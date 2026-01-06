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
use App\Models\HmoClaim;
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
                // Use payable_amount if set (HMO patient), otherwise use regular price
                $price = $services[$i]->payable_amount !== null
                    ? $services[$i]->payable_amount
                    : $services[$i]->service->price->sale_price;
                $total += $price * $serviceQty[$i];
            }
            $sumServices = $total;
        }

        $sumProducts = 0;

        if (isset($inputs)) {
            $products = ProductOrServiceRequest::with('product.price')->whereIn('id', array_values($inputs))->get();
            $productsTotal = 0;
            for ($j = 0; $j < count($products); ++$j) {
                // Use payable_amount if set (HMO patient), otherwise use regular price
                $price = $products[$j]->payable_amount !== null
                    ? $products[$j]->payable_amount
                    : $products[$j]->product->price->current_sale_price;
                $productsTotal += $price * $productQty[$j];
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

            // make the payment entry
            $payment = payment::create([
                'payment_type' => $request->payment_type,
                'total' => $request->total,
                'total_discount' => $totalDiscount,
                'reference_no' => $request->reference_no,
                'user_id' => Auth::id(),
                'patient_id' => $request->patient_id,
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
                        // Assign payment_id to mark as paid
                        // Mark only the intended row for this patient as paid (avoid touching other patients/items)
                        ProductOrServiceRequest::where('id', $service->id)
                            ->where('user_id', $request->patient_id)
                            ->whereNull('invoice_id')
                            ->update([
                                'payment_id' => $payment->id,
                            ]);
                        $discount = isset($serviceDiscounts[$u]) ? $serviceDiscounts[$u] : 0;
                        $qty = isset($serviceQty[$u]) ? $serviceQty[$u] : 1;
                        // Use payable_amount if set (HMO patient), otherwise use regular price
                        $price = $service->payable_amount !== null
                            ? $service->payable_amount
                            : $service->service->price->sale_price;
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
                        // Assign payment_id to mark as paid
                        // Mark only the intended row for this patient as paid (avoid touching other patients/items)
                        ProductOrServiceRequest::where('id', $product->id)
                            ->where('user_id', $request->patient_id)
                            ->whereNull('invoice_id')
                            ->update([
                                'payment_id' => $payment->id,
                            ]);
                        $discount = isset($productDiscounts[$l]) ? $productDiscounts[$l] : 0;
                        $qty = isset($productQty[$l]) ? $productQty[$l] : 1;
                        // Use payable_amount if set (HMO patient), otherwise use regular price
                        $price = $product->payable_amount !== null
                            ? $product->payable_amount
                            : $product->product->price->current_sale_price;
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
                } else {
                    $notes = [
                        'Funds Recieved in good condition - ' . $request->payment_type,
                    ];
                }
                $notes = implode('<br>', $notes);

                // Safety net: ensure all selected items (services/products) are marked paid for this patient
                $selectedIds = array_values(session('selected') ?? []);
                $selectedProductIds = array_values(session('products') ?? []);
                $allSelectedIds = array_filter(array_merge($selectedIds, $selectedProductIds));
                if (!empty($allSelectedIds)) {
                    ProductOrServiceRequest::whereIn('id', $allSelectedIds)
                        ->where('user_id', $request->patient_id)
                        ->whereNull('invoice_id')
                        ->update(['payment_id' => $payment->id]);
                }

                // Create HMO Claim if patient has HMO and there are claims
                $patient = patient::find($request->patient_id);
                if ($patient && $patient->hmo_id) {
                    $claimsTotal = 0;

                    // Calculate total claims from services
                    if (null != session('selected')) {
                        $services = ProductOrServiceRequest::whereIn('id', array_values(session('selected')))->get();
                        foreach ($services as $service) {
                            if ($service->claims_amount > 0) {
                                $qty = isset($serviceQty[array_search($service->id, session('selected'))]) ? $serviceQty[array_search($service->id, session('selected'))] : 1;
                                $claimsTotal += $service->claims_amount * $qty;
                            }
                        }
                    }

                    // Calculate total claims from products
                    if (session('products') != null) {
                        $products = ProductOrServiceRequest::whereIn('id', array_values(session('products')))->get();
                        foreach ($products as $product) {
                            if ($product->claims_amount > 0) {
                                $qty = isset($productQty[array_search($product->id, session('products'))]) ? $productQty[array_search($product->id, session('products'))] : 1;
                                $claimsTotal += $product->claims_amount * $qty;
                            }
                        }
                    }

                    // Create HMO claim if there are claims
                    if ($claimsTotal > 0) {
                        HmoClaim::create([
                            'hmo_id' => $patient->hmo_id,
                            'patient_id' => $patient->id,
                            'payment_id' => $payment->id,
                            'claims_amount' => $claimsTotal,
                            'status' => 'pending',
                            'created_by' => Auth::id(),
                        ]);
                    }
                }

                $site = $app_set;
                $patientName = userfullname($pp->user_id);
                $patientFileNo = $pp->file_no ?? 'N/A';
                $currentUserName = Auth::user() ? userfullname(Auth::id()) : '';
                $date = now()->format('Y-m-d H:i');
                $ref = $request->reference_no;

                // Amount in words (Naira and kobo)
                $amountParts = explode('.', number_format((float) $request->total, 2, '.', ''));
                $nairaWords = convert_number_to_words((int) $amountParts[0]);
                $koboWords = ((int) $amountParts[1]) > 0 ? ' and ' . convert_number_to_words((int) $amountParts[1]) . ' Kobo' : '';
                $amountInWords = ucwords($nairaWords . ' Naira' . $koboWords);

                // Render A4 receipt using blade (full-featured invoice style)
                $a4 = View::make('admin.Accounts.receipt_a4', [
                    'site' => $site,
                    'patientName' => $patientName,
                    'patientFileNo' => $patientFileNo,
                    'date' => $date,
                    'ref' => $ref,
                    'receiptDetails' => $receiptDetails,
                    'totalDiscount' => $totalDiscount,
                    'totalPaid' => $request->total,
                    'amountInWords' => $amountInWords,
                    'paymentType' => $request->payment_type,
                    'notes' => $notes,
                    'currentUserName' => $currentUserName,
                ])->render();

                // Render thermal receipt using blade (same layout)
                $thermal = View::make('admin.Accounts.receipt_thermal', [
                    'site' => $site,
                    'patientName' => $patientName,
                    'patientFileNo' => $patientFileNo,
                    'date' => $date,
                    'ref' => $ref,
                    'receiptDetails' => $receiptDetails,
                    'totalDiscount' => $totalDiscount,
                    'totalPaid' => $request->total,
                    'amountInWords' => $amountInWords,
                    'paymentType' => $request->payment_type,
                    'notes' => $notes,
                    'currentUserName' => $currentUserName,
                ])->render();

                Session::forget(['selected', 'serviceQty', 'products', 'productQty']);
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

    /**
     * AJAX: fetch unpaid items (services/products) for a patient.
     */
    public function ajaxUnpaid(Request $request, $userId)
    {
        $patient = patient::where('user_id', $userId)->with('hmo')->firstOrFail();

        $items = ProductOrServiceRequest::with([
                'service.price',
                'service.category',
                'product.price',
                'product.category',
            ])
            ->where('user_id', $userId)
            ->whereNull('payment_id')
            ->whereNull('invoice_id')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($row) {
                $isService = $row->service_id !== null;
                $basePrice = $isService
                    ? optional(optional($row->service)->price)->sale_price
                    : optional(optional($row->product)->price)->current_sale_price;

                $price = $row->payable_amount !== null ? $row->payable_amount : ($basePrice ?? 0);

                return [
                    'id' => $row->id,
                    'type' => $isService ? 'service' : 'product',
                    'name' => $isService ? optional($row->service)->service_name : optional($row->product)->product_name,
                    'code' => $isService ? optional($row->service)->service_code : optional($row->product)->product_code,
                    'category' => $isService ? optional(optional($row->service)->category)->category_name : optional(optional($row->product)->category)->category_name,
                    'qty' => $row->qty ?? 1,
                    'price' => $price,
                    'base_price' => $basePrice ?? 0,
                    'payable_amount' => $row->payable_amount,
                    'claims_amount' => $row->claims_amount,
                    'coverage_mode' => $row->coverage_mode,
                    'validation_status' => $row->validation_status,
                    'created_at' => $row->created_at,
                ];
            });

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'user_id' => $patient->user_id,
                'file_no' => $patient->file_no,
                'hmo_name' => optional($patient->hmo)->name,
                'hmo_no' => $patient->hmo_no,
            ],
            'items' => $items,
        ]);
    }

    /**
     * AJAX: process payment without session state.
     */
    public function ajaxPay(Request $request)
    {
        $data = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'payment_type' => 'required|string',
            'reference_no' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:1',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();

        try {
            $patient = patient::with('hmo')->findOrFail($data['patient_id']);

            $ids = collect($data['items'])->pluck('id')->all();

            $rows = ProductOrServiceRequest::with(['service.price', 'product.price', 'user'])
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($rows->count() !== count($ids)) {
                throw new \Exception('Some selected items could not be found.');
            }

            $total = 0;
            $totalDiscount = 0;
            $receiptDetails = [];
            $claimsTotal = 0;

            // Validate ownership and build totals
            foreach ($data['items'] as $itemPayload) {
                $row = $rows->firstWhere('id', $itemPayload['id']);
                if (!$row || $row->user_id != $patient->user_id) {
                    throw new \Exception('Item does not belong to patient or is missing.');
                }
                if ($row->payment_id !== null || $row->invoice_id !== null) {
                    throw new \Exception('One of the items is already paid or invoiced.');
                }

                $isService = $row->service_id !== null;
                $basePrice = $isService
                    ? optional(optional($row->service)->price)->sale_price
                    : optional(optional($row->product)->price)->current_sale_price;
                $price = $row->payable_amount !== null ? $row->payable_amount : ($basePrice ?? 0);

                $qty = $itemPayload['qty'];
                $discountPercent = isset($itemPayload['discount']) ? $itemPayload['discount'] : 0;
                $discountAmount = ($price * $qty) * ($discountPercent / 100);
                $lineTotal = ($price * $qty) - $discountAmount;

                $total += $lineTotal;
                $totalDiscount += $discountAmount;

                // Persist qty/discount to request row
                $row->qty = $qty;
                $row->discount = $discountPercent;
                $row->save();

                if ($row->claims_amount > 0) {
                    $claimsTotal += $row->claims_amount * $qty;
                }

                $receiptDetails[] = [
                    'type' => $isService ? 'Service' : 'Product',
                    'name' => $isService ? optional($row->service)->service_name : optional($row->product)->product_name,
                    'price' => $price,
                    'qty' => $qty,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'amount_paid' => $lineTotal,
                ];
            }

            // Create payment entry
            $payment = payment::create([
                'payment_type' => $data['payment_type'],
                'total' => $total,
                'total_discount' => $totalDiscount,
                'reference_no' => $data['reference_no'],
                'user_id' => Auth::id(),
                'patient_id' => $patient->id,
            ]);

            // Mark items as paid
            ProductOrServiceRequest::whereIn('id', $ids)
                ->where('user_id', $patient->user_id)
                ->whereNull('invoice_id')
                ->update(['payment_id' => $payment->id]);

            // Create HMO claim if applicable
            if ($patient->hmo_id && $claimsTotal > 0) {
                HmoClaim::create([
                    'hmo_id' => $patient->hmo_id,
                    'patient_id' => $patient->id,
                    'payment_id' => $payment->id,
                    'claims_amount' => $claimsTotal,
                    'status' => 'pending',
                    'created_by' => Auth::id(),
                ]);
            }

            $site = appsettings();
            $patientName = userfullname($patient->user_id);
            $patientFileNo = $patient->file_no ?? 'N/A';
            $currentUserName = Auth::user() ? userfullname(Auth::id()) : '';
            $date = now()->format('Y-m-d H:i');
            $ref = $data['reference_no'];

            $amountParts = explode('.', number_format((float) $total, 2, '.', ''));
            $nairaWords = convert_number_to_words((int) $amountParts[0]);
            $koboWords = ((int) $amountParts[1]) > 0 ? ' and ' . convert_number_to_words((int) $amountParts[1]) . ' Kobo' : '';
            $amountInWords = ucwords($nairaWords . ' Naira' . $koboWords);

            $a4 = View::make('admin.Accounts.receipt_a4', [
                'site' => $site,
                'patientName' => $patientName,
                'patientFileNo' => $patientFileNo,
                'date' => $date,
                'ref' => $ref,
                'receiptDetails' => $receiptDetails,
                'totalDiscount' => $totalDiscount,
                'totalPaid' => $total,
                'amountInWords' => $amountInWords,
                'paymentType' => $data['payment_type'],
                'notes' => '',
                'currentUserName' => $currentUserName,
            ])->render();

            $thermal = View::make('admin.Accounts.receipt_thermal', [
                'site' => $site,
                'patientName' => $patientName,
                'patientFileNo' => $patientFileNo,
                'date' => $date,
                'ref' => $ref,
                'receiptDetails' => $receiptDetails,
                'totalDiscount' => $totalDiscount,
                'totalPaid' => $total,
                'amountInWords' => $amountInWords,
                'paymentType' => $data['payment_type'],
                'notes' => '',
                'currentUserName' => $currentUserName,
            ])->render();

            DB::commit();

            return response()->json([
                'payment_id' => $payment->id,
                'total' => $total,
                'total_discount' => $totalDiscount,
                'receipt_a4' => $a4,
                'receipt_thermal' => $thermal,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AJAX payment failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function transactions(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $payment_type = $request->input('payment_type', null);

        $query = \App\Models\payment::query()
            ->with(['patient', 'patient.user'])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        if ($payment_type) {
            $query->where('payment_type', $payment_type);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        $total_amount = $transactions->sum('total');
        $total_discount = $transactions->sum('total_discount');
        $total_count = $transactions->count();

        $by_type = $transactions->groupBy('payment_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'amount' => $group->sum('total'),
                'discount' => $group->sum('total_discount'),
            ];
        });

        return view('admin.Accounts.transactions', compact('transactions', 'from', 'to', 'payment_type', 'total_amount', 'total_discount', 'total_count', 'by_type'));
    }

    public function myTransactions(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $payment_type = $request->input('payment_type', null);

        $query = \App\Models\payment::query()
            ->with(['patient', 'patient.user'])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->where('user_id', Auth::id());

        if ($payment_type) {
            $query->where('payment_type', $payment_type);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        $total_amount = $transactions->sum('total');
        $total_discount = $transactions->sum('total_discount');
        $total_count = $transactions->count();

        $by_type = $transactions->groupBy('payment_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'amount' => $group->sum('total'),
                'discount' => $group->sum('total_discount'),
            ];
        });

        return view('admin.Accounts.my_transactions', compact('transactions', 'from', 'to', 'payment_type', 'total_amount', 'total_discount', 'total_count', 'by_type'));
    }
}
