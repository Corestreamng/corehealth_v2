<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlowQuery extends Model
{
    use HasFactory;

    protected $fillable = [
        'timestamp',
        'user_host',
        'query_time',
        'lock_time',
        'rows_sent',
        'rows_examined',
        'query',
        'query_hash',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'query_time' => 'float',
        'lock_time' => 'float',
        'rows_sent' => 'integer',
        'rows_examined' => 'integer',
    ];
}
