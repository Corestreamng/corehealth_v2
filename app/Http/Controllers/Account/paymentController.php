<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Product,service,detail,payment,ProductOrServiceRequest,invoice as in};
use LaravelDaily\Invoices\Invoice;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use Session;


class paymentController extends Controller
{


    public function process(Request $request)
    {


        $checkBox = $request->input('someCheckbox');
        $id = $request->id;
        ;
        if ($checkBox == NULL) {
            return view('admin.Accounts.products',compact('id'));
        }
        session(['selected'=>$checkBox]);
        $checkboxValues = session('selected');


        return view('admin.Accounts.products',compact('id'));

    }


    public function payment(Request $request)
    {

        $request->validate([
            'total'=>'required',
            'payment_type'=>'required',
        ]);

        $payment = payment::create([
            'payment_type'=>$request->payment_type,
            'total'=>$request->total,
            'reference_no'=>$request->reference_no
        ]);
        // dd($payment);
        if($payment){
            $data = in::create();
            if(session('selected')== !NULL){
                $services = ProductOrServiceRequest::whereIn('id',array_values(session('selected')))->update(['invoice_id'=>$data->id]);

                $patient = new Party([
                    'name'          => 'core health',
                    'phone'         => '(520) 318-9486',
                    'custom_fields' => [
                ],]

            );
             $coreHealth = new Party([
                'name'          => 'Core Health',
                'phone'         => 'hospital customer care',
                'custom_fields' => [
            ],]

        );
        $requests = ProductOrServiceRequest::with(['user','service'])->whereIn('id',array_values(session('selected')))->pluck('service_id');

        $services =  service::with('price')->whereIn('id',$requests)->get();

        $items = collect();
          foreach($services as $service)
            {

                $item = (new InvoiceItem())
                ->title($service->service_name)
                ->pricePerUnit($service->price->sale_price);
              $items->push($item);
            }
        // dd($items);

        if(session('products')!= NULL){
            $prods = ProductOrServiceRequest::with('product')->whereIn('id',array_values(session('products')))->update(['invoice_id'=>$data->id]);;
            $req = ProductOrServiceRequest::with('product')->whereIn('id',array_values(session('products')))->pluck('product_id');
            $products =  Product::with('price')->whereIn('id',$req)->get();
            // dd($products);

            $goods = collect();
            foreach($products as $product)
                {

                $product = (new InvoiceItem())
                ->title($product->product_name)
                ->pricePerUnit($product->price->current_sale_price);
                $goods->push($product);
            }

            // dd($goods);
            $notes = [
                'your multiline',
                'additional notes',
                'in regards of delivery or something else',
            ];
            $notes = implode("<br>", $notes);

            $invoice = Invoice::make('receipt')
                ->series('BIG')
                // ability to include translated invoice status
                // in case it was paid
                ->status(__('invoices::invoice.paid'))
                ->sequence(667)
                ->serialNumberFormat('{SEQUENCE}/{SERIES}')
                ->seller($coreHealth)
                ->buyer($patient)
                ->currencySymbol('₦')
                ->date(now()->subWeeks(3))
                ->dateFormat('m/d/Y')
                // ->filename($client->name . ' ' . $customer->name)
                ->addItems($items)
                ->addItems($goods)
                ->notes($notes)
                ->save('public');
                payment::latest()->update(['invoice_id'=>$data->id]);

                $link = $invoice->url();
                // Then send email to party with link

                // And return invoice itself to browser or have a different view
                Session::forget(['selected','products']);
                return $invoice->stream();

                // composer require laraveldaily/laravel-invoices
        }

        }
        $notes = [
            'your multiline',
            'additional notes',
            'in regards of delivery or something else',
        ];
        $notes = implode("<br>", $notes);

        $invoice = Invoice::make('receipt')
            ->series('BIG')
            // ability to include translated invoice status
            // in case it was paid
            ->status(__('invoices::invoice.paid'))
            ->sequence(667)
            ->serialNumberFormat('{SEQUENCE}/{SERIES}')
            ->seller($coreHealth)
            ->buyer($patient)
            ->currencySymbol('₦')
            ->date(now()->subWeeks(3))
            ->dateFormat('m/d/Y')
            // ->filename($client->name . ' ' . $customer->name)
            ->addItems($items)
            ->notes($notes)
            ->save('public');
            payment::latest()->update(['invoice_id'=>$data->id]);

            $link = $invoice->url();
            // Then send email to party with link

            // And return invoice itself to browser or have a different view
            Session::forget('selected');
            return $invoice->stream();


            if(session('products')!= NULL){
                $prods = ProductOrServiceRequest::with('product')->whereIn('id',array_values(session('products')))->update(['invoice_id'=>$data->id]);
                $patient = new Party([
                    'name'          => 'core health',
                    'phone'         => '(520) 318-9486',
                    'custom_fields' => [
                ],]

            );
             $coreHealth = new Party([
                'name'          => 'hospital name',
                'phone'         => 'hospital customer care',
                'custom_fields' => [
            ],]
        );
                $req = ProductOrServiceRequest::with('product')->whereIn('id',array_values(session('products')))->pluck('product_id');
                $products =  Product::with('price')->whereIn('id',$req)->get();
                // dd($products);

                $goods = collect();
                foreach($products as $product)
                    {

                    $product = (new InvoiceItem())
                    ->title($product->product_name)
                    ->pricePerUnit($product->price->current_sale_price);
                    $goods->push($product);
                }

                // dd($goods);
                $notes = [
                    'your multiline',
                    'additional notes',
                    'in regards of delivery or something else',
                ];
                $notes = implode("<br>", $notes);

                $invoice = Invoice::make('receipt')
                    ->series('BIG')
                    // ability to include translated invoice status
                    // in case it was paid
                    ->status(__('invoices::invoice.paid'))
                    ->sequence(667)
                    ->serialNumberFormat('{SEQUENCE}/{SERIES}')
                    ->seller($coreHealth)
                    ->buyer($patient)
                    ->currencySymbol('₦')
                    ->date(now()->subWeeks(3))
                    ->dateFormat('m/d/Y')
                    // ->filename($client->name . ' ' . $customer->name)

                    ->addItems($goods)
                    ->notes($notes)
                    ->save('public');
                    payment::latest()->update(['invoice_id'=>$data->id]);
                    $link = $invoice->url();
                    // Then send email to party with link

                    // And return invoice itself to browser or have a different view
                    Session::forget('products');
                    return $invoice->stream();
            }


        }






    }
}
