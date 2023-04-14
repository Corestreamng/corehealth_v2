<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Product,service};

class productAccountController extends Controller
{
    public function process(Request $request)

    {
        try {
            $inputs = $request->input('productChecked');
            // dd($inputs);
            $checkboxServices = session('selected');
            // dd($checkboxServices);
            $services = service::whereIn('id',array_values($checkboxServices))->get();
            //  dd($services);
            if($inputs == NULL){


                return view('admin.Accounts.summary',compact('services'));

            }
            else {
                // dd($inputs);
                session(['products'=>$inputs]);
                $checkboxProducts = session('products');
                $products = Product::whereIn('id',$checkboxProducts)->get();
                dd($products);
                return view('admin.Accounts.summary',compact('checkboxProducts','services'));
            }

        } catch (\Throwable $th) {
            //throw $th;
        }

    }
}
