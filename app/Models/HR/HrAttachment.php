<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * HRMS Implementation Plan - Section 5.2
 * HR Attachment - Polymorphic file attachments for HR documents
 */
class HrAttachment extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'filename',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
        'document_type',
        'description',
        'uploaded_by'
    ];

    const TYPE_MEDICAL_REPORT = 'medical_report';
    const TYPE_QUERY_RESPONSE = 'query_response';
    const TYPE_TERMINATION_LETTER = 'termination_letter';
    const TYPE_SUSPENSION_LETTER = 'suspension_letter';
    const TYPE_LEAVE_DOCUMENT = 'leave_document';
    const TYPE_PAYROLL_SUMMARY = 'payroll_summary';
    const TYPE_OTHER = 'other';

    /**
     * Get the owning attachable model
     */
    public function attachable()
    {
        return $this->morphTo();
    }

    /**
     * Get the uploader
     */
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get file URL
     */
    public function getUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Get file extension
     */
    public function getExtensionAttribute(): string
    {
        return pathinfo($this->original_filename, PATHINFO_EXTENSION);
    }

    /**
     * Check if file is an image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if file is a PDF
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Get icon class based on file type
     */
    public function getIconClassAttribute(): string
    {
        if ($this->isImage()) {
            return 'mdi mdi-file-image';
        }

        if ($this->isPdf()) {
            return 'mdi mdi-file-pdf';
        }

        return match($this->extension) {
            'doc', 'docx' => 'mdi mdi-file-word',
            'xls', 'xlsx' => 'mdi mdi-file-excel',
            'ppt', 'pptx' => 'mdi mdi-file-powerpoint',
            'zip', 'rar' => 'mdi mdi-folder-zip',
            default => 'mdi mdi-file-document'
        };
    }

    /**
     * Delete file from storage when model is deleted
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($attachment) {
            if (Storage::exists($attachment->file_path)) {
                Storage::delete($attachment->file_path);
            }
        });
    }

    /**
     * Get static document types
     */
    public static function getDocumentTypes(): array
    {
        return [
            self::TYPE_MEDICAL_REPORT => 'Medical Report',
            self::TYPE_QUERY_RESPONSE => 'Query Response',
            self::TYPE_TERMINATION_LETTER => 'Termination Letter',
            self::TYPE_SUSPENSION_LETTER => 'Suspension Letter',
            self::TYPE_LEAVE_DOCUMENT => 'Leave Document',
            self::TYPE_PAYROLL_SUMMARY => 'Payroll Summary',
            self::TYPE_OTHER => 'Other',
        ];
    }
}
