<?php

namespace App\Http\Controllers;

use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Requests\StoreServiceCategoryRequest;
use App\Http\Requests\UpdateServiceCategoryRequest;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Auth;

class ServiceCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function listServiceCategories()
    {

        $productCat = ServiceCategory::where('status', '>', 0)->orderBy('id', 'ASC')->get();


        return Datatables::of($productCat)
            ->addIndexColumn()
            ->addColumn('category_code', function ($productCat) {
                $category_code = '<span class="badge badge-pill badge-dark">' . $productCat->category_code . '</sapn>';
                return $category_code;
            })
            ->editColumn('status', function ($productCat) {

                $active = '<span class="badge badge-pill badge-success">Active</sapn>';
                $inactive = '<span class="badge badge-pill badge-dark">Inactive</sapn>';
                return (($productCat->status == 1) ? $inactive : $active);
            })
            ->addColumn('view', function ($productCat) {

                if (Auth::user()->hasPermissionTo('can-manage-service-categories') || Auth::user()->hasRole(['ADMIN','STORE'])) {

                    $url =  route('services-category.show', $productCat->id);
                    return '<a href="' . $url . '" class="btn btn-success btn-sm" ><i class="fa fa-street-view"></i> View</a>';
                } else {

                    $label = '<span class="label label-warning">Not Allowed</span>';
                    return $label;
                }
            })
            ->addColumn('edit', function ($productCat) {

                if (Auth::user()->hasPermissionTo('can-manage-service-categories') || Auth::user()->hasRole(['ADMIN','STORE'])) {

                    $url =  route('services-category.edit', $productCat->id);
                    return '<a href="' . $url . '" class="btn btn-info btn-sm" ><i class="fa fa-pencil"></i> Edit</a>';
                } else {

                    $label = '<span class="label label-warning">Not Allow</span>';
                    return $label;
                }
            })
            // ->addColumn('delete', function ($productCat) {

            //     if (Auth::user()->hasPermissionTo('user-delete') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {
            //         $id = $productCat->id;
            //         return '<button type="button" class="delete-modal btn btn-danger btn-sm" data-toggle="modal" data-id="' . $id . '" data-target="#deleteModal"><i class="fa fa-trash"></i> Delete</button>';
            //     } else {
            //         $label = '<span class="label label-danger">Not Allow</span>';
            //         return $label;
            //     }
            // })
            ->rawColumns(['category_code', 'status', 'view', 'edit'])
            ->make(true);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.serviceCategory.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.serviceCategory.create');
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
            'category_name'        => 'required',
            'category_code'        => 'required',
            'category_description' => 'required'
        ];

        $v = validator()->make($request->all(), $rules);

        if ($v->fails()) {

            return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
        } else {

            $category                       = new ServiceCategory();
            $category->category_name        = $request->category_name;
            $category->category_code        = $request->category_code;
            $category->category_description = $request->category_description;

            if ($category->save()) {
                $msg = 'The ServiceCategory ' . $request->category_name . ' was saved successfully.';
                return redirect(route('services-category.index'))->withMessage($msg)->withMessageType('success');
            } else {

                $msg = 'Something is went wrong. But it seems it is not your input contact the system administrator';
                return redirect()->back()->withInput()->withMessage($msg)->withMessageType('danger');
            }
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
        // $productCat = ServiceCategory::whereId($id)->with(['products'])->first();
        // $options  = Status::whereVisible(1)->pluck('name', 'id')->all();

        $reqCat = ServiceCategory::where('id', '=', $id)
            ->with(['services'])->get();
        // dd($reqCat[0]->products);
        return view('admin.serviceCategory.show', compact('reqCat'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {

        $productCat = ServiceCategory::whereId($id)->first();

        return view('admin.serviceCategory.edit', compact('productCat'));
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
        $rules = [
            'category_name'        => 'required',
            'category_code'        => 'required',
            'category_description' => 'required',
        ];

        $v = validator()->make($request->all(), $rules);

        if ($v->fails()) {

            return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
        } else {

            $category                       = ServiceCategory::find($id);
            $category->category_name        = $request->category_name;
            $category->category_code        = $request->category_code;
            $category->category_description = $request->category_description;

            if ($category->save()) {
                $msg = 'The ServiceCategory ' . $request->category_name . ' was updated successfully.';
                return redirect(route('services-category.index'))->withMessage($msg)->withMessageType('success');
            } else {

                $msg = 'Something is went wrong. But it seems it is not your input contact the system administrator';
                return redirect()->back()->withInput()->withMessage($msg)->withMessageType('danger')->withInput();
            }
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
        $productCat = ServiceCategory::findOrFail($id);
        $productCat->delete();

        return response()->json($productCat);
    }}
