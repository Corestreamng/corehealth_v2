<?php

namespace App\Observers;

use App\Services\PatientContextService;
use Illuminate\Support\Facades\Log;

/**
 * Global observer to invalidate patient LLM context cache
 * whenever clinical data is created, updated, or deleted.
 */
class ClinicalDataObserver
{
    protected PatientContextService $contextService;

    public function __construct(PatientContextService $contextService)
    {
        $this->contextService = $contextService;
    }

    public function saved($model)
    {
        $this->invalidate($model);
    }

    public function deleted($model)
    {
        $this->invalidate($model);
    }

    public function restored($model)
    {
        $this->invalidate($model);
    }

    protected function invalidate($model)
    {
        try {
            $patientId = $model->patient_id ?? null;
            if ($patientId) {
                $this->contextService->invalidateContextCache($patientId);
            }
        } catch (\Exception $e) {
            Log::error('ClinicalDataObserver failed to invalidate cache', [
                'model' => get_class($model),
                'id' => $model->id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }
}
