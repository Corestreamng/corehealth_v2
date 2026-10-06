<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class NhmisMonthlyReport extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'nhmis_monthly_reports';

    protected $fillable = [
        'form_version',
        'facility_code',
        'year',
        'month',
        'period_type',
        'start_date',
        'end_date',
        'status',
        'metadata',
        'compiled_by',
        'compiled_at',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'metadata' => 'array',
        'compiled_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function values()
    {
        return $this->hasMany(NhmisMonthlyReportValue::class, 'report_id', 'id');
    }

    public function compiler()
    {
        return $this->belongsTo(User::class, 'compiled_by', 'id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by', 'id');
    }

    /**
     * Get or create report for a specific period & form version
     */
    public static function getOrCreateForPeriod(int $year, int $month, string $version = 'v2019', ?string $startDate = null, ?string $endDate = null): self
    {
        $periodType = 'monthly';
        if ($month === 255 || ($month > 12 && $startDate && $endDate)) {
            $periodType = 'custom';
            $month = 255;
            $year = $startDate ? \Carbon\Carbon::parse($startDate)->year : $year;
        } elseif ($month === 0) {
            $periodType = 'annual';
            $startDate = \Carbon\Carbon::createFromDate($year, 1, 1)->startOfYear()->toDateString();
            $endDate = \Carbon\Carbon::createFromDate($year, 12, 31)->endOfYear()->toDateString();
        } else {
            $periodType = 'monthly';
            $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
            $endDate = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
        }

        $report = static::firstOrCreate(
            [
                'form_version' => $version,
                'year' => $year,
                'month' => $month,
            ],
            [
                'period_type' => $periodType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'facility_code' => appsettings('nhmis_facility_code', '32/01/1/1/0012'),
                'status' => 'draft',
                'metadata' => [
                    'hospital_name' => appsettings('hospital_name'),
                    'state' => appsettings('state'),
                    'lga' => appsettings('lga'),
                    'political_ward' => appsettings('political_ward'),
                    'ownership' => appsettings('facility_ownership', 'private'),
                    'number_of_beds' => Bed::where('status', 1)->count(),
                ],
            ]
        );

        if ($report->period_type !== $periodType || $report->start_date !== $startDate || $report->end_date !== $endDate) {
            $report->update([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period_type' => $periodType,
            ]);
        }

        return $report;
    }

    /**
     * Helper to get month name or custom period label
     */
    public function getMonthNameAttribute(): string
    {
        if ($this->period_type === 'custom' || (int) $this->month === 255) {
            $from = $this->start_date ? \Carbon\Carbon::parse($this->start_date)->format('d M Y') : 'Start';
            $to = $this->end_date ? \Carbon\Carbon::parse($this->end_date)->format('d M Y') : 'End';

            return "Custom ({$from} — {$to})";
        }

        if ((int) $this->month === 0) {
            return 'Full Year (Annual)';
        }

        return date('F', mktime(0, 0, 0, (int) $this->month, 10));
    }
}
