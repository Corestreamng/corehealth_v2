<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ProcedureNote extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'procedure_id',
        'note_type',
        'title',
        'content',
        'created_by',
    ];

    /**
     * Note types
     */
    const TYPE_PRE_OP = 'pre_op';
    const TYPE_INTRA_OP = 'intra_op';
    const TYPE_POST_OP = 'post_op';
    const TYPE_ANESTHESIA = 'anesthesia';
    const TYPE_NURSING = 'nursing';

    /**
     * Note type labels for display
     */
    const NOTE_TYPES = [
        'pre_op' => 'Pre-Operative',
        'intra_op' => 'Intra-Operative',
        'post_op' => 'Post-Operative',
        'anesthesia' => 'Anesthesia',
        'nursing' => 'Nursing',
    ];

    /**
     * Get the procedure.
     */
    public function procedure()
    {
        return $this->belongsTo(Procedure::class, 'procedure_id', 'id');
    }

    /**
     * Get the user who created the note.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Get the display label for the note type.
     */
    public function getNoteTypeDisplayAttribute()
    {
        return self::NOTE_TYPES[$this->note_type] ?? ucfirst(str_replace('_', ' ', $this->note_type));
    }

    /**
     * Scope by note type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('note_type', $type);
    }

    /**
     * Scope for pre-op notes.
     */
    public function scopePreOp($query)
    {
        return $query->byType(self::TYPE_PRE_OP);
    }

    /**
     * Scope for post-op notes.
     */
    public function scopePostOp($query)
    {
        return $query->byType(self::TYPE_POST_OP);
    }

    /**
     * Scope for anesthesia notes.
     */
    public function scopeAnesthesia($query)
    {
        return $query->byType(self::TYPE_ANESTHESIA);
    }
}
