<?php

namespace App\Services;

use App\Models\HR\HrAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * HRMS Implementation Plan - Section 6.4
 * HR Attachment Service - Handles polymorphic file uploads for HR documents
 */
class HrAttachmentService
{
    /**
     * Upload and attach a file to an HR model
     */
    public function attach($model, UploadedFile $file, array $options = []): HrAttachment
    {
        // Generate unique filename
        $originalFilename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;

        // Determine storage path based on model type
        $folder = $this->getFolderForModel($model);
        $path = $file->storeAs("hr/{$folder}", $filename, 'public');

        return HrAttachment::create([
            'attachable_type' => get_class($model),
            'attachable_id' => $model->id,
            'filename' => $filename,
            'original_filename' => $originalFilename,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'document_type' => $options['document_type'] ?? HrAttachment::TYPE_OTHER,
            'description' => $options['description'] ?? null,
            'uploaded_by' => $options['uploaded_by'] ?? auth()->id(),
        ]);
    }

    /**
     * Upload multiple files
     */
    public function attachMultiple($model, array $files, array $options = []): array
    {
        $attachments = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $attachments[] = $this->attach($model, $file, $options);
            }
        }

        return $attachments;
    }

    /**
     * Delete an attachment
     */
    public function delete(HrAttachment $attachment): bool
    {
        // Delete file from storage
        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        // Soft delete the record
        return $attachment->delete();
    }

    /**
     * Get folder path for model type
     */
    protected function getFolderForModel($model): string
    {
        $class = class_basename(get_class($model));

        return match($class) {
            'LeaveRequest' => 'leave-requests',
            'DisciplinaryQuery' => 'disciplinary',
            'StaffSuspension' => 'suspensions',
            'StaffTermination' => 'terminations',
            'PayrollBatch' => 'payroll',
            'Staff' => 'staff-documents',
            default => 'misc'
        };
    }

    /**
     * Get allowed MIME types
     */
    public function getAllowedMimeTypes(): array
    {
        return [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
            'image/gif',
        ];
    }

    /**
     * Get max file size in bytes (10MB)
     */
    public function getMaxFileSize(): int
    {
        return 10 * 1024 * 1024;
    }

    /**
     * Validate an uploaded file
     */
    public function validateFile(UploadedFile $file): array
    {
        $errors = [];

        // Check MIME type
        if (!in_array($file->getMimeType(), $this->getAllowedMimeTypes())) {
            $errors[] = 'Invalid file type. Allowed types: PDF, Word, Excel, JPEG, PNG, GIF.';
        }

        // Check file size
        if ($file->getSize() > $this->getMaxFileSize()) {
            $errors[] = 'File size exceeds maximum allowed (10MB).';
        }

        return $errors;
    }

    /**
     * Get all attachments for a model
     */
    public function getAttachments($model): \Illuminate\Database\Eloquent\Collection
    {
        return $model->attachments()->with('uploadedBy')->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get attachment by ID with ownership check
     */
    public function getAttachment(int $attachmentId, $model = null): ?HrAttachment
    {
        $query = HrAttachment::query();

        if ($model) {
            $query->where('attachable_type', get_class($model))
                  ->where('attachable_id', $model->id);
        }

        return $query->find($attachmentId);
    }

    /**
     * Move attachments from one model to another
     */
    public function moveAttachments($fromModel, $toModel): int
    {
        return HrAttachment::where('attachable_type', get_class($fromModel))
            ->where('attachable_id', $fromModel->id)
            ->update([
                'attachable_type' => get_class($toModel),
                'attachable_id' => $toModel->id,
            ]);
    }
}
