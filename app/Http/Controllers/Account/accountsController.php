<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Service,detail};

class accountsController extends Controller
{
    public function index()
    {
        return view('admin.dashboards.accounts');
    }
    public function services()
    {

        $services = service::orderBy('id','DESC')->paginate(10);
        return view('admin.accounts.services',compact('services'));
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
