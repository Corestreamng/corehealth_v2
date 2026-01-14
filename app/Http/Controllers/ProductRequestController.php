<?php

namespace App\Http\Controllers;

use App\Helpers\HmoHelper;

use App\Models\ProductRequest;
use App\Models\AdmissionRequest;
use App\Models\Clinic;
use App\Models\Encounter;
use App\Models\DoctorQueue;
use Yajra\DataTables\DataTables;
use App\Models\User;
use App\Models\Staff;
use App\Models\Hmo;
use App\Models\LabServiceRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\patient;
use App\Models\Product;
use App\Models\ProductOrServiceRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (request('history') == 1) {
            return view('admin.product_requests.history');
        } else {
            return view('admin.product_requests.index');
        }
    }

    public function prescQueueList(Request $request)
    {
        // Initialize the query with relationships and basic filters
        $query = ProductRequest::with(['product', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->whereIn('status', [1, 2]) // Filter by status 1 or 2
            ->orderBy('created_at', 'DESC');

        // Apply date range filter if both start_date and end_date are provided
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ]);
        }

        // Get the filtered data
        $his = $query->get();

        // Return data to DataTable
        return Datatables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                if ($h->patient) {
                    $url = route('patient.show', [$h->patient->id, 'section' => 'prescriptionsNotesCardBody']);
                    $str = "
                    <a class='btn btn-primary' href='$url'>
                        view
                    </a>";
                    return $str;
                } else {
                    return "N/A";
                }
            })
            ->editColumn('patient_id', function ($h) {
                $str = "<small>";
                $str .= "<b>Patient</b>: " . (($h->patient) ? userfullname($h->patient->user_id) : "N/A");
                $str .= "<br><br><b>File No</b>: " . (($h->patient) ? $h->patient->file_no : "N/A");
                $str .= "<br><br><b>Insurance/HMO</b>: " . (($h->patient && $h->patient->hmo) ? $h->patient->hmo->name : "N/A");
                $str .= "<br><br><b>HMO Number</b>: " . (($h->patient && $h->patient->hmo) ? $h->patient->hmo_no : "N/A");
                $str .= "</small>";
                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = "<small>";
                $str .= "<b>Requested By</b>: " . ((isset($h->doctor_id) && $h->doctor_id != null) ? (userfullname($h->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>") . '<br>';
                $str .= "<b>Last Updated On</b>: " . date('h:i a D M j, Y', strtotime($h->updated_at)) . '<br>';
                $str .= "<b>Billed By</b>: " . ((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span><br>");
                $str .= "<br><b>Dispensed By</b>: " . ((isset($h->dispensed_by) && $h->dispensed_by != null) ? (userfullname($h->dispensed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->dispense_date)) . ')') : "<span class='badge badge-secondary'>Not dispensed</span><br>");
                $str .= "</small>";
                return $str;
            })
            ->editColumn('dose', function ($his) {
                $str = "<span class='badge badge-success'>[" . (($his->product->product_code) ? $his->product->product_code : '') . "]" . $his->product->product_name . "</span>";
                $str .= "<hr> <b>Dose/Freq:</b> " . ($his->dose ?? 'N/A');
                return $str;
            })
            ->rawColumns(['created_at', 'dose', 'select', 'patient_id'])
            ->make(true);
    }

    public function prescQueueHistoryList()
    {
        // Get the start_date and end_date from the request
        $startDate = request('start_date') ? date('Y-m-d 00:00:00', strtotime(request('start_date'))) : null;
        $endDate = request('end_date') ? date('Y-m-d 23:59:59', strtotime(request('end_date'))) : null;

        // Build the query
        $his = ProductRequest::with(['product', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', 3);

        // Apply date range filter if both dates are provided
        if ($startDate && $endDate) {
            $his = $his->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Order by latest created_at
        $his = $his->orderBy('created_at', 'DESC')->get();

        return Datatables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $url = route('patient.show', [$h->patient->id, 'section' => 'prescriptionsNotesCardBody']);
                $str = "
                <a class='btn btn-primary' href='$url'>
                    view
                </a>";
                return $str;
            })
            ->editColumn('patient_id', function ($h) {
                $str = "<small>";
                $str .= "<b >Patient </b> :" . (($h->patient->user) ? userfullname($h->patient->user->id) : "N/A");
                $str .= "<br><br><b >File No </b> : " . (($h->patient) ? $h->patient->file_no : "N/A");
                $str .= "<br><br><b >Insurance/HMO :</b> : " . (($h->patient->hmo) ? $h->patient->hmo->name : "N/A");
                $str .= "<br><br><b >HMO Number :</b> : " . (($h->patient->hmo_no) ? $h->patient->hmo_no : "N/A");
                $str .= "</small>";
                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = "<small>";
                $str .= "<b >Requested By: </b>" . ((isset($h->doctor_id) && $h->doctor_id != null) ? (userfullname($h->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>") . '<br>';
                $str .= "<b >Last Updated On: </b>" . date('h:i a D M j, Y', strtotime($h->updated_at)) . '<br>';
                $str .= "<b >Billed By: </b>" . ((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span><br>");
                $str .= "<br><b >Dispensed By: </b>" . ((isset($h->dispensed_by) && $h->dispensed_by != null) ? (userfullname($h->dispensed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->dispense_date)) . ')') : "<span class='badge badge-secondary'>Not dispensed</span><br>");
                $str .= "</small>";
                return $str;
            })
            ->editColumn('dose', function ($his) {
                $str = "<span class = 'badge badge-success'>[" . (($his->product->product_code) ? $his->product->product_code : '') . "]" . $his->product->product_name . "</span>";
                $str .= "<hr> <b>Dose/Freq:</b> " . ($his->dose ?? 'N/A');
                return $str;
            })
            ->rawColumns(['created_at', 'dose', 'select', 'patient_id'])
            ->make(true);
    }


    /**
     * dispense selected roduct requets
     */

    public function bill(Request $request)
    {
        try {
            $request->validate([
                'consult_presc_dose' => 'nullable|array|required_with:addedPrescBillRows',
                'addedPrescBillRows' => 'nullable|array|required_with:consult_presc_dose',
                'selectedPrescBillRows' => 'array',
                'patient_user_id' => 'required',
                'patient_id' => 'required'
            ]);

            if (isset($request->dismiss_presc_bill) && isset($request->selectedPrescBillRows)) {
                DB::beginTransaction();
                for ($i = 0; $i < count($request->selectedPrescBillRows); $i++) {
                    ProductRequest::where('id', $request->selectedPrescBillRows[$i])->update([
                        'status' => 0
                    ]);
                }
                DB::commit();
                return redirect()->back()->with(['message' => "Product Requests Dismissed Successfully", 'message_type' => 'success']);
            } else {
                DB::beginTransaction();
                if (isset($request->selectedPrescBillRows)) {
                    for ($i = 0; $i < count($request->selectedPrescBillRows); $i++) {
                        $prod_req = ProductRequest::where('id', $request->selectedPrescBillRows[$i])->first();
                        $prod_id = $prod_req->product->id;
                        $bill_req = new ProductOrServiceRequest;
                        $bill_req->user_id = $request->patient_user_id;
                        $bill_req->staff_user_id = Auth::id();
                        $bill_req->product_id = $prod_id;

                        // Apply HMO tariff if patient has HMO
                        try {
                            $patient = patient::where('user_id', $request->patient_user_id)->first();
                            if ($patient) {
                                $hmoData = HmoHelper::applyHmoTariff($patient->id, $prod_id, null);
                                if ($hmoData) {
                                    $bill_req->payable_amount = $hmoData['payable_amount'];
                                    $bill_req->claims_amount = $hmoData['claims_amount'];
                                    $bill_req->coverage_mode = $hmoData['coverage_mode'];
                                    $bill_req->validation_status = $hmoData['validation_status'];
                                }
                            }
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return redirect()->back()->withErrors(['error' => 'HMO Tariff Error: ' . $e->getMessage()])->withInput();
                        }

                        $bill_req->save();


                        ProductRequest::where('id', $request->selectedPrescBillRows[$i])->update([
                            'status' => 2,
                            'billed_by' => Auth::id(),
                            'billed_date' => date('Y-m-d H:i:s'),
                            'product_request_id' => $bill_req->id
                        ]);

                        $product = Product::with(['stock'])->where('id', $prod_id)->first();
                        if ($product && $product->stock) {
                            $quantityToDecrement = 1; // You can adjust this value based on your requirements
                            $product->stock->decrement('current_quantity', $quantityToDecrement);
                        }

                        //Save the updated stock model back to the database
                        if ($product->stock) {
                            $product->stock->save();
                        }
                    }
                }
                if (isset($request->addedPrescBillRows)) {
                    for ($i = 0; $i < count($request->addedPrescBillRows); $i++) {
                        $bill_req = new ProductOrServiceRequest;
                        $bill_req->user_id = $request->patient_user_id;
                        $bill_req->staff_user_id = Auth::id();
                        $bill_req->product_id = $request->addedPrescBillRows[$i];

                        // Apply HMO tariff if patient has HMO
                        try {
                            $patient = patient::where('user_id', $request->patient_user_id)->first();
                            if ($patient) {
                                $hmoData = HmoHelper::applyHmoTariff($patient->id, $request->addedPrescBillRows[$i], null);
                                if ($hmoData) {
                                    $bill_req->payable_amount = $hmoData['payable_amount'];
                                    $bill_req->claims_amount = $hmoData['claims_amount'];
                                    $bill_req->coverage_mode = $hmoData['coverage_mode'];
                                    $bill_req->validation_status = $hmoData['validation_status'];
                                }
                            }
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return redirect()->back()->withErrors(['error' => 'HMO Tariff Error: ' . $e->getMessage()])->withInput();
                        }

                        $bill_req->save();

                        $presc = new ProductRequest();
                        $presc->product_id = $request->addedPrescBillRows[$i];
                        $presc->dose = $request->consult_presc_dose[$i];
                        // $presc->encounter_id = $encounter->id;
                        $presc->billed_by = Auth::id();
                        $presc->billed_date = date('Y-m-d H:i:s');
                        $presc->patient_id = $request->patient_id;
                        $presc->doctor_id = Auth::id();
                        $presc->product_request_id = $bill_req->id;
                        $presc->status = 2;
                        $presc->save();

                        $product = Product::with(['stock'])->where('id', $request->addedPrescBillRows[$i])->first();
                        if ($product && $product->stock) {
                            $quantityToDecrement = 1; // You can adjust this value based on your requirements
                            $product->stock->decrement('current_quantity', $quantityToDecrement);
                        }

                        //Save the updated stock model back to the database
                        if ($product->stock) {
                            $product->stock->save();
                        }
                    }
                }
                DB::commit();
                return redirect()->back()->with(['message' => "Product Requests Billed Successfully", 'message_type' => 'success']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage() . 'line' . $e->getLine());
        }
    }

    public function dispense(Request $request)
    {
        try {
            $request->validate([
                'selectedPrescDispenseRows' => 'array',
                'patient_user_id' => 'required',
                'patient_id' => 'required'
            ]);

            if (isset($request->dismiss_presc_dispense) && isset($request->selectedPrescDispenseRows)) {
                DB::beginTransaction();
                for ($i = 0; $i < count($request->selectedPrescDispenseRows); $i++) {
                    ProductRequest::where('id', $request->selectedPrescDispenseRows[$i])->update([
                        'status' => 0
                    ]);
                }
                DB::commit();
                return redirect()->back()->with(['message' => "Product Requests Dismissed Successfully", 'message_type' => 'success']);
            } else {
                DB::beginTransaction();
                if (isset($request->selectedPrescDispenseRows)) {
                    for ($i = 0; $i < count($request->selectedPrescDispenseRows); $i++) {
                        $productRequest = ProductRequest::with('productOrServiceRequest')->findOrFail($request->selectedPrescDispenseRows[$i]);

                        // Check payment and HMO delivery requirements
                        if ($productRequest->productOrServiceRequest) {
                            $deliveryCheck = HmoHelper::canDeliverService($productRequest->productOrServiceRequest);
                            if (!$deliveryCheck['can_deliver']) {
                                DB::rollBack();
                                return redirect()->back()->with([
                                    'message' => $deliveryCheck['reason'] . ' for Request ID: ' . $productRequest->id,
                                    'hint' => $deliveryCheck['hint'],
                                    'message_type' => 'error'
                                ]);
                            }
                        }

                        ProductRequest::where('id', $request->selectedPrescDispenseRows[$i])->update([
                            'status' => 3,
                            'dispensed_by' => Auth::id(),
                            'dispense_date' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }

                DB::commit();
                return redirect()->back()->with(['message' => "Product Requests Dispensed Successfully", 'message_type' => 'success']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage() . 'line' . $e->getLine());
        }
    }

    /**
     * AJAX version of bill - returns JSON response
     */
    public function billAjax(Request $request)
    {
        try {
            $request->validate([
                'consult_presc_dose' => 'nullable|array',
                'addedPrescBillRows' => 'nullable|array',
                'selectedPrescBillRows' => 'nullable|array',
                'patient_user_id' => 'required',
                'patient_id' => 'required'
            ]);

            DB::beginTransaction();
            $billedCount = 0;
            $errors = [];

            // Process selected existing ProductRequests
            if (isset($request->selectedPrescBillRows) && is_array($request->selectedPrescBillRows)) {
                foreach ($request->selectedPrescBillRows as $prId) {
                    $prod_req = ProductRequest::with('product.price')->find($prId);
                    if (!$prod_req) {
                        $errors[] = "PR#{$prId}: Not found";
                        continue;
                    }
                    if ($prod_req->status != 1) {
                        $errors[] = "PR#{$prId}: Already processed";
                        continue;
                    }

                    $prod_id = $prod_req->product_id;
                    $bill_req = new ProductOrServiceRequest;
                    $bill_req->user_id = $request->patient_user_id;
                    $bill_req->staff_user_id = Auth::id();
                    $bill_req->product_id = $prod_id;

                    // Apply HMO tariff
                    $patient = patient::find($request->patient_id);
                    if ($patient && $patient->hmo_id) {
                        try {
                            $hmoData = HmoHelper::applyHmoTariff($patient->id, $prod_id, null);
                            if ($hmoData) {
                                $bill_req->payable_amount = $hmoData['payable_amount'];
                                $bill_req->claims_amount = $hmoData['claims_amount'];
                                $bill_req->coverage_mode = $hmoData['coverage_mode'];
                                $bill_req->validation_status = $hmoData['validation_status'] ?? 'pending';
                            }
                        } catch (\Exception $e) {
                            $price = optional($prod_req->product->price)->current_sale_price ?? 0;
                            $bill_req->payable_amount = $price;
                            $bill_req->claims_amount = 0;
                            $bill_req->coverage_mode = 'none';
                        }
                    } else {
                        $price = optional($prod_req->product->price)->current_sale_price ?? 0;
                        $bill_req->payable_amount = $price;
                        $bill_req->claims_amount = 0;
                        $bill_req->coverage_mode = 'none';
                    }

                    $bill_req->save();

                    $prod_req->update([
                        'status' => 2,
                        'billed_by' => Auth::id(),
                        'billed_date' => now(),
                        'product_request_id' => $bill_req->id
                    ]);

                    // Decrement stock
                    $product = Product::with('stock')->find($prod_id);
                    if ($product && $product->stock) {
                        $qty = $prod_req->qty ?? 1;
                        $product->stock->decrement('current_quantity', $qty);
                    }

                    $billedCount++;
                }
            }

            // Process newly added products
            if (isset($request->addedPrescBillRows) && is_array($request->addedPrescBillRows)) {
                $doses = $request->consult_presc_dose ?? [];

                for ($i = 0; $i < count($request->addedPrescBillRows); $i++) {
                    $productId = $request->addedPrescBillRows[$i];
                    $dose = $doses[$i] ?? '';

                    $product = Product::with(['price', 'stock'])->find($productId);
                    if (!$product) {
                        $errors[] = "Product #{$productId}: Not found";
                        continue;
                    }

                    // Create ProductOrServiceRequest
                    $bill_req = new ProductOrServiceRequest;
                    $bill_req->user_id = $request->patient_user_id;
                    $bill_req->staff_user_id = Auth::id();
                    $bill_req->product_id = $productId;

                    // Apply HMO tariff
                    $patient = patient::find($request->patient_id);
                    if ($patient && $patient->hmo_id) {
                        try {
                            $hmoData = HmoHelper::applyHmoTariff($patient->id, $productId, null);
                            if ($hmoData) {
                                $bill_req->payable_amount = $hmoData['payable_amount'];
                                $bill_req->claims_amount = $hmoData['claims_amount'];
                                $bill_req->coverage_mode = $hmoData['coverage_mode'];
                                $bill_req->validation_status = $hmoData['validation_status'] ?? 'pending';
                            }
                        } catch (\Exception $e) {
                            $price = optional($product->price)->current_sale_price ?? 0;
                            $bill_req->payable_amount = $price;
                            $bill_req->claims_amount = 0;
                            $bill_req->coverage_mode = 'none';
                        }
                    } else {
                        $price = optional($product->price)->current_sale_price ?? 0;
                        $bill_req->payable_amount = $price;
                        $bill_req->claims_amount = 0;
                        $bill_req->coverage_mode = 'none';
                    }

                    $bill_req->save();

                    // Create ProductRequest with status=2 (billed)
                    $presc = new ProductRequest();
                    $presc->product_id = $productId;
                    $presc->dose = $dose;
                    $presc->billed_by = Auth::id();
                    $presc->billed_date = now();
                    $presc->patient_id = $request->patient_id;
                    $presc->doctor_id = Auth::id();
                    $presc->product_request_id = $bill_req->id;
                    $presc->status = 2;
                    $presc->save();

                    // Decrement stock
                    if ($product->stock) {
                        $product->stock->decrement('current_quantity', 1);
                    }

                    $billedCount++;
                }
            }

            DB::commit();

            $message = "Successfully billed {$billedCount} item(s)";
            if (count($errors) > 0) {
                $message .= ". Errors: " . implode('; ', $errors);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'billed_count' => $billedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error billing items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * AJAX version of dispense - returns JSON response
     */
    public function dispenseAjax(Request $request)
    {
        try {
            $request->validate([
                'selectedPrescDispenseRows' => 'required|array|min:1',
                'patient_user_id' => 'required',
                'patient_id' => 'required'
            ]);

            DB::beginTransaction();
            $dispensedCount = 0;
            $errors = [];

            foreach ($request->selectedPrescDispenseRows as $prId) {
                $productRequest = ProductRequest::with('productOrServiceRequest')->find($prId);

                if (!$productRequest) {
                    $errors[] = "PR#{$prId}: Not found";
                    continue;
                }

                if ($productRequest->status != 2) {
                    $errors[] = "PR#{$prId}: Not billed yet";
                    continue;
                }

                // Check payment and HMO delivery requirements
                if ($productRequest->productOrServiceRequest) {
                    $deliveryCheck = HmoHelper::canDeliverService($productRequest->productOrServiceRequest);
                    if (!$deliveryCheck['can_deliver']) {
                        $errors[] = "PR#{$prId}: " . $deliveryCheck['reason'];
                        continue;
                    }
                }

                $productRequest->update([
                    'status' => 3,
                    'dispensed_by' => Auth::id(),
                    'dispense_date' => now(),
                ]);

                $dispensedCount++;
            }

            DB::commit();

            $message = "Successfully dispensed {$dispensedCount} item(s)";
            if (count($errors) > 0) {
                $message .= ". Errors: " . implode('; ', $errors);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'dispensed_count' => $dispensedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error dispensing items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * AJAX dismiss prescriptions
     */
    public function dismissAjax(Request $request)
    {
        try {
            $request->validate([
                'prescription_ids' => 'required|array|min:1',
                'patient_id' => 'required'
            ]);

            DB::beginTransaction();
            $dismissedCount = 0;
            $errors = [];

            foreach ($request->prescription_ids as $prId) {
                $productRequest = ProductRequest::find($prId);

                if (!$productRequest) {
                    $errors[] = "PR#{$prId}: Not found";
                    continue;
                }

                if ($productRequest->status == 3) {
                    $errors[] = "PR#{$prId}: Already dispensed - cannot dismiss";
                    continue;
                }

                // Soft delete or update status to 0 (dismissed)
                $productRequest->update(['status' => 0]);
                $dismissedCount++;
            }

            DB::commit();

            $message = "Successfully dismissed {$dismissedCount} item(s)";
            if (count($errors) > 0) {
                $message .= ". Errors: " . implode('; ', $errors);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'dismissed_count' => $dismissedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error dismissing items: ' . $e->getMessage()
            ], 500);
        }
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
     * @param  \App\Models\ProductRequest  $productRequest
     * @return \Illuminate\Http\Response
     */
    public function show(ProductRequest $productRequest)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ProductRequest  $productRequest
     * @return \Illuminate\Http\Response
     */
    public function edit(ProductRequest $productRequest)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ProductRequest  $productRequest
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ProductRequest $productRequest)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ProductRequest  $productRequest
     * @return \Illuminate\Http\Response
     */
    public function destroy(ProductRequest $productRequest)
    {
        //
    }
}
