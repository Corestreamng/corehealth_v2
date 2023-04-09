<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class productAccountController extends Controller
{
    public function process(Request $request)
    {
        $inputs = $request->input('productChecked');
        session(['products'=>$inputs]);
        $checkboxProducts = session('products');
        product::whereIn('id',$checkboxProducts)->get();
        return view('admin.Accounts.summary');
    }
}
