<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\invoice as in;
use App\Models\patient;
use App\Models\PatientAccount;
use App\Models\payment;
use App\Models\Product;
use App\Models\ProductOrServiceRequest;
use App\Models\service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Invoice;

class paymentController extends Controller
{
    public function process(Request $request)
    {
        $inputs = $request->input('productChecked');
        $productQty = $request->input('productQty');
        $serviceQty = $request->input('serviceQty');
        $checkboxServices = $request->input('someCheckbox');

        $products = [];
        $services = [];

        if (null != $request->input('productChecked') && count($inputs) > count($productQty)) {
            return redirect()->back()->withMessage('Please set a quantity for all selected entries');
        }
        if (null != $request->input('someCheckbox') && count($checkboxServices) > count($serviceQty)) {
            return redirect()->back()->withMessage('Please set a quantity for all selected entries');
        }
        $sumServices = 0;
        if (isset($checkboxServices)) {
            $services = ProductOrServiceRequest::with('service.price')->whereIn('id', array_values($checkboxServices))->get();
            // $services = service::with('price')->whereIn('id',$requests)->get();
            $total = 0;
            for ($i = 0; $i < count($services); ++$i) {
                $total += $services[$i]->service->price->sale_price * $serviceQty[$i];
            }
            $sumServices = $total;
        }

        $sumProducts = 0;

        if (isset($inputs)) {
            $products = ProductOrServiceRequest::with('product.price')->whereIn('id', array_values($inputs))->get();
            $productsTotal = 0;
            for ($j = 0; $j < count($products); ++$j) {
                $productsTotal += $products[$j]->product->price->current_sale_price * $productQty[$j];
            }
            $sumProducts = $productsTotal;
        }
        session(['serviceQty' => $serviceQty]);
        session(['productQty' => $productQty]);

        session(['selected' => $checkboxServices]);
        session(['products' => $inputs]);
        // dd(session('selected'));
        return view('admin.Accounts.summary', compact('products', 'services', 'sumServices', 'sumProducts', 'productQty', 'serviceQty'));
    }

    public function payment(Request $request)
    {
        try {
            $request->validate([
                'total' => 'required',
                'payment_type' => 'required',
                'patient_id' => 'required',
            ]);

            $serviceQty = session('serviceQty');
            $productQty = session('productQty');

            $serviceDiscounts = $request->input('serviceDiscount', []);
            $productDiscounts = $request->input('productDiscount', []);
            $servicePrices = $request->input('servicePrice', []);
            $productPrices = $request->input('productPrice', []);
            $totalDiscount = 0;

            // Save per-item discount to ProductOrServiceRequest and calculate total discount
            if (is_array($serviceDiscounts) && is_array($serviceQty) && is_array($servicePrices)) {
                $serviceIds = session('selected') ?? [];
                foreach ($serviceDiscounts as $i => $discount) {
                    $qty = isset($serviceQty[$i]) ? $serviceQty[$i] : 1;
                    $price = isset($servicePrices[$i]) ? $servicePrices[$i] : 0;
                    $rowDiscount = ($price * $qty) * ($discount / 100);
                    $totalDiscount += $rowDiscount;
                    if (isset($serviceIds[$i])) {
                        ProductOrServiceRequest::where('id', $serviceIds[$i])->update(['discount' => $discount]);
                    }
                }
            }
            if (is_array($productDiscounts) && is_array($productQty) && is_array($productPrices)) {
                $productIds = session('products') ?? [];
                foreach ($productDiscounts as $i => $discount) {
                    $qty = isset($productQty[$i]) ? $productQty[$i] : 1;
                    $price = isset($productPrices[$i]) ? $productPrices[$i] : 0;
                    $rowDiscount = ($price * $qty) * ($discount / 100);
                    $totalDiscount += $rowDiscount;
                    if (isset($productIds[$i])) {
                        ProductOrServiceRequest::where('id', $productIds[$i])->update(['discount' => $discount]);
                    }
                }
            }

            DB::beginTransaction();

            // deduct from acc bal if user is paying from acc
            if (strtolower($request->payment_type) == strtolower('ACC_WITHDRAW')) {
                $acc = \App\Models\PatientAccount::where('patient_id', $request->patient_id)->first();
                $new_bal = $acc->balance - $request->total;
                $acc->update([
                    'balance' => $new_bal,
                ]);
                $request->total = 0 - $request->total;
            }

            // Get HMO id if payment type is CLAIMS
            $hmo_id = null;
            if (strtolower($request->payment_type) == 'claims') {
                $patient = \App\Models\patient::find($request->patient_id);
                $hmo_id = $patient && $patient->hmo_id ? $patient->hmo_id : null;
            }

            // make the payment entry
            $payment = payment::create([
                'payment_type' => $request->payment_type,
                'total' => $request->total,
                'total_discount' => $totalDiscount,
                'reference_no' => $request->reference_no,
                'user_id' => Auth::id(),
                'patient_id' => $request->patient_id,
                'hmo_id' => $hmo_id,
            ]);
            // dd($payment);
            $pp = patient::find($request->patient_id);
            $app_set = appsettings();

            $data = in::create();
            $data->save();

            if ($payment) {
                // Prepare details for receipt
                $receiptDetails = [];

                // Services
                if (null != session('selected')) {
                    $services = ProductOrServiceRequest::with(['user', 'service.price'])->whereIn('id', array_values(session('selected')))->get();
                    $u = 0;
                    foreach ($services as $service) {
                        ProductOrServiceRequest::where('id', $service->id)->update([
                            'payment_id' => $payment->id,
                        ]);
                        $discount = isset($serviceDiscounts[$u]) ? $serviceDiscounts[$u] : 0;
                        $qty = isset($serviceQty[$u]) ? $serviceQty[$u] : 1;
                        $price = $service->service->price->sale_price;
                        $discountAmount = ($price * $qty) * ($discount / 100);
                        $amountPaid = ($price * $qty) - $discountAmount;

                        $receiptDetails[] = [
                            'type' => 'Service',
                            'name' => $service->service->service_name,
                            'price' => $price,
                            'qty' => $qty,
                            'discount_percent' => $discount,
                            'discount_amount' => $discountAmount,
                            'amount_paid' => $amountPaid,
                        ];
                        ++$u;
                    }
                }

                // Products
                if (session('products') != null) {
                    $products = ProductOrServiceRequest::with('product.price')->whereIn('id', array_values(session('products')))->get();
                    $l = 0;
                    foreach ($products as $product) {
                        ProductOrServiceRequest::where('id', $product->id)->update([
                            'payment_id' => $payment->id,
                        ]);
                        $discount = isset($productDiscounts[$l]) ? $productDiscounts[$l] : 0;
                        $qty = isset($productQty[$l]) ? $productQty[$l] : 1;
                        $price = $product->product->price->current_sale_price;
                        $discountAmount = ($price * $qty) * ($discount / 100);
                        $amountPaid = ($price * $qty) - $discountAmount;

                        $receiptDetails[] = [
                            'type' => 'Product',
                            'name' => $product->product->product_name,
                            'price' => $price,
                            'qty' => $qty,
                            'discount_percent' => $discount,
                            'discount_amount' => $discountAmount,
                            'amount_paid' => $amountPaid,
                        ];
                        ++$l;
                    }
                }

                // notes section
                $notes = [];
                if (strtolower($request->payment_type) == strtolower('ACC_WITHDRAW')) {
                    $notes = [
                        'Payment made from account credit',
                        'No cash was recieved',
                        'Current account credit is: ' . ($new_bal ?? ''),
                    ];
                } elseif (strtolower($request->payment_type) == strtolower('CLAIMS')) {
                    $notes = [
                        'Payment Billed to claims',
                        'No cash was recieved',
                        'Cash to be claimed from HMO',
                    ];
                } else {
                    $notes = [
                        'Funds Recieved in good condition - ' . $request->payment_type,
                    ];
                }
                $notes = implode('<br>', $notes);

                $site = $app_set;
                $patientName = userfullname($pp->user_id);
                $currentUserName = Auth::user() ? userfullname(Auth::id()) : '';
                $date = now()->format('Y-m-d H:i');
                $ref = $request->reference_no;

                // Render A4 receipt using blade (full-featured invoice style)
                $a4 = View::make('admin.Accounts.receipt_a4', [
                    'site' => $site,
                    'patientName' => $patientName,
                    'date' => $date,
                    'ref' => $ref,
                    'receiptDetails' => $receiptDetails,
                    'totalDiscount' => $totalDiscount,
                    'totalPaid' => $request->total,
                    'notes' => $notes,
                    'currentUserName' => $currentUserName,
                ])->render();

                // Render thermal receipt using blade (same layout)
                $thermal = View::make('admin.Accounts.receipt_thermal', [
                    'site' => $site,
                    'patientName' => $patientName,
                    'date' => $date,
                    'ref' => $ref,
                    'receiptDetails' => $receiptDetails,
                    'totalDiscount' => $totalDiscount,
                    'totalPaid' => $request->total,
                    'notes' => $notes,
                    'currentUserName' => $currentUserName,
                ])->render();

                Session::forget('selected', 'serviceQty');
                DB::commit();

                // Show both receipts as tabs/links
                return response("
                    <div style='text-align:center;margin-top:30px'>
                        <a href='#' onclick=\"document.getElementById('a4receipt').style.display='block';document.getElementById('thermalreceipt').style.display='none';return false;\">A4 Receipt</a> |
                        <a href='#' onclick=\"document.getElementById('thermalreceipt').style.display='block';document.getElementById('a4receipt').style.display='none';return false;\">Thermal Receipt</a>
                    </div>
                    <div id='a4receipt' style='margin:0 auto;max-width:900px;display:block'>{$a4}</div>
                    <div id='thermalreceipt' style='margin:0 auto;max-width:220px;display:none'>{$thermal}</div>
                ");
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);

            return redirect()->route('product-or-service-request.index')->withInput()->with('err', $e->getMessage());
        }
    }

    public function transactions(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $payment_type = $request->input('payment_type', null);

        $query = \App\Models\payment::query()
            ->with(['patient', 'user'])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        if ($payment_type) {
            $query->where('payment_type', $payment_type);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        $total_amount = $transactions->sum('total');
        $total_discount = $transactions->sum('discount');
        $total_count = $transactions->count();

        $by_type = $transactions->groupBy('payment_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'amount' => $group->sum('total'),
                'discount' => $group->sum('discount'),
            ];
        });

        return view('admin.Accounts.transactions', compact('transactions', 'from', 'to', 'payment_type', 'total_amount', 'total_discount', 'total_count', 'by_type'));
    }
}
