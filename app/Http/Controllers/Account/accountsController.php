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
        $products = ProductOrServiceRequest::with('product')->where('service_id',NULL)->where('user_id',$id)->where('invoice_id',NULL)->get();
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
        // dd($id);
        $identify = $id;

        $services = ProductOrServiceRequest::with('service')->where('product_id',NULL)->where('user_id',$identify)->where('invoice_id',NULL)->get();
        // orderBy('id','DESC')->paginate(10);g
        // dd($services);
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
