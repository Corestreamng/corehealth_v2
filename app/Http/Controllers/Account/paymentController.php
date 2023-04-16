<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{service,detail,payment,ProductOrServiceRequest,invoice as in};
use LaravelDaily\Invoices\Invoice;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Classes\InvoiceItem;


class paymentController extends Controller
{


    public function process(Request $request)
    {

        $checkBox = $request->input('someCheckbox');
        $id = $request->id;
        if ($checkBox == NULL) {
            return view('admin.Accounts.products',compact('id'));
        }
        session(['selected'=>$checkBox]);
        $checkboxValues = session('selected');
        // $services = service::whereIn('id',$checkboxValues)->get();
        return view('admin.Accounts.products',compact('id'));



    }


    public function payment(Request $request)
    {

        $request->validate([
            'total'=>'required',
            'payment_type'=>'required',
            'reference_no'=>'required'
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
                $services = ProductOrServiceRequest::with('service')->whereIn('id',array_values(session('selected')))->update(['invoice_id'=>$data->id]);;
                // dd($products);
                // // ->update(['invoice_id'=>$data->id]);
                // ProductOrServiceRequest::whereIn('id',session('product'))->update(['invoice_id'=>$data->id]);
                $patient = new Party([
                    'name'          => 'Roosevelt Lloyd',
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
        $services = ProductOrServiceRequest::with('service')->whereIn('id',array_values(session('selected')))->get();
        $items = collect();
          foreach($services as $service)
            {

                $item = (new InvoiceItem())
                ->title($service->service->service_name)
                ->pricePerUnit('100');
              $items->push($item);
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
                ->date(now()->subWeeks(3))
                ->dateFormat('m/d/Y')
                // ->filename($client->name . ' ' . $customer->name)
                ->addItems($items)
                ->notes($notes)
                ->save('public');

                $link = $invoice->url();
                // Then send email to party with link

                // And return invoice itself to browser or have a different view
                return $invoice->stream();

                // composer require laraveldaily/laravel-invoices

            }
            if(session('product')!= NULL){
                $services = ProductOrServiceRequest::with('product')->whereIn('id',array_values(session('product')))->update(['invoice_id'=>$data->id]);;
                // dd($products);
                // // ->update(['invoice_id'=>$data->id]);
                // ProductOrServiceRequest::whereIn('id',session('product'))->update(['invoice_id'=>$data->id]);
                $patient = new Party([
                    'name'          => 'Roosevelt Lloyd',
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
        $products = ProductOrServiceRequest::with('product')->whereIn('id',array_values(session('product')))->get();
        $items = collect();
          foreach($products as $product)
            {

                $item = (new InvoiceItem())
                ->title($product->product->product_name)
                ->pricePerUnit('100');
              $items->push($item);
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
                ->date(now()->subWeeks(3))
                ->dateFormat('m/d/Y')
                // ->filename($client->name . ' ' . $customer->name)
                ->addItems($items)
                ->notes($notes)
                ->save('public');

                $link = $invoice->url();
                // Then send email to party with link

                // And return invoice itself to browser or have a different view
                return $invoice->stream();



            }
            session()->forget('product','select');




    //         $products = ProductOrServiceRequest::whereIn('id',array_values(session('selected')))->get();
    //         dd($products);
    //         // ->update(['invoice_id'=>$data->id]);
    //         ProductOrServiceRequest::whereIn('id',session('product'))->update(['invoice_id'=>$data->id]);
    //         $patient = new Party([
    //             'name'          => 'Roosevelt Lloyd',
    //             'phone'         => '(520) 318-9486',
    //             'custom_fields' => [
    //         ],]

    //     );
    //      $coreHealth = new Party([
    //         'name'          => 'hospital name',
    //         'phone'         => 'hospital customer care',
    //         'custom_fields' => [
    //     ],]

    // );
    //   foreach($selectedServices as $service)
    //     {

    //         $item = (new InvoiceItem())
    //         ->title($service->name)
    //         ->pricePerUnit($service->price);

    //     }
    //     $notes = [
    //         'your multiline',
    //         'additional notes',
    //         'in regards of delivery or something else',
    //     ];
    //     $notes = implode("<br>", $notes);

    //     $invoice = Invoice::make('receipt')
    //         ->series('BIG')
    //         // ability to include translated invoice status
    //         // in case it was paid
    //         ->status(__('invoices::invoice.paid'))
    //         ->sequence(667)
    //         ->serialNumberFormat('{SEQUENCE}/{SERIES}')
    //         ->seller($coreHealth)
    //         ->buyer($patient)
    //         ->date(now()->subWeeks(3))
    //         ->dateFormat('m/d/Y')
    //         ->filename($client->name . ' ' . $customer->name)
    //         ->addItems($items)
    //         ->notes($notes)
    //         ->save('public');

    //         $link = $invoice->url();
    //         // Then send email to party with link

    //         // And return invoice itself to browser or have a different view
    //         return $invoice->stream();



        }






    }
}
