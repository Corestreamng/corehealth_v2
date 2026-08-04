<?php

namespace App\Traits;

use App\Models\User;

trait WorkbenchAuditable
{
    public function auditor()
    {
        return $this->belongsTo(User::class, 'audited_by');
    }

    public function querier()
    {
        return $this->belongsTo(User::class, 'queried_by');
    }

    public function queryResolver()
    {
        return $this->belongsTo(User::class, 'query_resolved_by');
    }
}
