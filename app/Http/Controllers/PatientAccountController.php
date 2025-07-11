<?php

namespace App\Http\Controllers;

use App\Models\MiscBill;
use App\Models\patient;
use App\Models\PatientAccount;
use App\Models\payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\service;
use App\Models\ServicePrice;
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
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function patientPaymentHistoryList($patient_id)
    {

        $hist = payment::where('patient_id', $patient_id)->with('product_or_service_request', 'patient', 'staff_user')->get();
        //dd($pc);
        return Datatables::of($hist)
            ->addIndexColumn()
            ->editColumn('user_id', function ($hist) {
                return (userfullname($hist->user_id));
            })
            ->editColumn('created_at', function ($hist) {
                return date('h:i a D M j, Y', strtotime($hist->created_at));
            })
            ->addColumn('product_or_service_request', function ($hist) {
                $str = '';
                foreach ($hist->product_or_service_request as $rr) {
                    $str .= '<small>[' . ($rr?->service->category?->category_name ?? $rr?->product->category?->category_name) . '] '
                        . ($rr?->service->service_name ?? $rr?->product->product_name) . '(' . ($rr?->service->service_code ?? $rr?->product->product_code) . ')</small><br>';
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
        try {
            $request->validate([
                'patient_id' => 'required'
            ]);

            $patient_account = new PatientAccount;
            $patient_account->patient_id = $request->patient_id;
            $patient_account->save();
            $msg = 'Patient Account was successfully created.';
            return redirect()->back()->withMessage($msg)->withMessageType('success');
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }


    public function addMsicBill(Request $request)
    {
        try {
            $request->validate([
                'names' => 'array|required',
                'prices' => 'array|required',
                'names.*' => 'required|string',
                'prices.*' => 'required|numeric',
                'patient_id' => 'required'
            ]);

            $patient = patient::where('id', $request->patient_id)->first();

            DB::beginTransaction();

            for ($i = 0; $i < count($request->names); $i++) {

                //create a misc service to associate the Misc bill with
                $misc_service                      = new service();
                $misc_service->user_id             = Auth::user()->id;
                $misc_service->category_id         = env('MISC_SERVICE_CATEGORY_ID');
                $misc_service->service_name        = trim('[' . userfullname($patient->user_id) . '] ' . $request->names[$i]);
                $misc_service->service_code        = trim($request->names[$i]);
                $misc_service->price_assign        = 1;
                $misc_service->status              = 1;


                $misc_service->save();


                //crete a price entry for the misc service creted above
                $price_entry = new ServicePrice;
                $price_entry->service_id = $misc_service->id;
                $price_entry->cost_price = $request->prices[$i];
                $price_entry->sale_price = $request->prices[$i];
                $price_entry->max_discount =  0;
                $price_entry->status = 1;

                $price_entry->save();

                //crete the actual misc bill entry, nowthat it has a service to be associated with

                $misc_bill = new MiscBill;
                $misc_bill->created_by = Auth::id();
                $misc_bill->creation_date = date('Y-m-d H:i:s');
                $misc_bill->service_id = $misc_service->id;
                $misc_bill->patient_id = $patient->id;
                $misc_bill->save();
            }
            DB::commit();
            $msg = 'Patient Misc. Bills Successfully Created.';
            return redirect()->back()->withMessage($msg)->withMessageType('success');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
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
