<?php

namespace App\Models\Accounting;

use App\Models\Bank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Bank Statement Import Model
 *
 * Tracks imported bank statement files for reconciliation.
 * Supports PDF, Excel, CSV, Word, and Image formats.
 */
class BankStatementImport extends Model
{
    use HasFactory;

    protected $table = 'bank_statement_imports';

    // Status constants (must match DB enum: 'uploaded','parsing','parsed','imported','failed')
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_PARSING = 'parsing';
    public const STATUS_PARSED = 'parsed';
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_FAILED = 'failed';

    // File format constants
    public const FORMAT_PDF = 'pdf';
    public const FORMAT_EXCEL = 'excel';
    public const FORMAT_CSV = 'csv';
    public const FORMAT_WORD = 'word';
    public const FORMAT_IMAGE = 'image';

    protected $fillable = [
        'bank_id',
        'reconciliation_id',
        'file_name',
        'file_path',
        'file_format',
        'statement_date',
        'period_from',
        'period_to',
        'opening_balance',
        'closing_balance',
        'total_transactions',
        'imported_transactions',
        'failed_transactions',
        'status',
        'error_log',
        'imported_by',
        'parsed_at',
        'imported_at',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'period_from' => 'date',
        'period_to' => 'date',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'total_transactions' => 'integer',
        'imported_transactions' => 'integer',
        'failed_transactions' => 'integer',
        'parsed_at' => 'datetime',
        'imported_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function reconciliation()
    {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }

    public function importedBy()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    // ==========================================
    // STATUS HELPERS
    // ==========================================

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    // ==========================================
    // FILE TYPE HELPERS
    // ==========================================

    /**
     * Determine file format from extension.
     */
    public static function determineFormat(string $extension): string
    {
        $extension = strtolower($extension);

        return match ($extension) {
            'pdf' => self::FORMAT_PDF,
            'xlsx', 'xls' => self::FORMAT_EXCEL,
            'csv' => self::FORMAT_CSV,
            'docx', 'doc' => self::FORMAT_WORD,
            'jpg', 'jpeg', 'png', 'gif', 'webp' => self::FORMAT_IMAGE,
            default => self::FORMAT_PDF,
        };
    }

    /**
     * Check if file supports data extraction (clickable rows).
     */
    public function supportsDataExtraction(): bool
    {
        return in_array($this->file_format, [self::FORMAT_EXCEL, self::FORMAT_CSV]);
    }

    /**
     * Check if file is viewable only (no data extraction).
     */
    public function isViewOnly(): bool
    {
        return in_array($this->file_format, [self::FORMAT_PDF, self::FORMAT_IMAGE, self::FORMAT_WORD]);
    }

    /**
     * Get full storage path.
     */
    public function getFullPathAttribute(): string
    {
        return storage_path('app/' . $this->file_path);
    }

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
            default => 'secondary',
        };
    }
}
