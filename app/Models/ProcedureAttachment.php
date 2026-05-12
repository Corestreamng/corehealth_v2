<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcedureAttachment extends Model
{
    protected $fillable = [
        'procedure_id',
        'uploaded_by',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'label',
    ];

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function formattedSize(): string
    {
        if ($this->file_size < 1024) {
            return $this->file_size . ' B';
        }
        if ($this->file_size < 1048576) {
            return round($this->file_size / 1024, 1) . ' KB';
        }
        return round($this->file_size / 1048576, 1) . ' MB';
    }
}
