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
        //
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
                'selectedPrescBillRows' => 'required|array',
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
