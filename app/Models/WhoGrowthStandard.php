<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhoGrowthStandard extends Model
{
    protected $table = 'who_growth_standards';

    protected $fillable = [
        'indicator', 'sex', 'age_months',
        'l_value', 'm_value', 's_value',
    ];

    protected $casts = [
        'age_months' => 'decimal:1',
        'l_value'    => 'decimal:4',
        'm_value'    => 'decimal:4',
        's_value'    => 'decimal:5',
    ];

    /* ══════════════════════════════════════════════════════════════
       SCOPES
       ══════════════════════════════════════════════════════════════ */

    public function scopeForIndicator($query, string $indicator)
    {
        return $query->where('indicator', $indicator);
    }

    public function scopeForSex($query, string $sex)
    {
        return $query->where('sex', $sex);
    }

    public function scopeForAge($query, float $ageMonths)
    {
        return $query->where('age_months', $ageMonths);
    }

    /* ══════════════════════════════════════════════════════════════
       Z-SCORE CALCULATION (WHO LMS method)
       ══════════════════════════════════════════════════════════════

       WHO Child Growth Standards use the Box-Cox power exponential
       (BCPE) distribution:

       If L ≠ 0:  z = [ (measurement / M)^L  − 1 ] / (L × S)
       If L = 0:  z = ln(measurement / M) / S

       Reference: WHO Multicentre Growth Reference Study Group (2006)
       ══════════════════════════════════════════════════════════════ */

    /**
     * Compute z-score for a given measurement using this row's LMS params.
     */
    public function computeZScore(float $measurement): ?float
    {
        if ($measurement <= 0 || $this->m_value <= 0) {
            return null;
        }

        $L = (float) $this->l_value;
        $M = (float) $this->m_value;
        $S = (float) $this->s_value;

        if (abs($L) < 0.0001) {
            // L ≈ 0 → use log formula
            $z = log($measurement / $M) / $S;
        } else {
            $z = (pow($measurement / $M, $L) - 1) / ($L * $S);
        }

        // WHO restricts z-scores to [-6, +6] for clinical data quality
        return max(-6, min(6, round($z, 2)));
    }

    /**
     * Compute the measurement value at a given z-score (for chart SD lines).
     */
    public function measurementAtZ(float $z): float
    {
        $L = (float) $this->l_value;
        $M = (float) $this->m_value;
        $S = (float) $this->s_value;

        if (abs($L) < 0.0001) {
            return $M * exp($S * $z);
        }

        return $M * pow(1 + $L * $S * $z, 1 / $L);
    }

    /**
     * Get pre-computed SD line values (-3, -2, -1, 0, +1, +2, +3).
     */
    public function getSdLines(): array
    {
        return [
            'sd_neg3' => round($this->measurementAtZ(-3), 2),
            'sd_neg2' => round($this->measurementAtZ(-2), 2),
            'sd_neg1' => round($this->measurementAtZ(-1), 2),
            'median'  => round($this->measurementAtZ(0), 2),
            'sd_pos1' => round($this->measurementAtZ(1), 2),
            'sd_pos2' => round($this->measurementAtZ(2), 2),
            'sd_pos3' => round($this->measurementAtZ(3), 2),
        ];
    }

    /* ══════════════════════════════════════════════════════════════
       STATIC HELPERS
       ══════════════════════════════════════════════════════════════ */

    /**
     * Find the nearest LMS row for a given indicator, sex, and age.
     * Uses floor-month matching (e.g. 3.7 months → month 3).
     */
    public static function findLms(string $indicator, string $sex, float $ageMonths): ?self
    {
        // Round to nearest whole month for lookup
        $month = round($ageMonths);
        $month = max(0, min(60, $month));

        return static::where('indicator', $indicator)
            ->where('sex', $sex)
            ->where('age_months', $month)
            ->first();
    }

    /**
     * Calculate z-score given indicator, sex, age and measurement.
     */
    public static function calculateZScore(string $indicator, string $sex, float $ageMonths, float $measurement): ?float
    {
        $lms = static::findLms($indicator, $sex, $ageMonths);

        if (!$lms) {
            return null;
        }

        return $lms->computeZScore($measurement);
    }

    /**
     * Classify nutritional status from weight-for-age z-score (WAZ).
     *
     * WHO classification:
     *   z > +2   → overweight
     *   z > +3   → obese
     *   z < -1   → mild underweight
     *   z < -2   → moderate underweight (underweight)
     *   z < -3   → severe underweight (severely underweight)
     *   else     → normal
     */
    public static function classifyNutritionalStatus(?float $waz, ?float $baz = null): string
    {
        // Use BAZ for overweight/obesity classification if available
        $overweightZ = $baz ?? $waz;

        if ($overweightZ !== null && $overweightZ > 3) return 'obese';
        if ($overweightZ !== null && $overweightZ > 2) return 'overweight';

        if ($waz !== null) {
            if ($waz < -3) return 'severe_underweight';
            if ($waz < -2) return 'moderate_underweight';
            if ($waz < -1) return 'mild_underweight';
        }

        return 'normal';
    }

    /**
     * Get full chart reference data (all months 0–60) for an indicator and sex.
     * Returns array with month → SD line values.
     */
    public static function getChartData(string $indicator, string $sex): array
    {
        $rows = static::where('indicator', $indicator)
            ->where('sex', $sex)
            ->orderBy('age_months')
            ->get();

        return $rows->map(function ($row) {
            $sd = $row->getSdLines();
            $sd['month'] = (float) $row->age_months;
            return $sd;
        })->toArray();
    }
}
