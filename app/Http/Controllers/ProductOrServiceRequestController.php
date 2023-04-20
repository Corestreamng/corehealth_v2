<?php

namespace App\Http\Controllers;

use App\Models\ProductOrServiceRequest;
use App\Models\DoctorQueue;
use App\Models\Hmo;
use App\Models\patient;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;

class ProductOrServiceRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.product_or_service_request.index');
    }

    public function productOrServicesRequestersList(){
        \DB::statement("SET SQL_MODE=''");//disable sql strict mode to allow groupby query
        $req = ProductOrServiceRequest::where('invoice_id', '=', null)->groupBy('user_id')->orderBy('created_at', 'DESC')->get();

        return Datatables::of($req)
            ->addIndexColumn()
            ->addColumn('show',function($r){
                $url = route('servicess', $r->user_id);
                return "<a href='$url' class='btn btn-info btn-sm' ><i class='fa fa-eye'></i> View</a>";
            })
            ->addColumn('patient',function($r){
                return userfullname($r->user_id);
            })
            ->addColumn('file_no',function($r){
                $p = patient::where('user_id', $r->user_id)->first();
                return $p->file_no ?? 'N/A';
            })
            ->addColumn('hmo',function($r){
                $p = patient::where('user_id', $r->user_id)->first();
                $hmo = Hmo::find($p->hmo_id);
                return $hmo->name ?? 'N/A';
            })
            ->addColumn('hmo_no',function($r){
                $p = patient::where('user_id', $r->user_id)->first();
                return $p->hmo_no ?? 'N/A';
            })
            ->rawColumns(['show'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        try {
            if ($request->is_consultation == 1) {
                if (count(array_filter($request->service_id)) < 1) {
                    $msg = "Please select a service for at least one of the listed patients";
                    return redirect()->back()->withMessage($msg)->withMessageType('warning');
                } else {
                    $service_ids = array_filter(($request->service_id));
                    sort($service_ids);
                    $clinic_ids  = array_filter($request->clinic_id);
                    sort($clinic_ids);
                    $user_ids  = array_filter($request->user_id);
                    sort($user_ids);
                    $doctor_ids  = array_filter($request->doctor_id);
                    sort($doctor_ids);
                    if (count($clinic_ids) == count($service_ids)) {
                        for ($i = 0; $i < count($service_ids); $i++) {
                            if ($service_ids[$i] != '') {
                                $req = new ProductOrServiceRequest;
                                $req->service_id = $service_ids[$i];
                                $req->user_id = $user_ids[$i];
                                $req->staff_user_id = Auth::id();
                                if ($req->save()) {
                                    $queue = new DoctorQueue;
                                    $p = patient::where('user_id', $user_ids[$i])->first();
                                    $d = Staff::find($doctor_ids[$i]);
                                    $r = Staff::where('user_id', Auth::id())->first();
                                    $queue->patient_id = $p->id;
                                    $queue->clinic_id = $clinic_ids[$i];
                                    $queue->receptionist_id = $r->id;
                                    $queue->staff_id = $d->id ?? null;
                                    $queue->request_entry_id = $req->id;
                                    if ($queue->save()) {
                                        $msg = "Request(s) saved successfully";
                                        return redirect()->route('add-to-queue')->withMessage($msg)->withMessageType('success')->withInput();
                                    } else {
                                        $req->delete();
                                        $msg = "An error occured while saving the request, please try again later";
                                        return redirect()->back()->withMessage($msg)->withMessageType('danger')->withInput();
                                    }
                                } else {
                                    $msg = "An error occured while saving the request, please try again later";
                                    return redirect()->back()->withMessage($msg)->withMessageType('danger')->withInput();
                                }
                            } else {
                                continue;
                            }
                        }
                    } else {
                        $msg = "Please specify a clinic for all patients for whom you specified a service";
                        return redirect()->back()->withMessage($msg)->withMessageType('warning')->withInput();
                    }
                }
            } else {
                if (count(array_filter($request->service_id)) < 1) {
                    $msg = "Please select a service for at least one of the listed patients";
                    return redirect()->back()->withMessage($msg)->withMessageType('warning');
                } else {
                    $service_ids = array_filter(($request->service_id));
                    sort($service_ids);
                    $user_ids  = array_filter($request->user_id);
                    sort($user_ids);
                    for ($i = 0; $i < count($service_ids); $i++) {
                        $req = new ProductOrServiceRequest;
                        $req->service_id = $service_ids[$i];
                        $req->user_id = $user_ids[$i];
                        $req->staff_user_id = Auth::id();
                        if ($req->save()) {
                            $msg = "Request(s) saved successfully";
                            return redirect()->route('add-to-queue')->withMessage($msg)->withMessageType('success')->withInput();
                        } else {
                            $msg = "An error occured while saving the request, please try again later";
                            return redirect()->back()->withMessage($msg)->withMessageType('danger')->withInput();
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->withMessage("An error occurred " . $e->getMessage() . 'line:' . $e->getLine());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ProductOrServiceRequest  $productOrServiceRequest
     * @return \Illuminate\Http\Response
     */
    public function show(ProductOrServiceRequest $productOrServiceRequest)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ProductOrServiceRequest  $productOrServiceRequest
     * @return \Illuminate\Http\Response
     */
    public function edit(ProductOrServiceRequest $productOrServiceRequest)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ProductOrServiceRequest  $productOrServiceRequest
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ProductOrServiceRequest $productOrServiceRequest)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ProductOrServiceRequest  $productOrServiceRequest
     * @return \Illuminate\Http\Response
     */
    public function destroy(ProductOrServiceRequest $productOrServiceRequest)
    {
        //
    }
}
