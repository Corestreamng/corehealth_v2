<?php

namespace App\Http\Controllers;

use App\Models\ProductRequest;
use App\Models\AdmissionRequest;
use App\Models\Clinic;
use App\Models\Encounter;
use App\Models\DoctorQueue;
use Yajra\DataTables\DataTables;
use App\Models\User;
use App\Models\Staff;
use App\Models\Hmo;
use App\Models\LabServiceRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\patient;
use App\Models\Product;
use App\Models\ProductOrServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if(request('history') == 1){
            return view('admin.product_requests.history');
        }else{
            return view('admin.product_requests.index');
        }
    }

    public function prescQueueList()
    {
        $his = ProductRequest::with(['product', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', 1)->orderBy('created_at', 'DESC')->get();
        //dd($pc);
        return Datatables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $url = route('patient.show',[$h->patient->id, 'section' => 'prescriptionsNotesCardBody']);
                $str = "
                    <a class='btn btn-primary' href='$url'>
                        view
                    </a>";
                return $str;
            })
            ->editColumn('patient_id', function($h){
                $str = "<small>";
                $str .= "<b >Patient </b> :". (($h->patient->user) ? userfullname($h->patient->user->id) : "N/A" );
                $str .= "<br><br><b >File No </b> : ". $h->patient->file_no;
                $str .= "<br><br><b >Insurance/HMO :</b> : ". (($h->patient->hmo) ? $h->patient->hmo->name : "N/A");
                $str .= "<br><br><b >HMO Number :</b> : ". (($h->patient->hmo_no) ? $h->patient->hmo_no : "N/A");
                $str .= "</small>" ; return $str;

            })
            ->editColumn('created_at', function ($h) {
                $str = "<small>";
                $str .= "<b >Requested By: </b>" . ((isset($h->doctor_id) && $h->doctor_id != null) ? (userfullname($h->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>") . '<br>';
                $str .= "<b >Last Updated On: </b>" . date('h:i a D M j, Y', strtotime($h->updated_at)) . '<br>';
                $str .= "<b >Billed/ Dispensed By: </b>" . ((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span><br>");
                $str .= "</small>" ; return $str;
            })
            ->editColumn('dose', function ($his) {
                $str = "<span class = 'badge badge-success'>[" . (($his->product->product_code) ? $his->product->product_code : '') . "]" . $his->product->product_name . "</span>";
                $str .= "<hr> <b>Dose/Freq:</b> " . ($his->dose ?? 'N/A');
                return $str;
            })
            ->rawColumns(['created_at', 'dose', 'select', 'patient_id'])
            ->make(true);
    }

    public function prescQueueHistoryList()
    {
        $his = ProductRequest::with(['product', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', 2)->orderBy('created_at', 'DESC')->get();
        //dd($pc);
        return Datatables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $url = route('patient.show',[$h->patient->id, 'section' => 'prescriptionsNotesCardBody']);
                $str = "
                    <a class='btn btn-primary' href='$url'>
                        view
                    </a>";
                return $str;
            })
            ->editColumn('patient_id', function($h){
                $str = "<small>";
                $str .= "<b >Patient </b> :". (($h->patient->user) ? userfullname($h->patient->user->id) : "N/A" );
                $str .= "<br><br><b >File No </b> : ". $h->patient->file_no;
                $str .= "<br><br><b >Insurance/HMO :</b> : ". (($h->patient->hmo) ? $h->patient->hmo->name : "N/A");
                $str .= "<br><br><b >HMO Number :</b> : ". (($h->patient->hmo_no) ? $h->patient->hmo_no : "N/A");
                $str .= "</small>" ; return $str;

            })
            ->editColumn('created_at', function ($h) {
                $str = "<small>";
                $str .= "<b >Requested By: </b>" . ((isset($h->doctor_id) && $h->doctor_id != null) ? (userfullname($h->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>") . '<br>';
                $str .= "<b >Last Updated On: </b>" . date('h:i a D M j, Y', strtotime($h->updated_at)) . '<br>';
                $str .= "<b >Billed/ Dispensed By: </b>" . ((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span><br>");
                $str .= "</small>" ; return $str;
            })
            ->editColumn('dose', function ($his) {
                $str = "<span class = 'badge badge-success'>[" . (($his->product->product_code) ? $his->product->product_code : '') . "]" . $his->product->product_name . "</span>";
                $str .= "<hr> <b>Dose/Freq:</b> " . ($his->dose ?? 'N/A');
                return $str;
            })
            ->rawColumns(['created_at', 'dose', 'select', 'patient_id'])
            ->make(true);
    }

    /**
     * dispense selected roduct requets
     */

    public function bill(Request $request)
    {
        try {
            $request->validate([
                'consult_presc_dose' => 'nullable|array|required_with:addedPrescBillRows',
                'addedPrescBillRows' => 'nullable|array|required_with:consult_presc_dose',
                'selectedPrescBillRows' => 'array',
                'patient_user_id' => 'required',
                'patient_id' => 'required'
            ]);

            if (isset($request->dismiss_presc_bill) && isset($request->selectedPrescBillRows)) {
                DB::beginTransaction();
                for ($i = 0; $i < count($request->selectedPrescBillRows); $i++) {
                    ProductRequest::where('id', $request->selectedPrescBillRows[$i])->update([
                        'status' => 0
                    ]);
                }
                DB::commit();
                return redirect()->back()->with(['message' => "Product Requests Dismissed Successfully", 'message_type' => 'success']);
            } else {
                DB::beginTransaction();
                if (isset($request->selectedPrescBillRows)) {
                    for ($i = 0; $i < count($request->selectedPrescBillRows); $i++) {
                        $prod_id = ProductRequest::where('id', $request->selectedPrescBillRows[$i])->first()->product->id;
                        $bill_req = new ProductOrServiceRequest;
                        $bill_req->user_id = $request->patient_user_id;
                        $bill_req->staff_user_id = Auth::id();
                        $bill_req->product_id = $prod_id;
                        $bill_req->save();


                        ProductRequest::where('id', $request->selectedPrescBillRows[$i])->update([
                            'status' => 2,
                            'billed_by' => Auth::id(),
                            'billed_date' => date('Y-m-d H:i:s'),
                            'product_request_id' => $bill_req->id
                        ]);

                        $product = Product::with(['stock'])->where('id',$prod_id)->first();
                        if ($product && $product->stock) {
                            $quantityToDecrement = 1; // You can adjust this value based on your requirements
                            $product->stock->decrement('current_quantity', $quantityToDecrement);
                        }

                        //Save the updated stock model back to the database
                        if ($product->stock) {
                            $product->stock->save();
                        }

                    }
                }
                if (isset($request->addedPrescBillRows)) {
                    for ($i = 0; $i < count($request->addedPrescBillRows); $i++) {
                        $bill_req = new ProductOrServiceRequest;
                        $bill_req->user_id = $request->patient_user_id;
                        $bill_req->staff_user_id = Auth::id();
                        $bill_req->product_id = $request->addedPrescBillRows[$i];
                        $bill_req->save();

                        $presc = new ProductRequest();
                        $presc->product_id = $request->addedPrescBillRows[$i];
                        $presc->dose = $request->consult_presc_dose[$i];
                        // $presc->encounter_id = $encounter->id;
                        $presc->billed_by = Auth::id();
                        $presc->billed_date = date('Y-m-d H:i:s');
                        $presc->patient_id = $request->patient_id;
                        $presc->doctor_id = Auth::id();
                        $presc->product_request_id = $bill_req->id;
                        $presc->status = 2;
                        $presc->save();

                        $product = Product::with(['stock'])->where('id',$request->addedPrescBillRows[$i])->first();
                        if ($product && $product->stock) {
                            $quantityToDecrement = 1; // You can adjust this value based on your requirements
                            $product->stock->decrement('current_quantity', $quantityToDecrement);
                        }

                        //Save the updated stock model back to the database
                        if ($product->stock) {
                            $product->stock->save();
                        }
                    }
                }
                DB::commit();
                return redirect()->back()->with(['message' => "Product Requests Dispensed & Billed Successfully", 'message_type' => 'success']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage() . 'line' . $e->getLine());
        }
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ProductRequest  $productRequest
     * @return \Illuminate\Http\Response
     */
    public function show(ProductRequest $productRequest)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ProductRequest  $productRequest
     * @return \Illuminate\Http\Response
     */
    public function edit(ProductRequest $productRequest)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ProductRequest  $productRequest
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ProductRequest $productRequest)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ProductRequest  $productRequest
     * @return \Illuminate\Http\Response
     */
    public function destroy(ProductRequest $productRequest)
    {
        //
    }
}
