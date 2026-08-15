<?php

namespace App\Traits;

use App\Models\AuditMark;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\LatestOfMany;

trait IsAuditable
{
    /**
     * Get all of the model's audit marks.
     */
    public function auditMarks()
    {
        return $this->morphMany(AuditMark::class, 'auditable');
    }

    /**
     * Get the active query if one exists.
     */
    public function activeQuery()
    {
        return $this->morphOne(AuditMark::class, 'auditable')
            ->where('status', 'queried')
            ->whereNull('query_resolved_at')
            ->latestOfMany();
    }

    /**
     * Get the latest audit stamp.
     */
    public function latestAudit()
    {
        return $this->morphOne(AuditMark::class, 'auditable')
            ->where('status', 'audited')
            ->latestOfMany();
    }
}
