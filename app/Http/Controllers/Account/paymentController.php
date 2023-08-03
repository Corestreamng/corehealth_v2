<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Product, service, detail, payment, ProductOrServiceRequest, invoice as in, patient, PatientAccount};
use Facade\FlareClient\Truncation\AbstractTruncationStrategy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LaravelDaily\Invoices\Invoice;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use Session;


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

        if (null != ($request->input('productChecked')) && count($inputs) > count($productQty)) {
            return redirect()->back()->withMessage("Please set a quantity for all selected entries");
        }
        if (null != ($request->input('someCheckbox')) && count($checkboxServices) > count($serviceQty)) {
            return redirect()->back()->withMessage("Please set a quantity for all selected entries");
        }
        $sumServices = 0;
        if (isset($checkboxServices)) {
            $services = ProductOrServiceRequest::with('service.price')->whereIn('id', array_values($checkboxServices))->get();
            // $services = service::with('price')->whereIn('id',$requests)->get();
            $total = 0;
            for ($i = 0; $i < count($services); $i++) {
                $total += $services[$i]->service->price->sale_price * $serviceQty[$i];
            }
            $sumServices = $total;
        }


        $sumProducts = 0;

        if (isset($inputs)) {
            $products = ProductOrServiceRequest::with('product.price')->whereIn('id', array_values($inputs))->get();
            $productsTotal = 0;
            for ($j = 0; $j < count($products); $j++) {
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
                'patient_id' => 'required'
            ]);

            $serviceQty = session('serviceQty');
            $productQty = session('productQty');

            DB::beginTransaction();

            //deduct from acc bal if user is paying from acc
            if (strtolower($request->payment_type) == strtolower('ACC_WITHDRAW')) {
                $acc = PatientAccount::where('patient_id', $request->patient_id)->first();
                $new_bal = $acc->balance - $request->total;
                $acc->update([
                    'balance' => $new_bal
                ]);

                $request->total = 0 - $request->total;
            }
            $payment = payment::create([
                'payment_type' => $request->payment_type,
                'total' => $request->total,
                'reference_no' => $request->reference_no,
                'user_id' => Auth::id(),
                'patient_id' => $request->patient_id
            ]);
            // dd($payment);
            $pp = patient::find($request->patient_id);
            $app_set = appsettings();
            if ($payment) {
                $data = in::create();
                if (session('selected') == !NULL) {
                    $services = ProductOrServiceRequest::whereIn('id', array_values(session('selected')))->update(['invoice_id' => $data->id]);

                    $patient = new Party(
                        [
                            'name'          => userfullname($pp->user_id),
                            'phone'         => $pp->phone,
                            'custom_fields' => [],
                        ]

                    );
                    $coreHealth = new Party(
                        [
                            'name'          => $app_set->site_name,
                            'phone'         => $app_set->contact_phones,
                            'custom_fields' => [
                                'Address' => $app_set->contact_address
                            ],
                        ]

                    );
                    $services = ProductOrServiceRequest::with(['user', 'service.price'])->whereIn('id', array_values(session('selected')))->get();

                    // $services =  service::with('price')->whereIn('id',$requests)->get();

                    $items = collect();
                    $u = 0;
                    foreach ($services as $service) {
                        ProductOrServiceRequest::where('id', $service->id)->update([
                            'payment_id' => $payment->id
                        ]);
                        $item = (new InvoiceItem())
                            ->title($service->service->service_name)
                            ->pricePerUnit($service->service->price->sale_price)
                            ->quantity($serviceQty[$u]);
                        $items->push($item);
                        $u++;
                    }
                    // dd($items);

                    if (session('products') != NULL) {
                        $prods = ProductOrServiceRequest::with('product')->whereIn('id', array_values(session('products')))->update(['invoice_id' => $data->id]);;
                        $products = ProductOrServiceRequest::with('product.price')->whereIn('id', array_values(session('products')))->get();
                        // $products =  Product::with('price')->whereIn('id',$req)->get();
                        // dd($products);

                        $goods = collect();
                        $l = 0;
                        foreach ($products as $product) {
                            ProductOrServiceRequest::where('id', $product->id)->update([
                                'payment_id' => $payment->id
                            ]);
                            $product = (new InvoiceItem())
                                ->title($product->product->product_name)
                                ->pricePerUnit($product->product->price->current_sale_price)
                                ->quantity($productQty[$l]);
                            $goods->push($product);
                        }

                        // dd($goods);

                        if (strtolower($request->payment_type) == strtolower('ACC_WITHDRAW')) {
                            $notes = [
                                'Payment made from account credit',
                                'No cash was recieved',
                                'Current account credit is: ' . $new_bal,
                            ];
                        } elseif (strtolower($request->payment_type) == strtolower('CLAIMS')) {
                            $notes = [
                                'Payment Billed to claims',
                                'No cash was recieved',
                                'Cash to be claimed from HMO',
                            ];
                        } else {
                            $notes = [
                                'Funds Recieved in good condition -'.$request->payment_type,
                            ];
                        }

                        $notes = implode("<br>", $notes);

                        $invoice = Invoice::make('receipt', [
                            'paper_size' => [50, 200]
                        ])
                            ->series('BIG')
                            // ability to include translated invoice status
                            // in case it was paid
                            ->status(__('invoices::invoice.paid'))
                            ->sequence((($request->reference_no) ? $request->reference_no : generate_invoice_no()))
                            ->serialNumberFormat('{SEQUENCE}/{SERIES}')
                            ->seller($coreHealth)
                            ->buyer($patient)
                            ->currencySymbol('₦')
                            ->date(now())
                            ->dateFormat('m/d/Y')
                            // ->filename($client->name . ' ' . $customer->name)
                            ->addItems($items)
                            ->addItems($goods)
                            ->notes($notes)
                            ->save('public');
                        payment::latest()->update(['invoice_id' => $data->id]);

                        $link = $invoice->url();
                        // Then send email to party with link

                        // And return invoice itself to browser or have a different view
                        Session::forget(['selected', 'products', 'productQty', 'serviceQty']);
                        DB::commit();
                        return $invoice->stream();

                        // composer require laraveldaily/laravel-invoices
                    }
                }
                if (strtolower($request->payment_type) == strtolower('ACC_WITHDRAW')) {
                    $notes = [
                        'Payment made from account credit',
                        'No cash was recieved',
                        'Current account credit is: ' . $new_bal,
                    ];
                } elseif (strtolower($request->payment_type) == strtolower('CLAIMS')) {
                    $notes = [
                        'Payment Billed to claims',
                        'No cash was recieved',
                        'Cash to be claimed from HMO',
                    ];
                } else {
                    $notes = [
                        'Funds Recieved in good condition - '.$request->payment_type,
                    ];
                }
                $notes = implode("<br>", $notes);

                $invoice = Invoice::make('receipt', [
                    'paper_size' => [50, 200]
                ])
                    ->series('BIG')
                    // ability to include translated invoice status
                    // in case it was paid
                    ->status(__('invoices::invoice.paid'))
                    ->sequence((($request->reference_no) ? $request->reference_no : generate_invoice_no()))
                    ->serialNumberFormat('{SEQUENCE}/{SERIES}')
                    ->seller($coreHealth)
                    ->buyer($patient)
                    ->currencySymbol('₦')
                    ->date(now())
                    ->dateFormat('m/d/Y')
                    // ->filename($client->name . ' ' . $customer->name)
                    ->addItems($items)
                    ->notes($notes)
                    ->save('public');
                payment::latest()->update(['invoice_id' => $data->id]);

                $link = $invoice->url();
                // Then send email to party with link

                // And return invoice itself to browser or have a different view
                Session::forget('selected', 'serviceQty');
                return $invoice->stream();


                if (session('products') != NULL) {
                    $prods = ProductOrServiceRequest::with('product')->whereIn('id', array_values(session('products')))->update(['invoice_id' => $data->id]);
                    $patient = new Party(
                        [
                            'name'          => userfullname($pp->user_id),
                            'phone'         => $pp->phone,
                            'custom_fields' => [],
                        ]

                    );
                    $coreHealth = new Party(
                        [
                            'name'          => $app_set->site_name,
                            'phone'         => $app_set->contact_phones,
                            'custom_fields' => [
                                'Address' => $app_set->contact_address
                            ],
                        ]

                    );
                    $products = ProductOrServiceRequest::with('product.price')->whereIn('id', array_values(session('products')))->get();

                    $goods = collect();
                    $k = 0;
                    foreach ($products as $product) {
                        ProductOrServiceRequest::where('id', $product->id)->update([
                            'payment_id' => $payment->id
                        ]);
                        $product = (new InvoiceItem())
                            ->title($product->product->product_name)
                            ->pricePerUnit($product->product->price->current_sale_price)
                            ->quantity($productQty[$k]);
                        $goods->push($product);
                    }
                    // dd($goods);
                    if (strtolower($request->payment_type) == strtolower('ACC_WITHDRAW')) {
                        $notes = [
                            'Payment made from account credit',
                            'No cash was recieved',
                            'Current account credit is: ' . $new_bal,
                        ];
                    } elseif (strtolower($request->payment_type) == strtolower('CLAIMS')) {
                        $notes = [
                            'Payment Billed to claims',
                            'No cash was recieved',
                            'Cash to be claimed from HMO',
                        ];
                    } else {
                        $notes = [
                            'Funds Recieved in good condition - '.$request->payment_type,
                        ];
                    }
                    $notes = implode("<br>", $notes);

                    $invoice = Invoice::make('receipt', [
                        'paper_size' => [50, 200]
                    ])
                        ->series('BIG')
                        // ability to include translated invoice status
                        // in case it was paid
                        ->status(__('invoices::invoice.paid'))
                        ->sequence((($request->reference_no) ? $request->reference_no : generate_invoice_no()))
                        ->serialNumberFormat('{SEQUENCE}/{SERIES}')
                        ->seller($coreHealth)
                        ->buyer($patient)
                        ->currencySymbol('₦')
                        ->date(now())
                        ->dateFormat('m/d/Y')
                        // ->filename($client->name . ' ' . $customer->name)

                        ->addItems($goods)
                        ->notes($notes)
                        ->save('public');
                    payment::latest()->update(['invoice_id' => $data->id]);
                    $link = $invoice->url();
                    // Then send email to party with link

                    // And return invoice itself to browser or have a different view
                    Session::forget('products', 'productQty');
                    DB::commit();
                    return $invoice->stream();
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->route('product-or-service-request.index')->withInput()->with('error', $e->getMessage());
        }
    }
}
