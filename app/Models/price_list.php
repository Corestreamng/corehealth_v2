<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class price_list extends Model implements Auditable
{


    use \OwenIt\Auditing\Auditable;
protected $table = 'price_list';

    protected $fillable = ['price'];
    /**
     * Get all of the service for the price_list
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function service(): HasMany
    {
        return $this->hasMany(service::class);
    }
}
