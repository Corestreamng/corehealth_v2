<?php

namespace App\Http\Controllers;

use App\Models\AdmissionRequest;
use App\Models\Patient;
use App\Models\ProductOrServiceRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class AdmissionModuleController extends Controller
{
    /**
     * Get patient admission history list.
     */
    public function getPatientAdmissions($patientId)
    {
        $patient = Patient::find($patientId);
        if (!$patient) {
            return response()->json(['error' => 'Patient not found'], 404);
        }

        $admissions = AdmissionRequest::where('patient_id', $patientId)
            ->with(['bed.wardRelation'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($admission) use ($patient) {
                // Emergency intake admissions skip bed assignment — fall back to created_at
                $admitDate = $admission->bed_assign_date ?? $admission->created_at;
                $dischargeDate = $admission->discharge_date;

                $los = 0;
                if ($admitDate) {
                    $endDate = $dischargeDate ?? now();
                    $los = Carbon::parse($admitDate)->diffInDays(Carbon::parse($endDate)) + 1;
                }

                $totalBill = $this->calculateAdmissionBillTotal($patient->user_id, $admitDate, $dischargeDate);

                $doctorName = 'N/A';
                if ($admission->doctor_id) {
                    $doctorStaff = \App\Models\Staff::find($admission->doctor_id);
                    if ($doctorStaff && $doctorStaff->user_id) {
                        $doctorName = userfullname($doctorStaff->user_id);
                    }
                }

                return [
                    'id' => $admission->id,
                    'admitted_date' => $admitDate ? Carbon::parse($admitDate)->format('d/m/Y H:i') : 'Pending',
                    'discharge_date' => $dischargeDate ? Carbon::parse($dischargeDate)->format('d/m/Y H:i') : ($admission->discharged ? 'Unknown' : null),
                    'los' => $los,
                    'ward' => optional(optional($admission->bed)->wardRelation)->name ?? ($admission->bed->ward ?? 'N/A'),
                    'bed' => optional($admission->bed)->name ?? 'N/A',
                    'doctor' => $doctorName,
                    'reason' => $admission->admission_reason ?? $admission->note ?? 'N/A',
                    'status' => $admission->discharged ? 'discharged' : 'admitted',
                    'priority' => $admission->priority ?? 'routine',
                    'total_bill' => $totalBill,
                ];
            });

        return response()->json([
            'admissions' => $admissions,
            'count' => $admissions->count(),
        ]);
    }

    /**
     * Get detailed admission bill data (categories, timeline, totals, HMO claims).
     */
    public function getAdmissionDetail($admissionId)
    {
        $admission = AdmissionRequest::with(['bed.wardRelation', 'patient'])->find($admissionId);

        if (!$admission) {
            return response()->json(['error' => 'Admission not found'], 404);
        }

        $patient = $admission->patient;
        // For emergency intake admissions, bed assignment is skipped — fall back to created_at
        $admitDate = $admission->bed_assign_date ?? $admission->created_at;
        $dischargeDate = $admission->discharge_date;

        if (!$admitDate) {
            return response()->json(['error' => 'Admission date not set'], 400);
        }

        $query = ProductOrServiceRequest::with(['service.category', 'product.category', 'payment', 'hmo', 'validator'])
            ->where('user_id', $patient->user_id)
            ->where('created_at', '>=', $admitDate);

        if ($dischargeDate) {
            $query->where('created_at', '<=', Carbon::parse($dischargeDate)->endOfDay());
        }

        $billingItems = $query->orderBy('created_at', 'asc')->get();

        $categoryMap = [
            'accommodation' => ['bed', 'ward', 'room', 'accommodation'],
            'nursing' => ['nursing', 'nurse'],
            'consultation' => ['consultation', 'doctor', 'visit'],
            'laboratory' => ['lab', 'laboratory', 'test', 'investigation'],
            'radiology' => ['radiology', 'xray', 'x-ray', 'scan', 'imaging', 'ultrasound', 'ct', 'mri'],
            'pharmacy' => ['drug', 'pharmacy', 'medication', 'medicine'],
            'procedure' => ['procedure', 'surgery', 'operation', 'theatre'],
            'consumables' => ['consumable', 'supply', 'supplies', 'material'],
        ];

        $categoryIcons = [
            'accommodation' => 'mdi-bed',
            'nursing' => 'mdi-account-nurse',
            'consultation' => 'mdi-stethoscope',
            'laboratory' => 'mdi-flask',
            'radiology' => 'mdi-radiology-box',
            'pharmacy' => 'mdi-pill',
            'procedure' => 'mdi-medical-bag',
            'consumables' => 'mdi-bandage',
            'other' => 'mdi-file-document',
        ];

        $categoryLabels = [
            'accommodation' => 'Accommodation & Bed',
            'nursing' => 'Nursing Care',
            'consultation' => 'Consultations',
            'laboratory' => 'Laboratory',
            'radiology' => 'Radiology/Imaging',
            'pharmacy' => 'Pharmacy/Medications',
            'procedure' => 'Procedures',
            'consumables' => 'Consumables/Supplies',
            'other' => 'Other Services',
        ];

        $categories = [];
        $grossTotal = 0;
        $totalDiscount = 0;
        $totalHmo = 0;
        $totalPaid = 0;
        $timeline = [];

        // HMO claims breakdown
        $hmoClaims = [
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'awaiting_code' => 0,
            'express' => 0,
            'total_items' => 0,
            'hmo_name' => null,
            'hmo_no' => $patient->hmo_no ?? null,
            'scheme' => null,
        ];

        // Bill items for full detail table
        $billItems = [];

        foreach ($billingItems as $item) {
            $itemCategory = 'other';
            $categoryName = '';

            if ($item->service_id && $item->service) {
                $categoryName = strtolower($item->service->category->category_name ?? '');
            } elseif ($item->product_id && $item->product) {
                $categoryName = strtolower($item->product->category->category_name ?? 'pharmacy');
                $itemCategory = 'pharmacy';
            }

            foreach ($categoryMap as $cat => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($categoryName, $keyword)) {
                        $itemCategory = $cat;
                        break 2;
                    }
                }
            }

            $qty = $item->qty ?? 1;
            $price = $item->amount ?? 0;
            $subtotal = $price * $qty;
            $discount = $item->discount ?? 0;
            $discountAmount = $subtotal * ($discount / 100);
            $payable = $item->payable_amount ?? ($subtotal - $discountAmount);
            $hmo = $item->claims_amount ?? 0;
            $paid = $item->payment_id ? $payable : 0;

            $grossTotal += $subtotal;
            $totalDiscount += $discountAmount;
            $totalHmo += $hmo;
            $totalPaid += $paid;

            // Track HMO claims
            if ($hmo > 0) {
                $hmoClaims['total_items']++;
                $vs = $item->validation_status ?? 'pending';
                $cm = $item->coverage_mode;

                if ($cm === 'express') {
                    $hmoClaims['express'] += $hmo;
                } elseif ($vs === 'approved') {
                    $hmoClaims['approved'] += $hmo;
                } elseif ($vs === 'rejected') {
                    $hmoClaims['rejected'] += $hmo;
                } elseif ($vs === 'awaiting_code') {
                    $hmoClaims['awaiting_code'] += $hmo;
                } else {
                    $hmoClaims['pending'] += $hmo;
                }

                if (!$hmoClaims['hmo_name'] && $item->hmo) {
                    $hmoClaims['hmo_name'] = $item->hmo->name ?? null;
                }
            }

            $itemName = $item->service_id
                ? ($item->service->service_name ?? 'Service')
                : ($item->product->product_name ?? ($item->product->name ?? 'Product'));

            // Category grouping
            if (!isset($categories[$itemCategory])) {
                $categories[$itemCategory] = [
                    'name' => $categoryLabels[$itemCategory] ?? ucfirst($itemCategory),
                    'icon' => $categoryIcons[$itemCategory] ?? 'mdi-file-document',
                    'items' => [],
                    'total' => 0,
                    'count' => 0,
                ];
            }

            $categories[$itemCategory]['items'][] = [
                'name' => $itemName,
                'qty' => $qty,
                'price' => $price,
                'amount' => $payable,
                'date' => Carbon::parse($item->created_at)->format('d/m H:i'),
                'paid' => $paid > 0,
            ];
            $categories[$itemCategory]['total'] += $payable;
            $categories[$itemCategory]['count']++;

            // Timeline
            $dayKey = Carbon::parse($item->created_at)->format('Y-m-d');
            if (!isset($timeline[$dayKey])) {
                $timeline[$dayKey] = [
                    'date' => Carbon::parse($item->created_at)->format('D, d M Y'),
                    'day_number' => Carbon::parse($admitDate)->diffInDays(Carbon::parse($item->created_at)) + 1,
                    'items' => [],
                    'total' => 0,
                ];
            }
            $timeline[$dayKey]['items'][] = [
                'name' => $itemName,
                'amount' => $payable,
            ];
            $timeline[$dayKey]['total'] += $payable;

            // Full bill item for detail modal
            $validatorName = null;
            if ($item->validated_by) {
                $validatorName = userfullname($item->validated_by);
            }

            $billItems[] = [
                'name' => $itemName,
                'date' => Carbon::parse($item->created_at)->format('d M H:i'),
                'qty' => $qty,
                'price' => $price,
                'amount' => $subtotal,
                'discount' => $discountAmount,
                'claims' => $hmo,
                'payable' => $payable,
                'paid' => $paid > 0,
                'coverage_mode' => $item->coverage_mode,
                'validation_status' => $item->validation_status,
                'auth_code' => $item->auth_code,
                'validator' => $validatorName,
                'validated_at' => $item->validated_at ? Carbon::parse($item->validated_at)->format('d/m/Y H:i') : null,
            ];
        }

        uasort($categories, fn($a, $b) => $b['total'] <=> $a['total']);
        ksort($timeline);

        $balanceDue = $grossTotal - $totalDiscount - $totalHmo - $totalPaid;

        $doctorName = 'N/A';
        if ($admission->doctor_id) {
            $doctorStaff = \App\Models\Staff::find($admission->doctor_id);
            if ($doctorStaff && $doctorStaff->user_id) {
                $doctorName = userfullname($doctorStaff->user_id);
            }
        }

        // Biller info
        $billerName = null;
        if ($admission->billed_by) {
            $billerName = userfullname($admission->billed_by);
        }

        return response()->json([
            'admission' => [
                'id' => $admission->id,
                'patient_name' => userfullname($patient->user_id),
                'patient_file_no' => $patient->file_no,
                'admitted_date' => $admitDate ? Carbon::parse($admitDate)->format('d/m/Y H:i') : 'N/A',
                'discharge_date' => $dischargeDate ? Carbon::parse($dischargeDate)->format('d/m/Y H:i') : 'Currently Admitted',
                'los' => $admitDate ? (Carbon::parse($admitDate)->diffInDays($dischargeDate ? Carbon::parse($dischargeDate) : now()) + 1) . ' days' : 'N/A',
                'ward' => optional(optional($admission->bed)->wardRelation)->name ?? ($admission->bed->ward ?? 'N/A'),
                'bed' => optional($admission->bed)->name ?? 'N/A',
                'doctor' => $doctorName,
                'reason' => $admission->admission_reason ?? $admission->note ?? 'N/A',
                'status' => $admission->discharged ? 'discharged' : 'admitted',
                'priority' => $admission->priority ?? 'routine',
                'chief_complaint' => $admission->chief_complaint,
                'discharge_note' => $admission->discharge_note,
                'discharge_reason' => $admission->discharge_reason,
                'followup_instructions' => $admission->followup_instructions,
                'biller' => $billerName,
                'billed_date' => $admission->billed_date ? Carbon::parse($admission->billed_date)->format('d/m/Y H:i') : null,
            ],
            'categories' => array_values($categories),
            'timeline' => array_values($timeline),
            'bill_items' => $billItems,
            'hmo_claims' => $hmoClaims,
            'totals' => [
                'gross' => $grossTotal,
                'discount' => $totalDiscount,
                'hmo' => $totalHmo,
                'paid' => $totalPaid,
                'balance' => $balanceDue,
            ],
        ]);
    }

    /**
     * Get full admission history for a patient (for the modal).
     */
    public function getAdmissionHistory($patientId)
    {
        $patient = Patient::find($patientId);
        if (!$patient) {
            return response()->json(['error' => 'Patient not found'], 404);
        }

        $admissions = AdmissionRequest::where('patient_id', $patientId)
            ->with(['bed.wardRelation'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($admission) use ($patient) {
                // Emergency intake admissions skip bed assignment — fall back to created_at
                $admitDate = $admission->bed_assign_date ?? $admission->created_at;
                $dischargeDate = $admission->discharge_date;

                $los = 0;
                if ($admitDate) {
                    $endDate = $dischargeDate ?? now();
                    $los = Carbon::parse($admitDate)->diffInDays(Carbon::parse($endDate)) + 1;
                }

                $totalBill = $this->calculateAdmissionBillTotal($patient->user_id, $admitDate, $dischargeDate);

                $doctorName = 'N/A';
                if ($admission->doctor_id) {
                    $doctorStaff = \App\Models\Staff::find($admission->doctor_id);
                    if ($doctorStaff && $doctorStaff->user_id) {
                        $doctorName = userfullname($doctorStaff->user_id);
                    }
                }

                return [
                    'id' => $admission->id,
                    'dates' => ($admitDate ? Carbon::parse($admitDate)->format('d M Y') : '?') . ' → ' .
                               ($dischargeDate ? Carbon::parse($dischargeDate)->format('d M Y') : 'Present'),
                    'los' => $los,
                    'ward' => optional(optional($admission->bed)->wardRelation)->name ?? 'N/A',
                    'doctor' => $doctorName,
                    'reason' => $admission->admission_reason ?? $admission->note ?? 'N/A',
                    'total' => $totalBill,
                    'status' => $admission->discharged ? 'discharged' : 'admitted',
                ];
            });

        return response()->json(['history' => $admissions]);
    }

    /**
     * Print admission bill (reuses detail data).
     */
    public function printAdmissionBill($admissionId)
    {
        $detailResponse = $this->getAdmissionDetail($admissionId);
        $data = json_decode($detailResponse->getContent(), true);

        if (isset($data['error'])) {
            return response()->json($data, 400);
        }

        $site = \App\Models\ApplicationStatu::first();
        $currentUserName = Auth::user() ? (Auth::user()->surname . ' ' . Auth::user()->firstname) : 'System';
        $date = now()->format('d/m/Y H:i');

        $admission = $data['admission'];
        $categories = $data['categories'];
        $totals = $data['totals'];
        $timeline = $data['timeline'];

        $billNo = 'ADM-' . $admissionId . '-' . now()->format('YmdHis');

        $amountParts = explode('.', number_format((float) $totals['balance'], 2, '.', ''));
        $nairaWords = convert_number_to_words((int) $amountParts[0]);
        $koboWords = ((int) $amountParts[1]) > 0 ? ' and ' . convert_number_to_words((int) $amountParts[1]) . ' Kobo' : '';
        $amountInWords = ucwords($nairaWords . ' Naira' . $koboWords);

        $a4 = View::make('admin.Accounts.admission_bill_a4', [
            'site' => $site,
            'admission' => $admission,
            'categories' => $categories,
            'timeline' => $timeline,
            'totals' => $totals,
            'billNo' => $billNo,
            'date' => $date,
            'amountInWords' => $amountInWords,
            'currentUserName' => $currentUserName,
        ])->render();

        $thermal = View::make('admin.Accounts.admission_bill_thermal', [
            'site' => $site,
            'admission' => $admission,
            'categories' => $categories,
            'totals' => $totals,
            'billNo' => $billNo,
            'date' => $date,
            'currentUserName' => $currentUserName,
        ])->render();

        return response()->json([
            'bill_a4' => $a4,
            'bill_thermal' => $thermal,
            'bill_no' => $billNo,
        ]);
    }

    /**
     * Calculate admission bill total.
     */
    protected function calculateAdmissionBillTotal($userId, $admitDate, $dischargeDate)
    {
        if (!$admitDate) return 0;

        $query = ProductOrServiceRequest::where('user_id', $userId)
            ->where('created_at', '>=', $admitDate);

        if ($dischargeDate) {
            $query->where('created_at', '<=', Carbon::parse($dischargeDate)->endOfDay());
        }

        return $query->sum(DB::raw('COALESCE(payable_amount, amount * qty)'));
    }
}
