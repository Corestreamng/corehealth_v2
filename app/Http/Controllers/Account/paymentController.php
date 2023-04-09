<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{service,detail,payment,ProductOrServiceRequest};
use LaravelDaily\Invoices\Invoice;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Classes\InvoiceItem;


class paymentController extends Controller
{


    public function process(Request $request)
    {

        $checkBox = $request->input('someCheckbox');
        session(['selected',$checkBox]);
        $checkboxValues = session('selected');
        $services = service::whereIn('id',$checkboxValues)->get();

        return view('admin.Accounts.products');



    }


    public function payment(Request $request)
    {
        $request->validate([
            'total'=>'required',
            'payment_type'=>'required'
        ]);

        $payment = payment::create([
            'patient_id'=> $request->patient_id,
            'payment_type'=>$request->payment_type,
            'total'=>$request->total,
            'reference_no'=>$request->reference_no
        ]);
        if($payment){
            $data = new invoice;
            $data->create();
            ProductOrServiceRequest::whereIn('id',$selectedServices)->update(['invoice_id'=>$data->id]);

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
      foreach($selectedServices as $service)
        {

            $item = (new InvoiceItem())
            ->title($service->name)
            ->pricePerUnit($service->price);

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
            ->filename($client->name . ' ' . $customer->name)
            ->addItems($items)
            ->notes($notes)
            ->save('public');

            $link = $invoice->url();
            // Then send email to party with link

            // And return invoice itself to browser or have a different view
            return $invoice->stream();



        }






    }
}
