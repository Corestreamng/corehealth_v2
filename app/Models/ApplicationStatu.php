<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationStatu extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_name',
        'header_text',
        'footer_text',
        'logo',
        'favicon',
        'hos_color',
        'contact_address',
        'contact_phones',
        'contact_emails',
        'social_links',
        'description',
        'version',
        'active',
        'debug_mode'
    ];
}
