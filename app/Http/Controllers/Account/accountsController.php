<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{service,detail,ProductOrServiceRequest};
use Yajra\DataTables\DataTables;

class accountsController extends Controller
{
    public function index()
    {
        return view('admin.Accounts.services');
    }
    public function products()
    {
        $products = ProductOrServiceRequest::with('product')->where('product_id',!NULL)->where('invoice_id',NULL)->get();
        return DataTables::of($products)
        ->addIndexColumn()
        ->addColumn('checkBox',function($product){

            return '<input type="checkbox" value="'.$product->id.'" name="someCheckbox[]" />';
        })
        ->rawColumns(['checkBox'])
        ->make(true);
    }
    public function services($id)
    {
        // dd($id);

        $services = ProductOrServiceRequest::with('service')->where('user_id',$id)->where('service_id',!NULL)->where('invoice_id',NULL)->get();
        // orderBy('id','DESC')->paginate(10);g
        return DataTables::of($services)
        ->addIndexColumn()
        ->addColumn('checkBox',function($service){

            return '<input type="checkbox" value="'.$service->id.'" name="someCheckbox[]" />';
        })
        ->rawColumns(['checkBox'])
        ->make(true);
        // return view('admin.accounts.services',compact('services'));
//
    }
    public function search(Request $request)
    {
        if($request->ajax()){
            $output = "";

            $services = service::where('name','LIKE','%'.$request->name.'%')->get();
            if($services)
            {
                foreach($services as $service)
                {

                    $output.='<tr>'.
                    '<td>'.$service->id.'</td>'.
                    '<td>'.$service->name.'</td>'.
                    '<td>'.$service->created_at->diffForHumans().'</td>'.

                    '</tr>';

                }

                return Response($output);
            }
        }

    }


    public function fullySettled($id){

        $details = detail::where('patient_id',$id)->where('has_paid',1)->get();
        return view('accounts.details',compact('details'));
    }



    public function unsettled($id){
        $details = detail::where('patient_id',$id)->where('has_paid',0)->get();
        return view('accounts.details',compact('details'));

    }




}
