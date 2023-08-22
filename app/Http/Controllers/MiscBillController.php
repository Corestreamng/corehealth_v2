<?php

namespace App\Http\Controllers;

use App\Models\MiscBill;
use App\Models\ProductOrServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class MiscBillController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
    }

    public function miscBillList($patient_id)
    {
        $his = MiscBill::with(['service', 'creator', 'patient', 'productOrServiceRequest', 'biller'])
        ->where('status', 1)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();
        // dd($pc);
        return Datatables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $str = "<input type='checkbox' name='selectedMiscBillRows[]' onclick='checkMiscBillRow(this)' data-price = '".$h->service->price->sale_price."' value='$h->id' class='form-control'> ";

                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = '<small>';
                $str .= '<b >Created By: </b>'.((isset($h->created_by) && $h->created_by != null) ? (userfullname($h->created_by).' ('.date('h:i a D M j, Y', strtotime($h->creation_date)).')') : "<span class='badge badge-secondary'>N/A</span>").'<br>';
                $str .= '<b >Last Updated On: </b>'.date('h:i a D M j, Y', strtotime($h->updated_at)).'<br>';
                $str .= '<b >Billed By: </b>'.((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by).' ('.date('h:i a D M j, Y', strtotime($h->billed_date)).')') : "<span class='badge badge-secondary'>Not billed</span><br>");
                $str .= '</small>';

                return $str;
            })
            ->editColumn('dose', function ($his) {
                $str = "<span class = 'badge badge-success'>".$his->service->service_name.'</span>';
                $str .= '<hr> <b>Cost: </b> '.($his->service->price->sale_price ?? 'N/A');

                return $str;
            })
            ->rawColumns(['created_at', 'dose', 'select'])
            ->make(true);
    }

    public function miscBillHistList($patient_id = null)
    {
        if (null != $patient_id) {
            $his = MiscBill::with(['service', 'creator', 'patient', 'productOrServiceRequest', 'biller'])
                ->where('status', '>', 0)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();
            // dd($pc);
            return Datatables::of($his)
                ->addIndexColumn()
                ->editColumn('created_at', function ($h) {
                    $str = '<small>';
                    $str .= '<b >Created By: </b>'.((isset($h->created_by) && $h->created_by != null) ? (userfullname($h->created_by).' ('.date('h:i a D M j, Y', strtotime($h->creation_date)).')') : "<span class='badge badge-secondary'>N/A</span>").'<br>';
                    $str .= '<b >Last Updated On: </b>'.date('h:i a D M j, Y', strtotime($h->updated_at)).'<br>';
                    $str .= '<b >Billed By: </b>'.((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by).' ('.date('h:i a D M j, Y', strtotime($h->billed_date)).')') : "<span class='badge badge-secondary'>Not billed</span><br>");
                    $str .= '</small>';

                    return $str;
                })
                ->editColumn('dose', function ($his) {
                    $str = "<span class = 'badge badge-success'>".$his->service->service_name.'</span>';
                    $str .= '<hr> <b>Cost: </b> '.($his->service->price->sale_price ?? 'N/A');

                    return $str;
                })
                ->rawColumns(['created_at', 'dose'])
                ->make(true);
        } else {
            $his = MiscBill::with(['service', 'creator', 'patient', 'productOrServiceRequest', 'biller'])
            ->where('status', '>', 0)->orderBy('created_at', 'DESC')->get();
            // dd($pc);
            return Datatables::of($his)
                ->addIndexColumn()
                ->addColumn('patient', function ($h) {
                    $str = "Name: ".userfullname($h->patient->user_id);
                    $str .= "File No: ". $h->patient->file_no;
                    return $str;
                })
                ->editColumn('created_at', function ($h) {
                    $str = '<small>';
                    $str .= '<b >Created By: </b>'.((isset($h->created_by) && $h->created_by != null) ? (userfullname($h->created_by).' ('.date('h:i a D M j, Y', strtotime($h->creation_date)).')') : "<span class='badge badge-secondary'>N/A</span>").'<br>';
                    $str .= '<b >Last Updated On: </b>'.date('h:i a D M j, Y', strtotime($h->updated_at)).'<br>';
                    $str .= '<b >Billed By: </b>'.((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by).' ('.date('h:i a D M j, Y', strtotime($h->billed_date)).')') : "<span class='badge badge-secondary'>Not billed</span><br>");
                    $str .= '</small>';

                    return $str;
                })
                ->editColumn('dose', function ($his) {
                    $str = "<span class = 'badge badge-success'>".$his->service->service_name.'</span>';
                    $str .= '<hr> <b>Cost: </b> '.($his->service->price->sale_price ?? 'N/A');

                    return $str;
                })
                ->rawColumns(['created_at', 'dose', 'patient'])
                ->make(true);
        }
    }

    public function bill(Request $request)
    {
        // dd($request->all());
        try {
            $request->validate([
                'selectedMiscBillRows' => 'required|array',
                'patient_id' => 'required',
                'patient_user_id' => 'required',
            ]);

            if (isset($request->dismiss_misc_bill) && isset($request->selectedMiscBillRows)) {
                DB::beginTransaction();
                for ($i = 0; $i < count($request->selectedMiscBillRows); ++$i) {
                    MiscBill::where('id', $request->selectedMiscBillRows[$i])->update([
                        'status' => 0,
                    ]);
                }
                DB::commit();

                return redirect()->back()->with(['message' => 'Misc. Bill Requests Dismissed Successfully', 'message_type' => 'success']);
            } else {
                DB::beginTransaction();
                if (isset($request->selectedMiscBillRows)) {
                    for ($i = 0; $i < count($request->selectedMiscBillRows); ++$i) {
                        $service_id = MiscBill::where('id', $request->selectedMiscBillRows[$i])->first()->service->id;
                        $bill_req = new ProductOrServiceRequest();
                        $bill_req->user_id = $request->patient_user_id;
                        $bill_req->staff_user_id = Auth::id();
                        $bill_req->service_id = $service_id;
                        $bill_req->save();

                        MiscBill::where('id', $request->selectedMiscBillRows[$i])->update([
                            'status' => 2,
                            'billed_by' => Auth::id(),
                            'billed_date' => date('Y-m-d H:i:s'),
                            'service_request_id' => $bill_req->id,
                        ]);
                    }
                }

                DB::commit();

                return redirect()->back()->with(['message' => 'Misc. Bill Requests Billed Successfully', 'message_type' => 'success']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);

            return redirect()->back()->withInput()->withMessage('An error occurred '.$e->getMessage().'line'.$e->getLine());
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(MiscBill $miscBill)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(MiscBill $miscBill)
    {
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, MiscBill $miscBill)
    {
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(MiscBill $miscBill)
    {
    }
}
