<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{service,detail,ProductOrServiceRequest};
use Yajra\DataTables\DataTables;

class accountsController extends Controller
{
    public function index($identifier)
    {
        $id = $identifier;
        return view('admin.Accounts.services',compact('id'));
    }
    public function products($id)
    {

        // dd($id);
        $products = ProductOrServiceRequest::with('product.price')->where('service_id',NULL)->where('user_id',$id)->where('invoice_id',NULL)->get();
        // dd($products);
        return DataTables::of($products)
        ->addIndexColumn()
        ->addColumn('checkBox',function($product){

            return '<input type="checkbox" value="'.$product->id.'" name="productChecked[]" />';
        })
        ->rawColumns(['checkBox'])
        ->make(true);
    }
    public function services($id)
    {

        $identify = $id;

        $services = ProductOrServiceRequest::with('service.price')->where('product_id',NULL)->where('user_id',$identify)->where('invoice_id',NULL)->get();

        return DataTables::of($services)
        ->addIndexColumn()
        ->addColumn('checkBox',function($service){

            return '<input type="checkbox" value="'.$service->id.'" name="someCheckbox[]" />';
        })
        ->rawColumns(['checkBox'])
        ->make(true);

    }
    public function serviceView($id)
    {
        return view('admin.Accounts.settledServices',compact('id'));
    }
    public function productView($id){

        return view('admin.Accounts.settledProducts',compact('id'));

    }



    public function settledServices($id){
        $identify = $id;

        $services = ProductOrServiceRequest::with('service.price')->where('product_id',NULL)->where('user_id',$identify)->where('invoice_id',!NULL)->get();

        return DataTables::of($services)
        ->addIndexColumn()
        ->addColumn('checkBox',function($service){

            return '<input type="checkbox" value="'.$service->id.'" name="someCheckbox[]" />';
        })
        ->rawColumns(['checkBox'])
        ->make(true);
    }
    public function settledProducts($id)
    {
        $products = ProductOrServiceRequest::with('product.price')->where('service_id',NULL)->where('user_id',$id)->where('invoice_id',!NULL)->get();
        ;
        return DataTables::of($products)
        ->addIndexColumn()
        ->addColumn('checkBox',function($product){

            return '<input type="checkbox" value="'.$product->id.'" name="productChecked[]" />';
        })
        ->rawColumns(['checkBox'])
        ->make(true);

    }




}
