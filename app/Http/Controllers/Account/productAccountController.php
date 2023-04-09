<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class productAccountController extends Controller
{
    public function process(Request $request)
    {
        try {
            $inputs = $request->input('productChecked');
            if($inputs == NULL){

                return view('admin.Accounts.summary');
                return 'null';

            }
            else {
                dd($inputs);
                session(['products'=>$inputs]);
                $checkboxProducts = session('products');
                Product::whereIn('id',$checkboxProducts)->get();
                return view('admin.Accounts.summary');
            }
            
        } catch (\Throwable $th) {
            //throw $th;
        }
        
    }
}
