<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\StockBatch;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers
     */
    public function index()
    {
        return view('admin.suppliers.index');
    }

    /**
     * Get suppliers list for DataTable
     */
    public function listSuppliers(Request $request)
    {
        $suppliers = Supplier::with('creator')
            ->withCount(['stockBatches', 'purchaseOrders'])
            ->orderBy('company_name', 'ASC');

        return DataTables::of($suppliers)
            ->addIndexColumn()
            ->addColumn('status_badge', function ($supplier) {
                return $supplier->status
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>';
            })
            ->addColumn('batches_count', fn($s) => $s->stock_batches_count)
            ->addColumn('po_count', fn($s) => $s->purchase_orders_count)
            ->addColumn('outstanding', function ($supplier) {
                $balance = $supplier->outstanding_balance;
                $class = $balance > 0 ? 'text-danger' : ($balance < 0 ? 'text-success' : '');
                return '<span class="' . $class . '">₦' . number_format(abs($balance), 2) . '</span>';
            })
            ->addColumn('last_activity', function ($supplier) {
                return $supplier->last_activity?->format('M d, Y') ?? '-';
            })
            ->addColumn('actions', function ($supplier) {
                $btns = '<div class="btn-group btn-group-sm">';
                $btns .= '<a href="' . route('suppliers.show', $supplier->id) . '" class="btn btn-info" title="View"><i class="fa fa-eye"></i></a>';
                $btns .= '<a href="' . route('suppliers.edit', $supplier->id) . '" class="btn btn-primary" title="Edit"><i class="fa fa-edit"></i></a>';
                $btns .= '<button onclick="deleteSupplier(' . $supplier->id . ')" class="btn btn-danger" title="Delete"><i class="fa fa-trash"></i></button>';
                $btns .= '</div>';
                return $btns;
            })
            ->rawColumns(['status_badge', 'outstanding', 'actions'])
            ->make(true);
    }

    /**
     * Show the form for creating a new supplier
     */
    public function create()
    {
        return view('admin.suppliers.create');
    }

    /**
     * Store a newly created supplier
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255|unique:suppliers,company_name',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'alt_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'tax_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
            'payment_terms' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'status' => 'nullable|boolean',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['status'] = $request->has('status') ? true : true; // Default to active

        $supplier = Supplier::create($validated);

        Alert::success('Success', 'Supplier created successfully');
        return redirect()->route('suppliers.index');
    }

    /**
     * Display the specified supplier with details
     */
    public function show(Supplier $supplier)
    {
        $supplier->load(['creator', 'stockBatches.product', 'stockBatches.store', 'purchaseOrders']);

        // Get supplier statistics
        $stats = [
            'total_batches' => $supplier->stockBatches->count(),
            'active_batches' => $supplier->stockBatches->where('current_qty', '>', 0)->where('is_active', true)->count(),
            'total_supplied_value' => $supplier->stockBatches->sum(fn($b) => $b->initial_qty * $b->cost_price),
            'total_po_count' => $supplier->purchaseOrders->count(),
            'pending_po_count' => $supplier->purchaseOrders->whereIn('status', ['draft', 'pending', 'approved'])->count(),
        ];

        // Recent batches (last 10)
        $recentBatches = $supplier->stockBatches()
            ->with(['product', 'store'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Recent POs
        $recentPOs = $supplier->purchaseOrders()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.suppliers.show', compact('supplier', 'stats', 'recentBatches', 'recentPOs'));
    }

    /**
     * Show the form for editing the specified supplier
     */
    public function edit(Supplier $supplier)
    {
        return view('admin.suppliers.edit', compact('supplier'));
    }

    /**
     * Update the specified supplier
     */
    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255|unique:suppliers,company_name,' . $supplier->id,
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'alt_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'tax_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
            'payment_terms' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'status' => 'nullable|boolean',
        ]);

        $validated['status'] = $request->has('status');

        $supplier->update($validated);

        Alert::success('Success', 'Supplier updated successfully');
        return redirect()->route('suppliers.index');
    }

    /**
     * Remove the specified supplier (soft delete)
     */
    public function destroy(Supplier $supplier)
    {
        // Check if supplier has batches
        if ($supplier->stockBatches()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete supplier with existing stock batches. Deactivate instead.'
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Supplier deleted successfully'
        ]);
    }

    /**
     * AJAX search for suppliers (for Select2)
     */
    public function search(Request $request)
    {
        $term = $request->get('q', '');

        $suppliers = Supplier::active()
            ->search($term)
            ->select('id', 'company_name', 'contact_person', 'phone')
            ->limit(20)
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'text' => $s->company_name . ($s->contact_person ? " ({$s->contact_person})" : ''),
                'phone' => $s->phone,
            ]);

        return response()->json($suppliers);
    }

    // ===== REPORTS =====

    /**
     * Supplier reports dashboard
     */
    public function reports(Request $request)
    {
        return view('admin.suppliers.reports.index');
    }

    /**
     * Get supplier performance report data
     */
    public function performanceReport(Request $request)
    {
        $startDate = $request->get('start_date', now()->subMonths(3)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $suppliers = Supplier::active()
            ->withCount(['stockBatches as total_batches' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            }])
            ->with(['stockBatches' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            }])
            ->get()
            ->map(function ($supplier) {
                $batches = $supplier->stockBatches;
                return [
                    'id' => $supplier->id,
                    'company_name' => $supplier->company_name,
                    'total_batches' => $batches->count(),
                    'total_items' => $batches->sum('initial_qty'),
                    'total_value' => $batches->sum(fn($b) => $b->initial_qty * $b->cost_price),
                    'avg_cost' => $batches->count() > 0 ? $batches->avg('cost_price') : 0,
                    'products_supplied' => $batches->pluck('product_id')->unique()->count(),
                ];
            })
            ->sortByDesc('total_value')
            ->values();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $suppliers,
                'period' => ['start' => $startDate, 'end' => $endDate],
            ]);
        }

        return view('admin.suppliers.reports.performance', compact('suppliers', 'startDate', 'endDate'));
    }

    /**
     * Get supplier batches report
     */
    public function batchesReport(Request $request)
    {
        $supplierId = $request->get('supplier_id');
        $startDate = $request->get('start_date', now()->subMonths(1)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $query = StockBatch::with(['product', 'store', 'supplier'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        } else {
            $query->whereNotNull('supplier_id');
        }

        $batches = $query->orderBy('created_at', 'desc')->get();

        $summary = [
            'total_batches' => $batches->count(),
            'total_items' => $batches->sum('initial_qty'),
            'total_value' => $batches->sum(fn($b) => $b->initial_qty * $b->cost_price),
            'suppliers_count' => $batches->pluck('supplier_id')->unique()->count(),
        ];

        $suppliers = Supplier::active()->orderBy('company_name')->get();

        if ($request->ajax()) {
            return DataTables::of($batches)
                ->addColumn('product_name', fn($b) => $b->product->product_name ?? '-')
                ->addColumn('store_name', fn($b) => $b->store->store_name ?? '-')
                ->addColumn('supplier_name', fn($b) => $b->supplier->company_name ?? '-')
                ->addColumn('total_value', fn($b) => '₦' . number_format($b->initial_qty * $b->cost_price, 2))
                ->make(true);
        }

        return view('admin.suppliers.reports.batches', compact('batches', 'summary', 'suppliers', 'supplierId', 'startDate', 'endDate'));
    }

    /**
     * Export suppliers to CSV
     */
    public function export(Request $request)
    {
        $suppliers = Supplier::with('creator')
            ->withCount(['stockBatches', 'purchaseOrders'])
            ->get();

        $filename = 'suppliers_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($suppliers) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'ID', 'Company Name', 'Contact Person', 'Email', 'Phone',
                'Address', 'Tax Number', 'Bank', 'Account Number',
                'Payment Terms', 'Credit Limit', 'Total Batches', 'Status', 'Created At'
            ]);

            foreach ($suppliers as $supplier) {
                fputcsv($file, [
                    $supplier->id,
                    $supplier->company_name,
                    $supplier->contact_person,
                    $supplier->email,
                    $supplier->phone,
                    $supplier->address,
                    $supplier->tax_number,
                    $supplier->bank_name,
                    $supplier->bank_account_number,
                    $supplier->payment_terms,
                    $supplier->credit_limit,
                    $supplier->stock_batches_count,
                    $supplier->status ? 'Active' : 'Inactive',
                    $supplier->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
