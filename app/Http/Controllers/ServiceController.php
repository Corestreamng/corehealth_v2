<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;

use App\Models\Sale;
use App\Models\ApplicationStatu;
use App\Models\Stock;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    public function listServices()
    {
        $pc = Service::where('status', '=', 1)->with('category')->orderBy('service_name', 'ASC')->get();

        return Datatables::of($pc)
            ->addIndexColumn()
            ->addColumn('service_code', function ($pc) {
                $service_code = '<span class="badge badge-pill badge-dark">' . $pc->service_code . '</sapn>';
                return $service_code;
            })
            ->addColumn('category_id', function ($pc) {
                $category_name = '<span class="badge badge-pill badge-dark">' . $pc->category->category_code . '</sapn>';
                return $category_name;
            })
            ->addColumn('visible', function ($pc) {

                $active = '<span class="badge badge-pill badge-success">Active</sapn>';
                $inactive = '<span class="badge badge-pill badge-dark">Inactive</sapn>';

                return (($pc->status == 0) ? $inactive : $active);
            })
            ->addColumn('adjust', function ($pc) {

                if (Auth::user()->hasPermissionTo('can-manage-products') || Auth::user()->hasRole(['ADMIN', 'STORE'])) {
                    # code...
                    $url = route('service-prices.edit', $pc->id);
                    return '<a href="' . $url . '" class="btn btn-secondary btn-sm"><i class="fa fa-info-circle"></i> Add/Adjust</a>';
                } else {
                    # code...
                    $label = '<button disabled class="btn btn-secondary btn-sm"> <i class="fa fa-info-circle"></i> Add/Adjust</button>';
                    return $label;
                }
            })
            ->addColumn('trans', function ($pc) {

                if (Auth::user()->hasPermissionTo('can-manage-products') || Auth::user()->hasRole(['ADMIN', 'STORE'])) {
                    # code...
                    $url = route('services.show', $pc->id);
                    return '<a href="' . $url . '" class="btn btn-info btn-sm"><i class="fa fa-map-pin"></i> View</a>';
                } else {
                    # code...
                    $label = '<button disabled class="btn btn-info btn-sm"> <i class="fa fa-map-pin"></i> View</button>';
                    return $label;
                }
            })
            ->addColumn('edit', function ($pc) {

                if (Auth::user()->hasPermissionTo('can-manage-products') || Auth::user()->hasRole(['ADMIN', 'STORE'])) {

                    $url = route('services.edit', $pc->id);
                    return '<a href="' . $url . '" class="btn btn-secondary btn-sm"><i class="fa fa-i-cursor"></i> Edit</a>';
                } else {

                    $label = '<button disabled class="btn btn-secondary btn-sm"> <i class="fa fa-i-cursor"></i> Edit</button>';
                    return $label;
                }
            })
            ->rawColumns(['service_code', 'category_id', 'visible', 'edit', 'adjust', 'trans'])
            ->make(true);
    }

    public function listSalesService(Request $request, $id)
    {

        $pc = Sale::where('service_id', '=', $id)->with('product_or_service_request', 'product', 'store')->orderBy('id', 'DESC')->get();

        return Datatables::of($pc)
            ->addIndexColumn()
            ->addColumn('view', function ($pc) {
                // return '<a href="' . route('transactions.show', $pc->transaction->id) . '" class="btn btn-dark btn-sm"><i class="fa fa-eye"></i> SIV</a>';
                return 'todo';
            })
            ->editColumn('product', function ($pc) {
                return ($pc->product->service_name);
            })
            ->editColumn('store', function ($pc) {
                return ($pc->store->store_name);
            })
            ->editColumn('trans', function ($pc) {
                return ($pc->product_or_service_request->invoice->id);
            })
            ->editColumn('customer', function ($pc) {
                // return ($pc->transaction->customer_name);
                return 'todo';
            })
            ->editColumn('budgetYear', function ($pc) {
                // $budgetYear = getBudgetYearName($pc->budget_year_id);

                return 'todo';
            })

            ->rawColumns(['view', 'product', 'store', 'trans', 'customer', 'budgetYear'])

            ->make(true);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.service.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $category       = ServiceCategory::where('status', '=', 1)->pluck('category_name', 'id')->all();
        return view('admin.service.create', compact('category'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request)
    {

        $rules = [
            'category_id'          => 'required',
            'service_name'          => 'required',
            'service_code'          => 'required',
        ];

        try {
            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                // Alert::error('Error Title', 'One or more information is needed.');
                // return redirect()->back()->with('errors', $v->messages()->all())->withInput();
                // return redirect()->back()->with('toast_error', $v->messages()->all()[0])->withInput();
                return redirect()->back()->with('errors', $v->messages()->all())->withInput();
            } else {

                $myservice                      = new Service();
                $myservice->user_id             = Auth::user()->id;
                $myservice->category_id         = $request->category_id;
                $myservice->service_name        = trim($request->service_name);
                $myservice->service_code        = $request->service_code;
                $myservice->status              = 1;

                if ($myservice->save()) {
                    $msg = 'The Service  ' . $request->service_name . ' was Saved Successfully.';
                    return redirect(route('services.index'))->withMessage($msg)->withMessageType('success');
                } else {
                    $msg = 'Something is went wrong. Please try again later, Service not Saved.';
                    //flash($msg, 'danger');
                    return redirect()->back()->withMessage($msg)->withMessageType('danger')->withInput();
                }
            }
        } catch (\Exception $e) {

            return redirect()->back()->withMessage("An error occurred " . $e->getMessage());
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
        $pc = Sale::where('service_id', '=', $id)->with('transaction', 'product', 'store')->sum('total_amount');
        $qt = Sale::where('service_id', '=', $id)->with('transaction', 'product', 'store')->sum('quantity_buy');
        $pp = Service::find($id);

        return view('admin.service.product', compact('id', 'pp', 'pc', 'qt'));
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
            $product = Service::whereId($id)->first();
            $category       = ServiceCategory::where('status', '=', 1)->pluck('category_name', 'id')->all();
            return view('admin.service.edit', compact('product', 'category'));
        } catch (\Exception $e) {

            return redirect()->back()->withMessage("An error occurred " . $e->getMessage());
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
                'category_id'          => 'required',
                'service_name'          => 'required',
                'service_code'          => 'required',
            ];

            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                //  $msg = 'Please cheak Your Inputs .';
                return redirect()->back()->with('errors', $v->messages()->all())->withInput();
            } else {

                $myservice                 = Service::whereId($id)->first();
                $myservice->user_id        = Auth::user()->id;
                $myservice->category_id    = $request->category_id;
                $myservice->service_name   = $request->service_name;
                $myservice->service_code   = $request->service_code;
                $myservice->status            = 1;

                if ($myservice->update()) {
                    $msg = 'The Service ' . $request->service_name . ' Was Updated Successfully.';
                    return redirect(route('services.index'))->withMessage($msg)->withMessageType('success');
                } else {
                    $msg = 'Something is went wrong. Please try again later, information not save.';

                    return redirect()->back()->withMessage($msg)->withMessageType('success')->withInput();
                }
            }
        } catch (\Exception $e) {

            return redirect()->back()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
