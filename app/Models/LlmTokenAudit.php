<?php

namespace App\Models;

use App\Interoperability\Models\SdkClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LlmTokenAudit extends Model
{
    use HasFactory;

    protected $table = 'llm_token_audits';

    protected $fillable = [
        'source',
        'client_id',
        'user_id',
        'patient_id',
        'encounter_id',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'latency_ms',
        'is_estimated',
        'ip_address',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'latency_ms' => 'integer',
        'is_estimated' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function client()
    {
        return $this->belongsTo(SdkClient::class, 'client_id', 'client_id');
    }
}
