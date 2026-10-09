<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;

class NhmisServiceMapping extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'nhmis_service_mappings';

    protected $fillable = [
        'indicator_code',
        'service_id',
        'service_type',
        'notes',
        'supported_outcomes',
        'positive_outcomes',
    ];

    protected $casts = [
        'supported_outcomes' => 'array',
        'positive_outcomes' => 'array',
    ];

    /**
     * Standard Outcome Presets by Clinical Category
     */
    public const OUTCOME_PRESETS = [
        'malaria' => [
            'supported' => ['Negative', 'Positive (+)', 'Positive (++)', 'Positive (+++)', 'Positive (++++)', 'Indeterminate'],
            'positive' => ['Positive (+)', 'Positive (++)', 'Positive (+++)', 'Positive (++++)'],
        ],
        'serology' => [
            'supported' => ['Non-Reactive', 'Reactive', 'Indeterminate'],
            'positive' => ['Reactive'],
        ],
        'microbiology' => [
            'supported' => ['Negative (Not Seen)', 'Positive (1+)', 'Positive (2+)', 'Positive (3+)', 'MTB Detected', 'MTB Not Detected'],
            'positive' => ['Positive (1+)', 'Positive (2+)', 'Positive (3+)', 'MTB Detected'],
        ],
        'qualitative' => [
            'supported' => ['Negative', 'Trace', '1+', '2+', '3+', '4+'],
            'positive' => ['1+', '2+', '3+', '4+'],
        ],
        'pregnancy' => [
            'supported' => ['Negative', 'Positive'],
            'positive' => ['Positive'],
        ],
        'procedure' => [
            'supported' => ['Successful', 'Complications', 'Aborted', 'Converted'],
            'positive' => ['Successful'],
        ],
        'imaging' => [
            'supported' => ['Normal / Completed', 'Abnormal / Pathological Findings'],
            'positive' => ['Abnormal / Pathological Findings'],
        ],
        'chest_xray' => [
            'supported' => ['Normal / Clear', 'Abnormal (TB Presumptive)', 'Other Abnormalities'],
            'positive' => ['Abnormal (TB Presumptive)'],
        ],
        'ultrasound' => [
            'supported' => ['Normal / Viable', 'Abnormal / Complications'],
            'positive' => ['Abnormal / Complications'],
        ],
    ];

    /**
     * Standard NHMIS Clinical Service Indicators across Lab, Imaging, and Procedures
     */
    public const INDICATORS = [
        // ==================== INVESTIGATIONS (LABORATORY - CAT 2) ====================
        'malaria_rdt' => [
            'label' => 'Malaria Rapid Diagnostic Test (mRDT)',
            'description' => 'Rapid antigen-detecting test for malaria (Rows 147-148)',
            'keywords' => ['rdt', 'malaria rdt', 'mrdt', 'rapid diagnostic'],
            'section' => 'Malaria Prevention & Treatment',
            'service_type' => 'investigation',
            'category_id' => 2,
            'preset_key' => 'malaria',
        ],
        'malaria_microscopy' => [
            'label' => 'Malaria Microscopy (MP)',
            'description' => 'Blood film microscopy (thick/thin) for malaria parasites (Rows 147-148)',
            'keywords' => ['microscopy', 'mp', 'malaria parasite', 'blood film', 'giemsa'],
            'section' => 'Malaria Prevention & Treatment',
            'service_type' => 'investigation',
            'category_id' => 2,
            'preset_key' => 'malaria',
        ],
        'hiv_screening' => [
            'label' => 'HIV Rapid Screening Test (RVS / Determine)',
            'description' => 'Initial rapid screening test for HIV antibodies (Rows 162-163)',
            'keywords' => ['rvs', 'retro viral', 'hiv test', 'determine', 'screening'],
            'section' => 'Antenatal Care & Infectious Diseases',
            'service_type' => 'investigation',
            'category_id' => 2,
            'preset_key' => 'serology',
        ],
        'hiv_confirmatory' => [
            'label' => 'HIV Confirmatory Test (Stat-Pak / Uni-Gold)',
            'description' => 'Confirmatory rapid test for reactive screening samples',
            'keywords' => ['stat-pak', 'stat pak', 'unigold', 'uni-gold', 'confirmatory'],
            'section' => 'Antenatal Care & Infectious Diseases',
            'service_type' => 'investigation',
            'category_id' => 2,
            'preset_key' => 'serology',
        ],
        'syphilis_vdrl' => [
            'label' => 'Syphilis Screening (VDRL / RPR / TPHA)',
            'description' => 'Serological test for syphilis (Rows 166-167)',
            'keywords' => ['vdrl', 'rpr', 'syphilis', 'tpha'],
            'section' => 'Antenatal Care & Serology',
            'service_type' => 'investigation',
            'category_id' => 2,
            'preset_key' => 'serology',
        ],
        'hepatitis_b' => [
            'label' => 'Hepatitis B Surface Antigen (HBsAg)',
            'description' => 'Rapid test / serology for Hepatitis B virus (Row 164)',
            'keywords' => ['hbsag', 'hepatitis b'],
            'section' => 'Antenatal Care & Serology',
            'service_type' => 'investigation',
            'category_id' => 2,
            'preset_key' => 'serology',
        ],
        'hepatitis_c' => [
            'label' => 'Hepatitis C Antibody (HCV)',
            'description' => 'Rapid test / serology for Hepatitis C virus (Row 165)',
            'keywords' => ['hcv', 'hepatitis c'],
            'section' => 'Antenatal Care & Serology',
            'service_type' => 'investigation',
            'category_id' => 2,
            'preset_key' => 'serology',
        ],
        'tb_sputum_afb' => [
            'label' => 'Sputum AFB / GeneXpert for TB',
            'description' => 'Acid-fast bacilli microscopy or molecular test for Tuberculosis (Row 161)',
            'keywords' => ['afb', 'sputum', 'genexpert', 'tuberculosis', 'tb'],
            'section' => 'Microbiology & Infectious Diseases',
            'service_type' => 'investigation',
            'category_id' => 2,
            'preset_key' => 'microbiology',
        ],
        'pcv_hb' => [
            'label' => 'Packed Cell Volume / Hemoglobin (PCV / Hb)',
            'description' => 'Hematocrit and hemoglobin estimation (Row 32 severe anaemia)',
            'keywords' => ['pcv', 'packed cell volume', 'hb', 'hemoglobin', 'haemoglobin'],
            'section' => 'Antenatal Care & Hematology',
            'service_type' => 'investigation',
            'category_id' => 2,
            'preset_key' => 'qualitative',
        ],
        'urinalysis_protein' => [
            'label' => 'Urinalysis (Proteinuria / Glycosuria)',
            'description' => 'Urine test for proteinuria and glucosuria (Row 33)',
            'keywords' => ['urinalysis', 'urine protein', 'proteinuria'],
            'section' => 'Antenatal Care & Clinical Chemistry',
            'service_type' => 'investigation',
            'category_id' => 2,
            'preset_key' => 'qualitative',
        ],
        'pregnancy_test' => [
            'label' => 'Pregnancy Test (PT)',
            'description' => 'Urine or serum beta-hCG qualitative test',
            'keywords' => ['pregnancy test', 'pt', 'hcg'],
            'section' => 'Reproductive Health',
            'service_type' => 'investigation',
            'category_id' => 2,
            'preset_key' => 'pregnancy',
        ],
        'blood_glucose' => [
            'label' => 'Blood Glucose (FBG / RBS)',
            'description' => 'Fasting or random blood sugar determination (Row 137 Diabetes)',
            'keywords' => ['fbg', 'rbs', 'blood glucose', 'blood sugar', 'fasting blood'],
            'section' => 'Clinical Chemistry',
            'service_type' => 'investigation',
            'category_id' => 2,
            'preset_key' => 'qualitative',
        ],

        // ==================== PROCEDURES (SURGERY - CAT 8) ====================
        'caesarean_section' => [
            'label' => 'Caesarean Section (CS)',
            'description' => 'Elective or emergency caesarean deliveries (Row 36 Caesarean Section)',
            'keywords' => ['caesarean', 'cesarean', 'c-section', 'c/s', 'lscs'],
            'section' => 'Labour & Delivery (Procedures)',
            'service_type' => 'procedure',
            'category_id' => 8,
            'preset_key' => 'procedure',
        ],
        'mva_spontaneous' => [
            'label' => 'MVA - Spontaneous Abortion Evacuation',
            'description' => 'Manual vacuum aspiration for spontaneous miscarriage (Row 44)',
            'keywords' => ['mva', 'spontaneous', 'miscarriage', 'aspiration'],
            'section' => 'Post-Abortion & Maternal Care (Procedures)',
            'service_type' => 'procedure',
            'category_id' => 8,
            'preset_key' => 'procedure',
        ],
        'mva_induced' => [
            'label' => 'MVA - Induced Abortion Evacuation',
            'description' => 'Manual vacuum aspiration for induced abortion management (Row 44)',
            'keywords' => ['mva', 'induced', 'termination', 'evacuation'],
            'section' => 'Post-Abortion & Maternal Care (Procedures)',
            'service_type' => 'procedure',
            'category_id' => 8,
            'preset_key' => 'procedure',
        ],
        'mva_pac' => [
            'label' => 'Post-Abortion Care (PAC)',
            'description' => 'Clinical post-abortion care and emergency uterine evacuation (Row 45)',
            'keywords' => ['pac', 'post abortion', 'post-abortion', 'd&c', 'curettage'],
            'section' => 'Post-Abortion & Maternal Care (Procedures)',
            'service_type' => 'procedure',
            'category_id' => 8,
            'preset_key' => 'procedure',
        ],
        'tubal_ligation' => [
            'label' => 'Bilateral Tubal Ligation (BTL)',
            'description' => 'Female surgical sterilization / permanent contraception (Row 125)',
            'keywords' => ['tubal ligation', 'btl', 'tubal occlusion', 'female sterilization'],
            'section' => 'Family Planning (Procedures)',
            'service_type' => 'procedure',
            'category_id' => 8,
            'preset_key' => 'procedure',
        ],
        'vasectomy' => [
            'label' => 'Vasectomy (Male Sterilization)',
            'description' => 'Male surgical sterilization / permanent contraception (Row 126)',
            'keywords' => ['vasectomy', 'male sterilization', 'no-scalpel vasectomy'],
            'section' => 'Family Planning (Procedures)',
            'service_type' => 'procedure',
            'category_id' => 8,
            'preset_key' => 'procedure',
        ],
        'fistula_repair' => [
            'label' => 'Obstetric Fistula Repair (VVF / RVF)',
            'description' => 'Surgical repair of vesicovaginal or rectovaginal fistula (Row 176)',
            'keywords' => ['vvf', 'rvf', 'fistula', 'fistula repair'],
            'section' => 'Specialized Surgery (Procedures)',
            'service_type' => 'procedure',
            'category_id' => 8,
            'preset_key' => 'procedure',
        ],

        // ==================== IMAGING (RADIOLOGY - CAT 6) ====================
        'obstetric_ultrasound' => [
            'label' => 'Obstetric Ultrasound Scan (ANC Sonogram)',
            'description' => 'Routine or emergency pregnancy ultrasound scan during antenatal care',
            'keywords' => ['obstetric scan', 'obstetric ultrasound', 'antenatal scan', 'pregnancy scan'],
            'section' => 'Antenatal Care & Imaging',
            'service_type' => 'imaging',
            'category_id' => 6,
            'preset_key' => 'ultrasound',
        ],
        'chest_xray_tb' => [
            'label' => 'Chest X-Ray (Presumptive TB Screening)',
            'description' => 'Plain chest radiography for pulmonary tuberculosis evaluation',
            'keywords' => ['chest x-ray', 'chest xray', 'cxr', 'thorax'],
            'section' => 'Infectious Diseases & Imaging',
            'service_type' => 'imaging',
            'category_id' => 6,
            'preset_key' => 'chest_xray',
        ],
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    /**
     * Resolve category ID for a service type from facility appsettings()
     */
    public static function getCategoryIdForType(string $serviceType): int
    {
        return match (strtolower(trim($serviceType))) {
            'imaging' => (int) appsettings('imaging_category_id', 6),
            'procedure' => (int) appsettings('procedure_category_id', 8),
            default => (int) appsettings('investigation_category_id', 2),
        };
    }

    /**
     * Get all unique active category IDs for delegated NHMIS services from appsettings()
     *
     * @return array<int>
     */
    public static function getDelegatedCategoryIds(): array
    {
        return array_values(array_unique(array_filter([
            (int) appsettings('investigation_category_id', 2),
            (int) appsettings('imaging_category_id', 6),
            (int) appsettings('procedure_category_id', 8),
        ])));
    }

    /**
     * Get indicators catalog with dynamic category IDs resolved from appsettings()
     */
    public static function getIndicatorsWithDynamicCategories(): array
    {
        $indicators = self::INDICATORS;
        foreach ($indicators as $code => &$meta) {
            $meta['category_id'] = self::getCategoryIdForType($meta['service_type'] ?? 'investigation');
        }

        return $indicators;
    }

    /**
     * Resolve mapped service IDs for an indicator.
     *
     * @return array<int>
     */
    public static function getServiceIds(string $indicatorCode): array
    {
        return static::where('indicator_code', $indicatorCode)->pluck('service_id')->toArray();
    }

    /**
     * Get outcome preset for an indicator
     */
    public static function getPresetsForIndicator(string $indicatorCode): array
    {
        $meta = self::INDICATORS[$indicatorCode] ?? null;
        $presetKey = $meta['preset_key'] ?? 'qualitative';

        return self::OUTCOME_PRESETS[$presetKey] ?? [
            'supported' => ['Negative', 'Positive'],
            'positive' => ['Positive'],
        ];
    }

    /**
     * Populate default service delegations across Lab, Imaging, and Procedures
     */
    public static function autoPopulateDefaults(): int
    {
        $added = 0;

        foreach (self::INDICATORS as $code => $meta) {
            $catId = self::getCategoryIdForType($meta['service_type'] ?? 'investigation');
            $keywords = $meta['keywords'];
            $presets = self::getPresetsForIndicator($code);

            $services = DB::table('services')
                ->where('category_id', $catId)
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $q->orWhere('service_name', 'like', "%{$kw}%");
                    }
                })->get();

            foreach ($services as $s) {
                $exists = static::where('indicator_code', $code)->where('service_id', $s->id)->exists();
                if (!$exists) {
                    static::create([
                        'indicator_code' => $code,
                        'service_id' => $s->id,
                        'service_type' => $meta['service_type'],
                        'notes' => 'Auto-mapped: ' . $meta['label'],
                        'supported_outcomes' => $presets['supported'],
                        'positive_outcomes' => $presets['positive'],
                    ]);
                    $added++;
                }
            }
        }

        return $added;
    }
}
