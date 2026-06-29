<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Service;
use App\Models\HmoScheme;
use App\Models\HmoTariff;
use App\Models\Hmo;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class WorkbenchPriceListController extends Controller
{
    public function getProducts(Request $request)
    {
        if ($request->ajax()) {
            $products = Product::with(['category', 'price'])->where('status', 1)->select('products.*');

            if ($request->filled('category_id')) {
                $products->where('category_id', $request->category_id);
            }
            if ($request->filled('product_type')) {
                $products->where('product_type', $request->product_type);
            }

            return DataTables::of($products)
                ->addColumn('item_details', function ($product) {
                    $category = $product->category ? $product->category->category_name : 'Uncategorized';
                    $html = '<div>';
                    $html .= '<strong style="font-size: 1.05rem; color: #2c3e50;">' . $product->product_name . '</strong>';
                    $html .= '<div class="text-muted small mt-1"><i class="mdi mdi-tag"></i> ' . $category . '</div>';
                    $html .= '</div>';
                    return $html;
                })
                ->addColumn('base_pricing', function ($product) {
                    $price = $product->price;
                    $salePrice = $price ? number_format($price->current_sale_price, 2) : '0.00';
                    
                    $html = '<div>';
                    $html .= '<strong class="text-success" style="font-size: 1.1rem;">₦' . $salePrice . '</strong>';
                    $html .= '<div class="text-muted small mt-1">Base Price</div>';
                    $html .= '</div>';
                    return $html;
                })
                ->addColumn('tariff_action', function ($product) {
                    return '<button type="button" class="btn btn-sm btn-outline-primary view-tariffs-btn" data-id="' . $product->id . '" data-type="product"><i class="mdi mdi-chevron-down"></i> View Tariffs</button>';
                })
                ->rawColumns(['item_details', 'base_pricing', 'tariff_action'])
                ->make(true);
        }
        return abort(404);
    }

    public function getServices(Request $request)
    {
        if ($request->ajax()) {
            $services = Service::with(['category', 'price'])->where('status', 1)->select('services.*');

            if ($request->filled('category_id')) {
                $services->where('category_id', $request->category_id);
            }
            if ($request->filled('service_type')) {
                $type = $request->service_type;
                if ($type === 'lab') {
                    $services->where('category_id', appsettings('investigation_category_id', 2));
                } elseif ($type === 'imaging') {
                    $services->where('category_id', appsettings('imaging_category_id', 6));
                } elseif ($type === 'procedure') {
                    $services->has('procedureDefinition');
                } elseif ($type === 'combo') {
                    $services->where('is_combo', 1);
                }
            }

            return DataTables::of($services)
                ->addColumn('item_details', function ($service) {
                    $category = $service->category ? $service->category->category_name : 'Uncategorized';
                    $html = '<div>';
                    $html .= '<strong style="font-size: 1.05rem; color: #2c3e50;">' . $service->service_name . '</strong>';
                    $html .= '<div class="text-muted small mt-1"><i class="mdi mdi-tag"></i> ' . $category . '</div>';
                    $html .= '</div>';
                    return $html;
                })
                ->addColumn('base_pricing', function ($service) {
                    $price = $service->price;
                    $salePrice = $price ? number_format($price->sale_price, 2) : '0.00';
                    
                    $html = '<div>';
                    $html .= '<strong class="text-success" style="font-size: 1.1rem;">₦' . $salePrice . '</strong>';
                    $html .= '<div class="text-muted small mt-1">Base Price</div>';
                    $html .= '</div>';
                    return $html;
                })
                ->addColumn('tariff_action', function ($service) {
                    return '<button type="button" class="btn btn-sm btn-outline-primary view-tariffs-btn" data-id="' . $service->id . '" data-type="service"><i class="mdi mdi-chevron-down"></i> View Tariffs</button>';
                })
                ->rawColumns(['item_details', 'base_pricing', 'tariff_action'])
                ->make(true);
        }
        return abort(404);
    }

    public function getTariffs(Request $request)
    {
        $id = $request->id;
        $type = $request->type; // 'product' or 'service'

        $schemes = HmoScheme::with(['hmos' => function ($q) {
            $q->where('status', 1);
        }])->get();

        $query = HmoTariff::query();
        if ($type === 'product') {
            $query->where('product_id', $id)->whereNull('service_id');
        } else {
            $query->where('service_id', $id)->whereNull('product_id');
        }
        $tariffs = $query->get()->keyBy('hmo_id');

        $schemeSummary = [];
        foreach ($schemes as $scheme) {
            $activeHmos = $scheme->hmos;
            if ($activeHmos->isEmpty()) continue;

            $hmosData = [];
            foreach ($activeHmos as $hmo) {
                $tariff = $tariffs->get($hmo->id);
                $payable = $tariff ? (float) $tariff->payable_amount : 0;
                $claims = $tariff ? (float) $tariff->claims_amount : 0;

                $hmosData[] = [
                    'id' => $hmo->id,
                    'name' => $hmo->name,
                    'payable_amount' => $payable,
                    'claims_amount' => $claims,
                    'coverage_mode' => $tariff ? $tariff->coverage_mode : 'primary',
                    'has_tariff' => $tariff ? true : false,
                ];
            }

            $schemeSummary[] = [
                'id' => $scheme->id,
                'name' => $scheme->name,
                'hmos' => $hmosData,
            ];
        }

        $standaloneHmos = Hmo::where('status', 1)->whereNull('hmo_scheme_id')->get();
        $standaloneData = [];
        foreach ($standaloneHmos as $hmo) {
            $tariff = $tariffs->get($hmo->id);
            $standaloneData[] = [
                'id' => $hmo->id,
                'name' => $hmo->name,
                'payable_amount' => $tariff ? (float) $tariff->payable_amount : 0,
                'claims_amount' => $tariff ? (float) $tariff->claims_amount : 0,
                'coverage_mode' => $tariff ? $tariff->coverage_mode : 'primary',
                'has_tariff' => $tariff ? true : false,
            ];
        }

        return response()->json([
            'success' => true,
            'schemeSummary' => $schemeSummary,
            'standaloneData' => $standaloneData
        ]);
    }
}
