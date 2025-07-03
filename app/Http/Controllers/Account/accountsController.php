<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{service, detail, ProductOrServiceRequest};
use Yajra\DataTables\DataTables;

class accountsController extends Controller
{
    public function index($identifier)
    {
        $id = $identifier;
        return view('admin.Accounts.services', compact('id'));
    }
    public function products($id)
    {
        $products = ProductOrServiceRequest::with(['product.price', 'product.category', 'staff', 'user'])
            ->where('service_id', NULL)
            ->where('user_id', $id)
            ->where('invoice_id', NULL)
            ->get();

        return DataTables::of($products)
            ->addIndexColumn()
            ->addColumn('checkBox', function ($product) {
                return '<input type="checkbox" value="' . $product->id . '" name="productChecked[]" />';
            })
            ->editColumn('qty', function ($pr) {
                return '<input type="number" name="productQty[]" value="' . $pr->qty . '" class="form-control form-control-sm" required>';
            })
            ->addColumn('product.category.category_name', function ($product) {
                return $product->product && $product->product->category ? $product->product->category->category_name : '';
            })
            ->addColumn('created_at', function ($product) {
                return $product->created_at ? $product->created_at->format('Y-m-d H:i') : '';
            })
            ->addColumn('staff_name', function ($product) {
                return $product->staff ? userfullname($product->staff_user_id) : '';
            })
            ->addColumn('patient_name', function ($product) {
                return $product->user ? userfullname($product->user_id) : '';
            })
            ->rawColumns(['checkBox', 'qty'])
            ->make(true);
    }
    public function services($id)
    {

        $identify = $id;
        $services = ProductOrServiceRequest::with(['service.price', 'service.category', 'staff', 'user'])
            ->where('product_id', NULL)
            ->where('user_id', $identify)
            ->where('invoice_id', NULL)
            ->get();

        return DataTables::of($services)
            ->addIndexColumn()
            ->addColumn('checkBox', function ($service) {
                return '<input type="checkbox" value="' . $service->id . '" name="someCheckbox[]" />';
            })
            ->editColumn('qty', function ($sr) {
                return '<input type="number" name="serviceQty[]" value="' . $sr->qty . '" class="form-control form-control-sm" required>';
            })
            ->addColumn('service.category.category_name', function ($service) {
                return $service->service && $service->service->category ? $service->service->category->category_name : '';
            })
            ->addColumn('created_at', function ($service) {
                return $service->created_at ? $service->created_at->format('Y-m-d H:i') : '';
            })
            ->addColumn('staff_name', function ($service) {
                return $service->staff ? userfullname($service->staff_user_id) : '';
            })
            ->addColumn('patient_name', function ($service) {
                return $service->user ? userfullname($service->user_id) : '';
            })
            ->rawColumns(['checkBox', 'qty'])
            ->make(true);
    }
    public function serviceView($id)
    {
        return view('admin.Accounts.settledServices', compact('id'));
    }
    public function productView($id)
    {

        return view('admin.Accounts.settledProducts', compact('id'));
    }



    public function settledServices($id)
    {
        $identify = $id;

        $services = ProductOrServiceRequest::with('service.price')->where('product_id', NULL)->where('user_id', $identify)->where('invoice_id', !NULL)->get();

        return DataTables::of($services)
            ->addIndexColumn()
            ->addColumn('checkBox', function ($service) {

                return '<input type="checkbox" value="' . $service->id . '" name="someCheckbox[]" />';
            })
            ->rawColumns(['checkBox'])
            ->make(true);
    }
    public function settledProducts($id)
    {
        $products = ProductOrServiceRequest::with('product.price')->where('service_id', NULL)->where('user_id', $id)->where('invoice_id', !NULL)->get();;
        return DataTables::of($products)
            ->addIndexColumn()
            ->addColumn('checkBox', function ($product) {

                return '<input type="checkbox" value="' . $product->id . '" name="productChecked[]" />';
            })
            ->rawColumns(['checkBox'])
            ->make(true);
    }
    public function mergedList($id)
    {
        // Services
        $services = ProductOrServiceRequest::with(['service.price', 'service.category', 'staff', 'user'])
            ->where('product_id', NULL)
            ->where('user_id', $id)
            ->where('invoice_id', NULL)
            ->get()
            ->map(function ($item) {
                $cat = $item->service && $item->service->category ? $item->service->category->category_name : '';
                $type = '<br><span class="badge badge-info">Service</span>';
                return [
                    'id' => $item->id,
                    'checkBox' => '<input type="checkbox" value="' . $item->id . '" name="mergedChecked[]" />',
                    'name' => $item->service ? $item->service->service_name : '',
                    'cat_type' => $cat . ' ' . $type,
                    'price' => $item->service && $item->service->price ? $item->service->price->sale_price : '',
                    'qty' => '<input type="number" name="serviceQty[]" value="' . $item->qty . '" class="form-control form-control-sm" style="min-width:48px;max-width:70px;padding:2px 4px;text-align:center;" required>',
                    'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i') : '',
                    'staff_name' => $item->staff ? userfullname($item->staff_user_id) : '',
                    'patient_name' => $item->user ? userfullname($item->user_id) : '',
                ];
            });

        // Products
        $products = ProductOrServiceRequest::with(['product.price', 'product.category', 'staff', 'user'])
            ->where('service_id', NULL)
            ->where('user_id', $id)
            ->where('invoice_id', NULL)
            ->get()
            ->map(function ($item) {
                $cat = $item->product && $item->product->category ? $item->product->category->category_name : '';
                $type = '<br><span class="badge badge-success">Product</span>';
                return [
                    'id' => $item->id,
                    'checkBox' => '<input type="checkbox" value="' . $item->id . '" name="mergedChecked[]" />',
                    'name' => $item->product ? $item->product->product_name : '',
                    'cat_type' => $cat . ' ' . $type,
                    'price' => $item->product && $item->product->price ? $item->product->price->current_sale_price : '',
                    'qty' => '<input type="number" name="productQty[]" value="' . $item->qty . '" class="form-control form-control-sm" style="min-width:48px;max-width:70px;padding:2px 4px;text-align:center;" required>',
                    'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i') : '',
                    'staff_name' => $item->staff ? userfullname($item->staff_user_id) : '',
                    'patient_name' => $item->user ? userfullname($item->user_id) : '',
                ];
            });

        $merged = $services->concat($products)->values();

        return DataTables::of($merged)
            ->rawColumns(['cat_type', 'qty', 'checkBox'])
            ->make(true);
    }
}
