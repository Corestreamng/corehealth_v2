<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class ReasonForEncounter extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'code',
        'name',
        'category',
        'sub_category'
    ];

    /**
     * Create or find a custom reason for encounter
     */
    public static function createCustomReason($reasonString)
    {
        // Extract code and name from the string if it contains a hyphen
        if (strpos($reasonString, '-') !== false) {
            list($code, $name) = explode('-', $reasonString, 2);
            $code = trim($code);
            $name = trim($name);
        } else {
            $code = 'CUSTOM';
            $name = trim($reasonString);
        }

        // Check if this custom reason already exists
        $existing = self::where('code', $code)
            ->where('name', $name)
            ->where('category', 'custom')
            ->first();

        if ($existing) {
            return $existing;
        }

        // Create new custom reason
        return self::create([
            'code' => $code,
            'name' => $name,
            'category' => 'custom',
            'sub_category' => 'custom'
        ]);
    }
}
