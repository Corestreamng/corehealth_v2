<?php

namespace App\Services;

use App\Models\ProductOrServiceRequest;
use App\Models\BillingQueue;
use App\Models\DoctorQueue;
use App\Models\AdmissionRequest;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BillingQueueService
{
    /**
     * Calculate if an item should be in the unpaid billing queue.
     */
    public static function isQueueable(ProductOrServiceRequest $request): bool
    {
        // Must be unpaid and un-invoiced
        if ($request->payment_id !== null || $request->invoice_id !== null) {
            return false;
        }
        
        // Exclude fully HMO-covered approved items
        if (($request->payable_amount === null || $request->payable_amount == 0) && 
            ($request->claims_amount > 0 && $request->validation_status === 'approved')) {
            return false;
        }
        
        return true;
    }

    /**
     * Incrementally update the queue for a single request change.
     */
    public static function syncForUser(int $userId): void
    {
        // Re-calculate the user's queue state
        $unpaidItems = ProductOrServiceRequest::where('user_id', $userId)
            ->whereNull('payment_id')
            ->whereNull('invoice_id')
            ->whereRaw('NOT ((payable_amount IS NULL OR payable_amount = 0) AND (claims_amount > 0 AND validation_status = ?))', ['approved'])
            ->get();
            
        $count = $unpaidItems->count();
        
        if ($count === 0) {
            // Remove from queue
            BillingQueue::where('user_id', $userId)->delete();
            return;
        }
        
        $hmoCount = $unpaidItems->where('claims_amount', '>', 0)->count();
        $latest = $unpaidItems->max('created_at');
        
        // Find patient context
        $patientId = $unpaidItems->first()->patient_id;
        if (!$patientId) {
            $patientId = Patient::where('user_id', $userId)->value('id');
        }
        
        // Determine emergency status
        $isEmergency = false;
        if ($patientId) {
            $isEmergency = DoctorQueue::where('priority', 'emergency')
                ->where('patient_id', $patientId)
                ->whereIn('status', [1, 2, 3])
                ->exists() 
                || 
                AdmissionRequest::where('priority', 'emergency')
                ->where('patient_id', $patientId)
                ->where('discharged', 0)
                ->exists();
        }
        
        BillingQueue::updateOrCreate(
            ['user_id' => $userId],
            [
                'patient_id' => $patientId,
                'unpaid_items_count' => $count,
                'hmo_items_count' => $hmoCount,
                'is_emergency' => $isEmergency,
                'latest_item_at' => $latest,
            ]
        );
    }
}
