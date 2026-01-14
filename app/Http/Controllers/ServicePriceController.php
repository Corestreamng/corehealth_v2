<?php

namespace App\Http\Controllers;

use App\Models\servicePrice;
use Illuminate\Http\Request;
use App\Models\service;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use App\Models\ApplicationStatu;
use Illuminate\Support\Facades\Auth;

class ServicePriceController extends Controller
{
    public function listServicePrice()
    {
        $pc = ServicePrice::where('status', '=', 1)->with('service')->get();

        return Datatables::of($pc)
            ->addIndexColumn()
            ->editColumn('service', function ($pc) {
                return ($pc->service->service_name);
            })
            ->rawColumns(['service'])

            ->make(true);
    }

    public function index()
    {
        try {
            $service_id = request()->get('service_id');
            $service     = service::find($service_id);
            $application = ApplicationStatu::whereId(1)->first();
            return view('admin.service_prices.create', compact('service', 'application'));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.service_prices.pricelist');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $now = \Carbon\Carbon::now();
        try {
            $rules = [
                'service_id' => 'required|max:100',
                'price'    => 'required|max:11',
                'buy_price' => 'required'
            ];

            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                $msg = 'Please cheak Your Inputs .';
                //flash($msg, 'danger');
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            } else {
                $myprice                 = new ServicePrice();
                $myprice->service_id     = $request->service_id;
                $myprice->cost_price     = $request->buy_price;
                $myprice->sale_price     = $request->price;
                $myprice->max_discount   = $request->max_discount ?? 0;
                $myprice->status         = 1;

                if ($myprice->save()) {
                    $assing_price = service::find($request->service_id);
                    $assing_price->price_assign = 1;
                    $assing_price->update();
                    $msg = 'price for ' . $assing_price->service_name . ' was saved successfully.';
                    // flash($msg, 'success');
                    return redirect(route('services.index'))->withMessage($msg)->withMessageType('success')->with($msg);
                } else {
                    $msg = 'Something is went wrong. Please try again later, information not save.';
                    //flash($msg, 'danger');
                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('danger');
                }
            }
        } catch (\Exception $e) {

            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $service     = service::whereId($id)->first();
            return view('admin.service_prices.newprice', compact('service'));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        try {
            $data = ServicePrice::with('service')->whereService_id($id)->first();
            if (empty($data)) {
                return redirect(route('service-prices.index', ['service_id' => $id]));
            } else {
                // dd($data);
                return view('admin.service_prices.edit', compact('data'));
            }
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $rules = [
                'cost_price' => 'required|max:11',
                'price'    => 'required|max:11'
            ];

            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                $msg = 'Please cheak Your Inputs .';
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            } else {
                $myprice               =  ServicePrice::find($id);
                $myprice->cost_price   = $request->cost_price;
                $myprice->sale_price   = $request->price;
                $myprice->max_discount = $request->max_discount ?? 0;
                $myprice->status       = 1;

                if ($myprice->update()) {
                    $msg = "Price for [".$myprice->service->service_name."] was updated successfully";
                    return redirect(route('services.index'))->withMessage($msg)->withMessageType('success')->with($msg);
                } else {
                    $msg = 'Something is went wrong. Please try again later, information not save.';
                    return redirect()->back()->withInput()->withInput();
                }
            }
        } catch (\Exception $e) {

            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function priceslist()
    {
        //
    }
    public function destroy($id)
    {
        //
    }
}
