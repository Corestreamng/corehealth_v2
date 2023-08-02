<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Product, service, ProductOrServiceRequest};
use Illuminate\Support\Facades\Log;

class productAccountController extends Controller
{
    public function process(Request $request)

    {
        try {
            $inputs = $request->input('productChecked');
            $productQty = $request->input('productQty');
            $checkboxServices = session('selected');
            $serviceQty = session('serviceQty');

            if (count($inputs) > count($productQty)) {
                return redirect()->back()->withMessage("Please set a quantity for all selected entries");
            }
            if (isset($checkboxServices)) {
                $services = ProductOrServiceRequest::with('service.price')->whereIn('id', array_values($checkboxServices))->get();
                // $services = service::with('price')->whereIn('id',$requests)->get();
                $total = 0;
                for ($i = 0; $i < count($services); $i++) {
                    $total += $services[$i]->service->price->sale_price * $serviceQty[$i];
                }
            }
            $sumServices = $total;
            // dd($sumServices);
            if ($inputs == NULL) {
                return view('admin.Accounts.summary', compact('services', 'sumServices', 'serviceQty'));
            } else {
                // dd($inputs);
                session(['products' => $inputs, 'productQty' => $productQty]);
                $checkboxProducts = session('products');
                $productQty = session('productQty');
                // dd($checkboxProducts);
                $products = ProductOrServiceRequest::with('product.price')->whereIn('id', array_values($checkboxProducts))->get();
                // dd($products);
                // $products = Product::with('price')->whereIn('id',$productRequests)->get();
                $productsTotal = 0;
                for ($j = 0; $j < count($products); $j++) {
                    $productsTotal += $products[$j]->product->price->current_sale_price * $productQty[$j];
                }
                $sumProducts = $productsTotal;
                // dd($sumProducts);
                // dd($sumServices);
                return view('admin.Accounts.summary', compact('products', 'services', 'sumServices', 'sumProducts', 'productQty', 'serviceQty'));
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
