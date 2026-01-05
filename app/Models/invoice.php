<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class invoice extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
/**
     * Get the payment that owns the invoice
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(payment::class);
    }
}
