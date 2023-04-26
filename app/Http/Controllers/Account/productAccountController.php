<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Product,service,ProductOrServiceRequest};

class productAccountController extends Controller
{
    public function process(Request $request)

    {
        try {

            $inputs = $request->input('productChecked');
            $checkboxServices = session('selected');
            $requests = ProductOrServiceRequest::whereIn('id',array_values($checkboxServices))->pluck('service_id');
            $services = service::with('price')->whereIn('id',$requests)->get();
            $total = 0;
            foreach($services as $service) {
                $total += $service->price->sale_price;
            }
            $sumServices = $total;
            // dd($sumServices);
            if($inputs == NULL){


                return view('admin.Accounts.summary',compact('services','sumServices'));

            }
            else {
                // dd($inputs);
                session(['products'=>$inputs]);
                $checkboxProducts = session('products');
                // dd($checkboxProducts);
                $productRequests = ProductOrServiceRequest::whereIn('id',array_values($checkboxProducts))->pluck('product_id');
                // dd($productRequests);
                $products = Product::with('price')->whereIn('id',$productRequests)->get();
                $productsTotal = 0;
                foreach($products as $product) {
                    $productsTotal += $product->price->current_sale_price;
                }
                $sumProducts = $productsTotal;
                // dd($sumProducts);
                // dd($sumServices);
                return view('admin.Accounts.summary',compact('products','services','sumServices','sumProducts'));
            }

        } catch (\Throwable $th) {
            //throw $th;
        }

    }
}
