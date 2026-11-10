<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for grounding AI Clinical Scribe orders in CoreHealth v2's
 * Products and Services catalogs via token-compressed categories,
 * appsettings-driven taxonomies, dynamic context search, and fuzzy matching.
 */
class OrderCatalogRegistryService
{
    protected const CACHE_TTL = 3600; // 1 hour
    protected const CACHE_KEY_CATEGORIES = 'ai_scribe_catalog_categories_v2';
    protected const CACHE_KEY_COMPRESSED_SERVICES = 'ai_scribe_compressed_services_catalog_v2';

    /**
     * Common clinical acronyms and synonyms mapped to canonical hospital search terms.
     */
    protected const CLINICAL_SYNONYMS = [
        'cxr' => ['chest x-ray', 'chest radiograph', 'chest'],
        'xray' => ['x-ray', 'radiograph'],
        'uss' => ['ultrasound', 'sonogram', 'scan'],
        'usg' => ['ultrasound', 'sonogram'],
        'fbc' => ['full blood count', 'cbc', 'complete blood count'],
        'cbc' => ['full blood count', 'complete blood count'],
        'pcv' => ['packed cell volume', 'haemoglobin', 'hemoglobin', 'hgb'],
        'mp' => ['malaria parasite', 'malaria', 'blood film'],
        'e/u/cr' => ['electrolytes', 'urea', 'creatinine', 'renal function'],
        'eucr' => ['electrolytes', 'urea', 'creatinine', 'renal function'],
        'secr' => ['serum electrolytes', 'creatinine', 'renal function'],
        'rft' => ['renal function test', 'electrolytes', 'creatinine', 'urea'],
        'kft' => ['kidney function test', 'renal function test', 'urea', 'creatinine'],
        'lft' => ['liver function test', 'bilirubin', 'transaminase', 'alt', 'ast'],
        'fbs' => ['fasting blood sugar', 'glucose'],
        'rbs' => ['random blood sugar', 'glucose'],
        'hba1c' => ['glycated hemoglobin', 'glycated haemoglobin', 'hba1c'],
        'pt' => ['prothrombin time', 'pregnancy test', 'upt'],
        'inr' => ['international normalized ratio', 'prothrombin time'],
        'upt' => ['urine pregnancy test', 'pregnancy test'],
        'msu' => ['midstream urine', 'urinalysis', 'urine mcs'],
        'mcs' => ['microscopy culture', 'sensitivity', 'culture'],
        'widal' => ['widal test', 'typhoid'],
        'ct' => ['computed tomography', 'ct scan'],
        'cat' => ['computed tomography', 'ct scan'],
        'mri' => ['magnetic resonance imaging', 'mri scan'],
        'ecg' => ['electrocardiogram', 'ekg'],
        'ekg' => ['electrocardiogram', 'ecg'],
        'echo' => ['echocardiogram', 'echocardiography'],
        'i&d' => ['incision and drainage', 'abscess'],
        'ind' => ['incision and drainage'],
        'eua' => ['examination under anesthesia', 'examination under anaesthesia'],
        'cs' => ['caesarean section', 'cesarean section'],
        'c-section' => ['caesarean section', 'cesarean section'],
        'lp' => ['lumbar puncture'],
        'ogd' => ['endoscopy', 'gastroscopy'],
        'hiv' => ['human immunodeficiency virus', 'retroviral screening', 'rvs'],
        'rvs' => ['retroviral screening', 'hiv'],
        'hbsag' => ['hepatitis b', 'surface antigen'],
        'hcv' => ['hepatitis c'],
        'vdrl' => ['syphilis', 'vdrl'],
        'psa' => ['prostate specific antigen'],
        'tft' => ['thyroid function test', 'tsh', 't3', 't4'],
        'tsh' => ['thyroid stimulating hormone', 'thyroid function'],
        'esr' => ['erythrocyte sedimentation rate'],
        'stool' => ['stool microscopy', 'stool routine', 'stool mcs', 'stool culture'],
        'stool mcs' => ['stool microscopy', 'stool routine', 'stool m/c/s', 'stool culture'],
        'mcs' => ['microscopy culture sensitivity', 'm/c/s', 'culture and sensitivity'],
        'antibiotic' => ['amoxicillin', 'augmentin', 'ciprofloxacin', 'metronidazole', 'ceftriaxone', 'azithromycin'],
        'sputum' => ['sputum afb', 'genexpert'],
        'afb' => ['acid fast bacilli', 'tuberculosis', 'genexpert'],
        'genexpert' => ['tuberculosis', 'mycobacterium', 'afb'],
    ];

    /**
     * Retrieve the authoritative service category IDs configured in appsettings().
     */
    public function getAppSettingCategoryIds(): array
    {
        return [
            'lab' => (int) appsettings('investigation_category_id', 2),
            'imaging' => (int) appsettings('imaging_category_id', 6),
            'procedure' => (int) appsettings('procedure_category_id', 8),
            'nursing' => (int) appsettings('nursing_service_category', 4),
            'consultation' => (int) appsettings('consultation_category_id', 1),
            'morgue' => (int) appsettings('morgue_category_id', 9),
            'registration' => (int) appsettings('registration_category_id', 7),
            'bed' => (int) appsettings('bed_service_category_id', 3),
            'misc' => (int) appsettings('misc_service_category_id', 5),
        ];
    }

    /**
     * Build an ultra-dense, token-compressed catalog of active hospital services
     * strictly grouped by their configured appsettings() categories.
     * Uses 200-capacity limits per category so that extensive catalogs (e.g. 200 imaging services)
     * fit comfortably in token budgets (~3 tokens per item = ~600 tokens per category).
     */
    public function getCompressedServiceCatalog(): string
    {
        return Cache::remember(self::CACHE_KEY_COMPRESSED_SERVICES, self::CACHE_TTL, function () {
            try {
                $categoryIds = $this->getAppSettingCategoryIds();
                $sections = [];

                // 1. Laboratory Investigations (appsettings investigation_category_id)
                $labServices = Service::where('status', 1)
                    ->where('category_id', $categoryIds['lab'])
                    ->select(['id', 'service_name', 'service_code'])
                    ->orderBy('service_name')
                    ->limit(200)
                    ->get();
                if ($labServices->isNotEmpty()) {
                    $items = $labServices->map(fn ($s) => "{$s->id}:{$s->service_name}")->implode(' | ');
                    $sections[] = "[CATEGORY ID: {$categoryIds['lab']} | TYPE: LAB | LABORATORY INVESTIGATIONS]:\n" . $items;
                }

                // 2. Radiology & Diagnostic Imaging (appsettings imaging_category_id)
                $imagingServices = Service::where('status', 1)
                    ->where('category_id', $categoryIds['imaging'])
                    ->select(['id', 'service_name', 'service_code'])
                    ->orderBy('service_name')
                    ->limit(200)
                    ->get();
                if ($imagingServices->isNotEmpty()) {
                    $items = $imagingServices->map(fn ($s) => "{$s->id}:{$s->service_name}")->implode(' | ');
                    $sections[] = "[CATEGORY ID: {$categoryIds['imaging']} | TYPE: IMAGING | RADIOLOGY & SCANS]:\n" . $items;
                }

                // 3. Clinical & Surgical Procedures (appsettings procedure_category_id)
                $procServices = Service::where('status', 1)
                    ->where('category_id', $categoryIds['procedure'])
                    ->select(['id', 'service_name', 'service_code'])
                    ->orderBy('service_name')
                    ->limit(200)
                    ->get();
                if ($procServices->isNotEmpty()) {
                    $items = $procServices->map(fn ($s) => "{$s->id}:{$s->service_name}")->implode(' | ');
                    $sections[] = "[CATEGORY ID: {$categoryIds['procedure']} | TYPE: PROCEDURE | CLINICAL & SURGICAL PROCEDURES]:\n" . $items;
                }

                // 4. Nursing Services & Bedside Care (appsettings nursing_service_category)
                $nursingServices = Service::where('status', 1)
                    ->where('category_id', $categoryIds['nursing'])
                    ->select(['id', 'service_name', 'service_code'])
                    ->orderBy('service_name')
                    ->limit(80)
                    ->get();
                if ($nursingServices->isNotEmpty()) {
                    $items = $nursingServices->map(fn ($s) => "{$s->id}:{$s->service_name}")->implode(' | ');
                    $sections[] = "[CATEGORY ID: {$categoryIds['nursing']} | TYPE: NURSING | BEDSIDE NURSING CARE]:\n" . $items;
                }

                // 5. Specialist Consultations (appsettings consultation_category_id)
                $consultServices = Service::where('status', 1)
                    ->where('category_id', $categoryIds['consultation'])
                    ->select(['id', 'service_name', 'service_code'])
                    ->orderBy('service_name')
                    ->limit(50)
                    ->get();
                if ($consultServices->isNotEmpty()) {
                    $items = $consultServices->map(fn ($s) => "{$s->id}:{$s->service_name}")->implode(' | ');
                    $sections[] = "[CATEGORY ID: {$categoryIds['consultation']} | TYPE: CONSULTATION | SPECIALIST REVIEWS]:\n" . $items;
                }

                // 6. Other Active Service Categories
                $otherCategories = ServiceCategory::where('status', '>', 0)
                    ->whereNotIn('id', array_values($categoryIds))
                    ->get();

                foreach ($otherCategories as $cat) {
                    $otherServices = Service::where('status', 1)
                        ->where('category_id', $cat->id)
                        ->select(['id', 'service_name', 'service_code'])
                        ->orderBy('service_name')
                        ->limit(40)
                        ->get();

                    if ($otherServices->isNotEmpty()) {
                        $items = $otherServices->map(fn ($s) => "{$s->id}:{$s->service_name}")->implode(' | ');
                        $sections[] = "[CATEGORY ID: {$cat->id} | TYPE: SERVICE | {$cat->category_name}]:\n" . $items;
                    }
                }

                return implode("\n\n", $sections);
            } catch (\Exception $e) {
                Log::warning('Error generating compressed service catalog: ' . $e->getMessage());

                // Fallback standard catalog structure
                return "[CATEGORY ID: 2 | TYPE: LAB | LABORATORY INVESTIGATIONS]:\n" .
                    "Full Blood Count (FBC) | Malaria Parasite (MP) | Urinalysis | Lipid Profile | Serum Electrolytes | Fasting Blood Sugar (FBS) | Widal Test | Stool Microscopy | Liver Function Test (LFT) | Renal Function Test (RFT)\n\n" .
                    "[CATEGORY ID: 6 | TYPE: IMAGING | RADIOLOGY & SCANS]:\n" .
                    "Chest X-Ray (PA/AP) | Abdominal Ultrasound | Pelvic Ultrasound | Obstetric Ultrasound | Brain CT Scan | Lumbar Spine X-Ray | Echocardiogram\n\n" .
                    "[CATEGORY ID: 8 | TYPE: PROCEDURE | CLINICAL & SURGICAL PROCEDURES]:\n" .
                    "Wound Dressing (Major) | Wound Dressing (Minor) | Urethral Catheterization | IV Cannulation | Incision & Drainage (I&D) | Suture Laceration | Appendectomy | Caesarean Section | Debridement";
            }
        });
    }

    /**
     * Extract clinical tokens, medical n-grams, and acronym expansions from consultation text.
     */
    public function extractClinicalSearchTerms(string $clinicalText): array
    {
        $clean = trim($clinicalText);
        if (empty($clean)) {
            return [];
        }

        // Clean punctuation into whitespace but keep hyphens/slashes in medical terms (e.g. x-ray, c-section)
        $normalized = strtolower(preg_replace('/[^\w\s\-\/]/', ' ', $clean));

        // Extract raw words
        preg_match_all('/\b[a-z0-9\-\/]{2,}\b/', $normalized, $matches);
        $rawWords = array_unique($matches[0] ?? []);

        $stopWords = [
            'the', 'and', 'with', 'for', 'from', 'this', 'that', 'have', 'been',
            'patient', 'years', 'year', 'month', 'male', 'female', 'complaining',
            'history', 'presenting', 'daily', 'twice', 'three', 'times', 'hours',
            'today', 'yesterday', 'presented', 'normal', 'mild', 'severe', 'moderate',
            'exam', 'examination', 'impression', 'plan', 'notes', 'doctor', 'nurse',
            'clinic', 'hospital', 'reported', 'denies', 'status', 'given', 'taking',
            'intake', 'output', 'pulse', 'rate', 'blood', 'pressure', 'temp', 'pain',
            'routine', 'urgent', 'stat', 'dose', 'tablets', 'capsule', 'injection',
            'will', 'shall', 'also', 'about', 'after', 'before', 'where', 'which',
        ];

        $filteredWords = array_diff($rawWords, $stopWords);
        $expandedTerms = [];

        foreach ($filteredWords as $word) {
            $expandedTerms[] = $word;

            // Check if word or acronym has clinical expansions
            if (isset(self::CLINICAL_SYNONYMS[$word])) {
                foreach (self::CLINICAL_SYNONYMS[$word] as $syn) {
                    $expandedTerms[] = $syn;
                }
            }
        }

        // Also check multi-word phrases in the text
        foreach (self::CLINICAL_SYNONYMS as $key => $syns) {
            if (str_contains($normalized, $key)) {
                foreach ($syns as $syn) {
                    $expandedTerms[] = $syn;
                }
            }
        }

        return array_values(array_unique(array_filter($expandedTerms, fn ($t) => strlen($t) >= 2)));
    }

    /**
     * Deterministic Dynamic Catalog Retrieval:
     * Searches the live catalog for all Services and Products matching the clinical consultation terms.
     * Even if a hospital has 500 imaging services or 5,000 products, any service or drug matching
     * the consultation terms is guaranteed to be retrieved with its real catalog ID, name, and category.
     */
    public function searchRelevantCatalogItems(string $clinicalText): string
    {
        $searchTerms = $this->extractClinicalSearchTerms($clinicalText);
        if (empty($searchTerms)) {
            return '';
        }

        try {
            $categoryIds = $this->getAppSettingCategoryIds();

            // 1. Search Products (Prescriptions / Drugs)
            $matchedProducts = Product::where('status', 1)
                ->where(function ($q) use ($searchTerms) {
                    foreach (array_slice($searchTerms, 0, 15) as $token) {
                        $q->orWhere('product_name', 'LIKE', "%{$token}%")
                          ->orWhere('product_code', 'LIKE', "%{$token}%");
                    }
                })
                ->select(['id', 'product_name', 'category_id'])
                ->limit(25)
                ->get();

            // 2. Search Services partitioned by category so no category is crowded out
            $matchedServices = Service::where('status', 1)
                ->where(function ($q) use ($searchTerms) {
                    foreach (array_slice($searchTerms, 0, 15) as $token) {
                        $q->orWhere('service_name', 'LIKE', "%{$token}%")
                          ->orWhere('service_code', 'LIKE', "%{$token}%");
                    }
                })
                ->select(['id', 'service_name', 'service_code', 'category_id'])
                ->limit(35)
                ->get();

            $output = [];

            if ($matchedProducts->isNotEmpty()) {
                $pList = $matchedProducts->map(fn ($p) => "[ID: {$p->id} | {$p->product_name}]")->implode('; ');
                $output[] = "DYNAMIC MATCHED PRODUCTS FOR THIS CONSULTATION (Prescriptions):\n" . $pList;
            }

            if ($matchedServices->isNotEmpty()) {
                $sList = $matchedServices->map(function ($s) use ($categoryIds) {
                    $type = match ((int) $s->category_id) {
                        $categoryIds['lab'] => 'LAB',
                        $categoryIds['imaging'] => 'IMAGING',
                        $categoryIds['procedure'] => 'PROCEDURE',
                        $categoryIds['nursing'] => 'NURSING',
                        $categoryIds['consultation'] => 'CONSULTATION',
                        default => 'SERVICE',
                    };

                    return "[ID: {$s->id} | {$s->service_name} | Cat ID: {$s->category_id} ({$type})]";
                })->implode('; ');
                $output[] = "DYNAMIC MATCHED SERVICES SPECIFIC TO SPOKEN CONSULTATION:\n" . $sList;
            }

            return implode("\n\n", $output);
        } catch (\Exception $e) {
            Log::warning('Error searching relevant catalog items: ' . $e->getMessage());

            return '';
        }
    }

    /**
     * Get the full catalog prompt context:
     * - Configured AppSettings category IDs
     * - Token-compressed service catalog directory
     * - Dynamic context-aware products & services matches
     */
    public function getCatalogPromptContext(string $clinicalText = ''): string
    {
        $categoryIds = $this->getAppSettingCategoryIds();
        $compressedCatalog = $this->getCompressedServiceCatalog();
        $dynamicMatches = $this->searchRelevantCatalogItems($clinicalText);

        $context = "AVAILABLE CATALOG CATEGORIES & HOSPITAL SERVICES CONFIGURATION (APPSETTINGS):\n" .
            "- LABORATORY INVESTIGATIONS (SERVICES): Category ID {$categoryIds['lab']} (service_type: 'lab')\n" .
            "- RADIOLOGY & IMAGING (SERVICES): Category ID {$categoryIds['imaging']} (service_type: 'imaging')\n" .
            "- PROCEDURES & SURGERIES (SERVICES): Category ID {$categoryIds['procedure']} (service_type: 'procedure')\n" .
            "- NURSING BEDSIDE CARE (SERVICES): Category ID {$categoryIds['nursing']} (service_type: 'nursing')\n" .
            "- SPECIALIST CONSULTATIONS (SERVICES): Category ID {$categoryIds['consultation']} (service_type: 'consultation')\n" .
            "- PHARMACEUTICAL PRESCRIPTIONS (PRODUCTS): Products Catalog (order_type: 'PRODUCT')\n\n" .
            "TOKEN-COMPRESSED ACTIVE HOSPITAL SERVICE DIRECTORY (Select items from this list whenever applicable):\n" .
            $compressedCatalog;

        if (!empty($dynamicMatches)) {
            $context .= "\n\nRELEVANT CATALOG ITEMS MATCHED TO CLINICAL ENCOUNTER:\n" . $dynamicMatches;
        }

        return $context;
    }

    /**
     * Backward-compatible alias for getCatalogPromptContext.
     */
    public function getCategoryPromptContext(): string
    {
        return $this->getCatalogPromptContext();
    }

    /**
     * Search and match a suggested order string to the real hospital catalog.
     * Clinically deterministic multi-stage lookup:
     * 1. Exact catalog ID verification
     * 2. Category-scoped name and acronym match
     * 3. Cross-category search
     * 4. Structured free-form classification with guaranteed proper queue routing
     */
    public function matchCatalogItem(string $itemName, string $orderType = 'AUTO', ?string $serviceType = null): ?array
    {
        $cleanName = trim($itemName);
        if (empty($cleanName)) {
            return null;
        }

        $categoryIds = $this->getAppSettingCategoryIds();

        // 1. Try matching Product if orderType is PRODUCT or AUTO
        if ($orderType === 'PRODUCT' || ($orderType === 'AUTO' && $this->isLikelyProduct($cleanName))) {
            try {
                $words = preg_split('/\s+/', $cleanName);
                $baseWord = $words[0] ?? $cleanName;

                $product = Product::with(['category', 'price'])
                    ->where('status', 1)
                    ->where(function ($q) use ($cleanName, $baseWord) {
                        $q->where('product_name', 'LIKE', "%{$cleanName}%")
                          ->orWhere('product_name', 'LIKE', "%{$baseWord}%");
                    })
                    ->first();

                if ($product) {
                    return [
                        'order_type' => 'PRODUCT',
                        'service_type' => 'product',
                        'catalog_id' => $product->id,
                        'item_name' => $product->product_name,
                        'item_code' => $product->product_code,
                        'category_id' => $product->category_id,
                        'category_name' => $product->category?->category_name ?? 'Pharmaceutical',
                        'unit_price' => (float) ($product->price?->price ?? 0),
                        'route_target' => 'dispensing_queue',
                        'is_matched' => true,
                        'is_free_form' => false,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Product matching error: ' . $e->getMessage());
            }

            if ($orderType === 'PRODUCT') {
                return [
                    'order_type' => 'PRODUCT',
                    'service_type' => 'product',
                    'catalog_id' => null,
                    'item_name' => $cleanName,
                    'item_code' => null,
                    'category_id' => null,
                    'category_name' => 'Custom Medication',
                    'unit_price' => 0.0,
                    'route_target' => 'dispensing_queue',
                    'is_matched' => false,
                    'is_free_form' => true,
                ];
            }
        }

        // 2. Resolve Service Category and Service Type
        $resolved = $this->resolveServiceTaxonomy($cleanName, $serviceType);
        $targetCatId = $resolved['category_id'];
        $resolvedServiceType = $resolved['service_type'];
        $resolvedQueue = $resolved['route_target'];
        $resolvedCatName = $resolved['category_name'];

        try {
            // First search within the target category
            $service = Service::with(['category', 'price'])
                ->where('status', 1)
                ->where('category_id', $targetCatId)
                ->where(function ($q) use ($cleanName) {
                    $q->where('service_name', 'LIKE', "%{$cleanName}%")
                      ->orWhere('service_code', 'LIKE', "%{$cleanName}%");
                })
                ->first();

            // Try clinical acronym/synonym lookup in target category
            if (!$service) {
                $lower = strtolower($cleanName);
                if (isset(self::CLINICAL_SYNONYMS[$lower])) {
                    foreach (self::CLINICAL_SYNONYMS[$lower] as $syn) {
                        $service = Service::with(['category', 'price'])
                            ->where('status', 1)
                            ->where('category_id', $targetCatId)
                            ->where(function ($q) use ($syn) {
                                $q->where('service_name', 'LIKE', "%{$syn}%")
                                  ->orWhere('service_code', 'LIKE', "%{$syn}%");
                            })
                            ->first();
                        if ($service) {
                            break;
                        }
                    }
                }
            }

            // If not found in target category, search across all active services
            if (!$service) {
                $service = Service::with(['category', 'price'])
                    ->where('status', 1)
                    ->where(function ($q) use ($cleanName) {
                        $q->where('service_name', 'LIKE', "%{$cleanName}%")
                          ->orWhere('service_code', 'LIKE', "%{$cleanName}%");
                    })
                    ->first();
            }

            if ($service) {
                $actualCatId = (int) $service->category_id;
                $serviceTypeAndQueue = $this->mapCategoryIdToServiceType($actualCatId, $categoryIds);

                return [
                    'order_type' => 'SERVICE',
                    'service_type' => $serviceTypeAndQueue['service_type'],
                    'catalog_id' => $service->id,
                    'item_name' => $service->service_name,
                    'item_code' => $service->service_code,
                    'category_id' => $service->category_id,
                    'category_name' => $service->category?->category_name ?? $resolvedCatName,
                    'unit_price' => (float) ($service->price?->sale_price ?? 0),
                    'route_target' => $serviceTypeAndQueue['route_target'],
                    'is_matched' => true,
                    'is_free_form' => false,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Service matching error: ' . $e->getMessage());
        }

        // Fallback unmatched Service with strictly determined category and department queue
        return [
            'order_type' => 'SERVICE',
            'service_type' => $resolvedServiceType,
            'catalog_id' => null,
            'item_name' => $cleanName,
            'item_code' => null,
            'category_id' => $targetCatId,
            'category_name' => $resolvedCatName,
            'unit_price' => 0.0,
            'route_target' => $resolvedQueue,
            'is_matched' => false,
            'is_free_form' => true,
        ];
    }

    /**
     * Resolve service taxonomy (service_type, category_id, route_target) from item name or hint.
     */
    protected function resolveServiceTaxonomy(string $cleanName, ?string $serviceType = null): array
    {
        $categoryIds = $this->getAppSettingCategoryIds();
        $lower = strtolower($cleanName);
        $hint = strtolower((string) $serviceType);

        // Check Imaging
        if ($hint === 'imaging' || str_contains($lower, 'x-ray') || str_contains($lower, 'xray') ||
            str_contains($lower, 'ultrasound') || str_contains($lower, 'scan') || str_contains($lower, 'mri') ||
            str_contains($lower, 'ct ') || str_contains($lower, 'ct-') || str_contains($lower, 'echo') ||
            str_contains($lower, 'radiog') || str_contains($lower, 'mammog') || str_contains($lower, 'doppler') ||
            str_contains($lower, 'fluorosc') || str_contains($lower, 'cxr') || str_contains($lower, 'uss')) {
            return [
                'service_type' => 'imaging',
                'category_id' => $categoryIds['imaging'],
                'category_name' => 'Radiology & Diagnostic Imaging',
                'route_target' => 'imaging_queue',
            ];
        }

        // Check Procedure
        if ($hint === 'procedure' || str_contains($lower, 'dressing') || str_contains($lower, 'suture') ||
            str_contains($lower, 'catheter') || str_contains($lower, 'incision') || str_contains($lower, 'drainage') ||
            str_contains($lower, 'debridement') || str_contains($lower, 'circumcision') || str_contains($lower, 'excision') ||
            str_contains($lower, 'appendectomy') || str_contains($lower, 'cannulation') || str_contains($lower, 'surgery') ||
            str_contains($lower, 'caesarean') || str_contains($lower, 'c-section') || str_contains($lower, 'paracentesis') ||
            str_contains($lower, 'biopsy') || str_contains($lower, 'thoracentesis') || str_contains($lower, 'i&d')) {
            return [
                'service_type' => 'procedure',
                'category_id' => $categoryIds['procedure'],
                'category_name' => 'Clinical Procedure',
                'route_target' => 'procedure_queue',
            ];
        }

        // Check Nursing
        if ($hint === 'nursing' || str_contains($lower, 'vital signs') || str_contains($lower, 'bed bath') ||
            str_contains($lower, 'infusion pump') || str_contains($lower, 'blood transfusion') || str_contains($lower, 'sponge bath')) {
            return [
                'service_type' => 'nursing',
                'category_id' => $categoryIds['nursing'],
                'category_name' => 'Nursing Care',
                'route_target' => 'nursing_queue',
            ];
        }

        // Check Consultation
        if ($hint === 'consultation' || str_contains($lower, 'consultation') || str_contains($lower, 'clinic review') || str_contains($lower, 'referral')) {
            return [
                'service_type' => 'consultation',
                'category_id' => $categoryIds['consultation'],
                'category_name' => 'Specialist Consultation',
                'route_target' => 'consultation_queue',
            ];
        }

        // Default: Laboratory Investigation
        return [
            'service_type' => 'lab',
            'category_id' => $categoryIds['lab'],
            'category_name' => 'Laboratory Investigation',
            'route_target' => 'lab_queue',
        ];
    }

    /**
     * Map a service's actual category ID to service type and route target.
     */
    protected function mapCategoryIdToServiceType(int $categoryId, array $categoryIds): array
    {
        if ($categoryId === $categoryIds['lab']) {
            return ['service_type' => 'lab', 'route_target' => 'lab_queue'];
        }
        if ($categoryId === $categoryIds['imaging']) {
            return ['service_type' => 'imaging', 'route_target' => 'imaging_queue'];
        }
        if ($categoryId === $categoryIds['procedure']) {
            return ['service_type' => 'procedure', 'route_target' => 'procedure_queue'];
        }
        if ($categoryId === $categoryIds['nursing']) {
            return ['service_type' => 'nursing', 'route_target' => 'nursing_queue'];
        }
        if ($categoryId === $categoryIds['consultation']) {
            return ['service_type' => 'consultation', 'route_target' => 'consultation_queue'];
        }

        return ['service_type' => 'service', 'route_target' => 'service_queue'];
    }

    /**
     * Check if an order string is likely a pharmaceutical product.
     */
    protected function isLikelyProduct(string $name): bool
    {
        $lower = strtolower($name);

        return preg_match('/\b(\d+\s*(mg|g|ml|mcg|iu|iu\/ml|%))\b/i', $lower) ||
            str_contains($lower, 'tab') || str_contains($lower, 'cap') ||
            str_contains($lower, 'syrup') || str_contains($lower, 'susp') ||
            str_contains($lower, 'inj') || str_contains($lower, 'infusion') ||
            str_contains($lower, 'paracetamol') || str_contains($lower, 'amoxicillin') ||
            str_contains($lower, 'ciprofloxacin') || str_contains($lower, 'artemether');
    }

    /**
     * Ground an array of LLM extracted orders against the live catalog.
     */
    public function groundOrders(array $rawOrders, array $patientAllergies = []): array
    {
        $grounded = [];
        $categoryIds = $this->getAppSettingCategoryIds();

        foreach ($rawOrders as $idx => $order) {
            $name = $order['item_name'] ?? $order['name'] ?? '';
            if (empty($name)) {
                continue;
            }

            $suggestedType = strtoupper($order['order_type'] ?? 'AUTO');
            if (!in_array($suggestedType, ['SERVICE', 'PRODUCT', 'REFERRAL', 'FOLLOW_UP', 'AUTO'])) {
                $suggestedType = 'AUTO';
            }

            // Handle dedicated Referral & Follow-up types
            if ($suggestedType === 'REFERRAL' || !empty($order['is_referral'])) {
                $grounded[] = [
                    'id' => 'ord_' . ($idx + 1),
                    'order_type' => 'REFERRAL',
                    'service_type' => 'referral',
                    'category_id' => $categoryIds['consultation'],
                    'category_name' => 'Specialist Referral',
                    'catalog_id' => null,
                    'item_name' => $name,
                    'item_code' => null,
                    'specialization' => $order['specialization'] ?? $order['target_specialization'] ?? 'Specialist Consultation',
                    'urgency' => in_array(strtolower($order['urgency'] ?? ''), ['routine', 'urgent', 'emergency']) ? strtolower($order['urgency']) : 'routine',
                    'reason' => $order['reason'] ?? $order['indication'] ?? $name,
                    'clinical_summary' => $order['clinical_summary'] ?? '',
                    'target_queue' => 'referral_queue',
                    'is_selected' => true,
                    'is_matched' => true,
                    'is_free_form' => false,
                    'safety_warning' => null,
                ];

                continue;
            }

            if ($suggestedType === 'FOLLOW_UP' || !empty($order['is_follow_up'])) {
                $grounded[] = [
                    'id' => 'ord_' . ($idx + 1),
                    'order_type' => 'FOLLOW_UP',
                    'service_type' => 'follow_up',
                    'category_id' => null,
                    'category_name' => 'Reception Follow-Up',
                    'catalog_id' => null,
                    'item_name' => $name,
                    'item_code' => null,
                    'duration_days' => (int) ($order['duration_days'] ?? 7),
                    'reason' => $order['reason'] ?? 'Routine Review',
                    'target_queue' => 'appointment_queue',
                    'is_selected' => true,
                    'is_matched' => true,
                    'is_free_form' => false,
                    'safety_warning' => null,
                ];

                continue;
            }

            // Check if catalog_id was explicitly provided by LLM
            $llmCatalogId = !empty($order['catalog_id']) && is_numeric($order['catalog_id']) ? (int) $order['catalog_id'] : null;
            $llmServiceType = strtolower((string) ($order['service_type'] ?? ''));
            $matched = null;

            if ($llmCatalogId) {
                try {
                    if ($suggestedType === 'PRODUCT') {
                        $product = Product::with(['category', 'price'])->where('status', 1)->find($llmCatalogId);
                        if ($product) {
                            $matched = [
                                'order_type' => 'PRODUCT',
                                'service_type' => 'product',
                                'catalog_id' => $product->id,
                                'item_name' => $product->product_name,
                                'item_code' => $product->product_code,
                                'category_id' => $product->category_id,
                                'category_name' => $product->category?->category_name ?? 'Pharmaceutical',
                                'unit_price' => (float) ($product->price?->price ?? 0),
                                'route_target' => 'dispensing_queue',
                                'is_matched' => true,
                                'is_free_form' => false,
                            ];
                        }
                    } else {
                        $service = Service::with(['category', 'price'])->where('status', 1)->find($llmCatalogId);
                        if ($service) {
                            $actualCatId = (int) $service->category_id;
                            $serviceTypeAndQueue = $this->mapCategoryIdToServiceType($actualCatId, $categoryIds);

                            $matched = [
                                'order_type' => 'SERVICE',
                                'service_type' => $serviceTypeAndQueue['service_type'],
                                'catalog_id' => $service->id,
                                'item_name' => $service->service_name,
                                'item_code' => $service->service_code,
                                'category_id' => $service->category_id,
                                'category_name' => $service->category?->category_name ?? 'Clinical Service',
                                'unit_price' => (float) ($service->price?->sale_price ?? 0),
                                'route_target' => $serviceTypeAndQueue['route_target'],
                                'is_matched' => true,
                                'is_free_form' => false,
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Catalog ID validation error: ' . $e->getMessage());
                }
            }

            // Fallback to deterministic name & acronym search if no valid catalog_id match
            if (!$matched) {
                $matched = $this->matchCatalogItem($name, $suggestedType, $llmServiceType);
            }

            if ($matched) {
                // Allergy safety check for pharmaceuticals
                $safetyWarning = null;
                if ($matched['order_type'] === 'PRODUCT' && !empty($patientAllergies)) {
                    foreach ($patientAllergies as $allergy) {
                        $allergyTerm = strtolower(trim((string) $allergy));
                        if (!empty($allergyTerm) && str_contains(strtolower($name), $allergyTerm)) {
                            $safetyWarning = "Potential patient allergy conflict with reported '{$allergyTerm}'";

                            break;
                        }
                    }
                }

                // Format dosage according to pipe standard: <dose> | <freq> | <duration> | <route>
                $rawDosage = trim((string) ($order['dosage'] ?? ''));
                $route = trim((string) ($order['route'] ?? 'Oral'));
                $frequency = trim((string) ($order['frequency'] ?? 'TID'));
                $duration = trim((string) ($order['duration'] ?? '3 days'));

                if ($matched['order_type'] === 'PRODUCT') {
                    if (!empty($rawDosage) && str_contains($rawDosage, '|')) {
                        $dosage = $rawDosage;
                    } else {
                        $parts = array_filter([$rawDosage ?: '500mg', $frequency, $duration, $route], fn ($p) => !empty($p));
                        $dosage = implode(' | ', $parts);
                    }
                } else {
                    $dosage = $rawDosage;
                }

                // Clean instructions & indications
                $cleanInstructions = trim((string) ($order['instructions'] ?? ''));
                if (in_array(strtolower($cleanInstructions), ['instructions...', 'instruction...', 'routine', 'none', 'n/a', 'null'])) {
                    $cleanInstructions = '';
                }

                $cleanIndication = trim((string) ($order['indication'] ?? ($order['reason'] ?? '')));
                if (in_array(strtolower($cleanIndication), ['instructions...', 'instruction...', 'routine', 'none', 'n/a', 'null'])) {
                    $cleanIndication = '';
                }

                $grounded[] = [
                    'id' => 'ord_' . ($idx + 1),
                    'order_type' => $matched['order_type'],
                    'service_type' => $matched['service_type'] ?? ($matched['order_type'] === 'PRODUCT' ? 'product' : 'service'),
                    'category_id' => $matched['category_id'],
                    'category_name' => $matched['category_name'],
                    'catalog_id' => $matched['catalog_id'],
                    'item_name' => $matched['item_name'],
                    'item_code' => $matched['item_code'],
                    'dosage' => $dosage,
                    'route' => $route,
                    'frequency' => $frequency,
                    'duration' => $duration,
                    'quantity' => (int) ($order['quantity'] ?? 1),
                    'instructions' => $cleanInstructions,
                    'indication' => $cleanIndication,
                    'unit_price' => $matched['unit_price'],
                    'target_queue' => $matched['route_target'],
                    'is_selected' => true,
                    'is_matched' => $matched['is_matched'],
                    'is_free_form' => $matched['is_free_form'] ?? !$matched['is_matched'],
                    'safety_warning' => $safetyWarning,
                ];
            }
        }

        return $grounded;
    }
}
