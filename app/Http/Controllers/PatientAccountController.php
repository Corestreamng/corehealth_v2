<?php

namespace App\Http\Controllers;

use App\Models\PatientAccount;
use App\Models\payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class PatientAccountController extends Controller
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

    public function makeDeposit(Request $request)
    {
        try {
            $request->validate([
                'patient_id' => 'required',
                'acc_id' => 'required',
                'amount' => 'required'
            ]);

            DB::beginTransaction();

            $acc = PatientAccount::where('id', $request->acc_id)->first();
            $new_bal = $acc->balance + $request->amount;
            $acc->update([
                'balance' => $new_bal
            ]);

            $pay = new payment;
            $pay->patient_id = $request->patient_id;
            $pay->user_id = Auth::id();
            $pay->total = $request->amount;
            $pay->reference_no = generate_invoice_no();
            $pay->payment_type = 'ACC_DEPOSIT';
            $pay->save();
            DB::commit();
            return redirect()->back()->with(['message' => "Deposit Saved Successfully", 'message_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error', $e);
        }
    }

    public function patientPaymentHistoryList($patient_id)
    {

        $hist = payment::where('patient_id', $patient_id)->with('product_or_service_request','patient','staff_user')->get();
        //dd($pc);
        return Datatables::of($hist)
            ->addIndexColumn()
            ->editColumn('user_id', function ($hist) {
                return (userfullname($hist->user_id));
            })
            ->editColumn('created_at', function ($hist) {
                return date('h:i a D M j, Y', strtotime($hist->created_at));
            })
            ->addColumn('product_or_service_request', function($hist){
                $str = '';
                foreach($hist->product_or_service_request as $rr){
                    $str .= '<small>['.$rr->service->category->category_name.'] '
                    .$rr->service->service_name.'('.$rr->service->service_code.')</small><br>';
                }
                return $str;
            })
            ->rawColumns(['product_or_service_request'])
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PatientAccount  $patientAccount
     * @return \Illuminate\Http\Response
     */
    public function show(PatientAccount $patientAccount)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\PatientAccount  $patientAccount
     * @return \Illuminate\Http\Response
     */
    public function edit(PatientAccount $patientAccount)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PatientAccount  $patientAccount
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PatientAccount $patientAccount)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PatientAccount  $patientAccount
     * @return \Illuminate\Http\Response
     */
    public function destroy(PatientAccount $patientAccount)
    {
        //
    }
}
