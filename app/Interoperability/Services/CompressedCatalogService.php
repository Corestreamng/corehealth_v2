<?php

namespace App\Interoperability\Services;

use Illuminate\Support\Facades\Log;

/**
 * Service for parsing, validating, serializing, and matching
 * ultra-dense, token-compressed partner EMR catalogs (CSCP - Compressed Scribe Catalog Protocol).
 *
 * Reduces catalog payload size by 10x-15x compared to verbose JSON, allowing
 * thousands of partner EMR services and medications to be ingested within strict LLM token limits.
 */
class CompressedCatalogService
{
    /**
     * Common clinical acronyms and synonyms for partner matching.
     */
    protected const CLINICAL_SYNONYMS = [
        'cxr' => ['chest x-ray', 'chest radiograph', 'chest'],
        'xray' => ['x-ray', 'radiograph'],
        'uss' => ['ultrasound', 'sonogram', 'scan'],
        'fbc' => ['full blood count', 'cbc', 'complete blood count'],
        'cbc' => ['full blood count', 'complete blood count'],
        'pcv' => ['packed cell volume', 'haemoglobin', 'hemoglobin'],
        'mp' => ['malaria parasite', 'malaria rapid test', 'blood film'],
        'e/u/cr' => ['electrolytes', 'urea', 'creatinine', 'renal function'],
        'eucr' => ['electrolytes', 'urea', 'creatinine', 'renal function'],
        'lft' => ['liver function test', 'bilirubin', 'transaminase'],
        'rft' => ['renal function test', 'electrolytes', 'creatinine'],
        'fbs' => ['fasting blood sugar', 'glucose'],
        'rbs' => ['random blood sugar', 'glucose'],
        'pt' => ['prothrombin time', 'pregnancy test', 'upt'],
        'upt' => ['urine pregnancy test', 'pregnancy test'],
        'msu' => ['midstream urine', 'urinalysis', 'urine mcs'],
        'mcs' => ['microscopy culture', 'sensitivity', 'culture'],
        'widal' => ['widal test', 'typhoid'],
        'ct' => ['computed tomography', 'ct scan'],
        'mri' => ['magnetic resonance imaging', 'mri scan'],
        'ecg' => ['electrocardiogram', 'ekg'],
        'ekg' => ['electrocardiogram', 'ecg'],
    ];

    /**
     * Parse compressed catalog payload (CSCP string, gzip-base64, or JSON).
     */
    public function parse(string|array|null $payload): array
    {
        if (empty($payload)) {
            return ['categories' => [], 'items_flat' => []];
        }

        // 1. Array input
        if (is_array($payload)) {
            return $this->normalizeArrayCatalog($payload);
        }

        $raw = trim($payload);

        // 2. Gzip Base64 payload detection
        if (str_starts_with($raw, 'H4sI') || str_starts_with($raw, 'eJ')) {
            try {
                $decoded = base64_decode($raw, true);
                if ($decoded) {
                    $unzipped = @gzdecode($decoded);
                    if ($unzipped === false) {
                        $unzipped = @gzuncompress($decoded);
                    }
                    if ($unzipped !== false) {
                        $raw = trim($unzipped);
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('CompressedCatalogService: Gzip decompression skipped: ' . $e->getMessage());
            }
        }

        // 3. JSON input detection
        if (str_starts_with($raw, '{') || str_starts_with($raw, '[')) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $this->normalizeArrayCatalog($json);
            }
        }

        // 4. CSCP Plaintext DSL Parser
        return $this->parseCscpText($raw);
    }

    /**
     * Parse CSCP (Compressed Scribe Catalog Protocol) format.
     * Line format:
     *   #CAT:<cat_id>|<type:LAB|RAD|PROC|NURS|RX|CONSULT>|<category_name>
     *   <item_id>:<item_name>|<item_id>:<item_name>
     */
    public function parseCscpText(string $text): array
    {
        $categories = [];
        $itemsFlat = [];
        $lines = preg_split('/\r\n|\r|\n/', $text);

        $currentCat = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '//') || str_starts_with($line, ';')) {
                continue;
            }

            // Category Header: #CAT:id|type|name
            if (str_starts_with($line, '#CAT:') || str_starts_with($line, '[CATEGORY:')) {
                $cleanHeader = preg_replace('/^(?:#CAT:|\[CATEGORY:)/', '', rtrim($line, ']'));
                $parts = explode('|', $cleanHeader);

                $catId = trim($parts[0] ?? ('cat_' . (count($categories) + 1)));
                $catType = strtoupper(trim($parts[1] ?? 'SERVICE'));
                $catName = trim($parts[2] ?? $catId);

                $normType = $this->normalizeCategoryType($catType);
                $queue = $this->resolveQueueForType($normType);

                $currentCat = [
                    'id' => $catId,
                    'type' => $normType,
                    'order_type' => $normType === 'RX' ? 'PRODUCT' : 'SERVICE',
                    'name' => $catName,
                    'target_queue' => $queue,
                    'items' => [],
                ];
                $categories[$catId] = &$currentCat;
                unset($currentCat);
                $currentCat = &$categories[$catId];

                continue;
            }

            // If no category header encountered yet, create default general category
            if ($currentCat === null) {
                $catId = 'general_catalog';
                $categories[$catId] = [
                    'id' => $catId,
                    'type' => 'SERVICE',
                    'order_type' => 'SERVICE',
                    'name' => 'General Clinical Catalog',
                    'target_queue' => 'general_queue',
                    'items' => [],
                ];
                $currentCat = &$categories[$catId];
            }

            // Parse Pipe-Delimited Items: id:name or id:code:name or name
            $rawItems = explode('|', $line);
            foreach ($rawItems as $rawItem) {
                $rawItem = trim($rawItem);
                if (empty($rawItem)) {
                    continue;
                }

                $itemObj = $this->parseCscpItemString($rawItem, $currentCat);
                $currentCat['items'][] = $itemObj;
                $itemsFlat[] = $itemObj;
            }
        }

        return [
            'categories' => array_values($categories),
            'items_flat' => $itemsFlat,
        ];
    }

    /**
     * Parse individual item segment (e.g. "101:Full Blood Count" or "101:LAB-01:Full Blood Count:2500").
     */
    protected function parseCscpItemString(string $itemStr, array $category): array
    {
        $parts = explode(':', $itemStr);
        $count = count($parts);

        $id = null;
        $code = null;
        $name = '';
        $price = 0.0;

        if ($count === 1) {
            $name = trim($parts[0]);
            $id = 'item_' . crc32($name);
        } elseif ($count === 2) {
            $id = trim($parts[0]);
            $name = trim($parts[1]);
        } elseif ($count === 3) {
            $id = trim($parts[0]);
            $code = trim($parts[1]);
            $name = trim($parts[2]);
        } else {
            $id = trim($parts[0]);
            $code = trim($parts[1]);
            $name = trim($parts[2]);
            $price = (float) trim($parts[3]);
        }

        return [
            'id' => $id,
            'catalog_id' => $id,
            'code' => $code,
            'name' => $name,
            'item_name' => $name,
            'category_id' => $category['id'],
            'category_name' => $category['name'],
            'category_type' => $category['type'],
            'order_type' => $category['order_type'],
            'service_type' => strtolower($category['type']),
            'target_queue' => $category['target_queue'],
            'unit_price' => $price,
            'is_matched' => true,
        ];
    }

    /**
     * Normalize JSON/Array structured catalog.
     */
    protected function normalizeArrayCatalog(array $data): array
    {
        $categories = [];
        $itemsFlat = [];

        // Support { categories: [...] } or direct list of categories or items
        $rawCats = $data['categories'] ?? ($data[0]['items'] ?? null ? $data : null);

        if ($rawCats && is_array($rawCats)) {
            foreach ($rawCats as $idx => $c) {
                $catId = $c['id'] ?? ('cat_' . ($idx + 1));
                $catType = $this->normalizeCategoryType($c['type'] ?? ($c['order_type'] ?? 'SERVICE'));
                $queue = $c['target_queue'] ?? $this->resolveQueueForType($catType);

                $catObj = [
                    'id' => $catId,
                    'type' => $catType,
                    'order_type' => in_array($catType, ['RX', 'PRODUCT', 'DRUG', 'PHARMACY']) ? 'PRODUCT' : 'SERVICE',
                    'name' => $c['name'] ?? $c['category_name'] ?? ('Category ' . $catId),
                    'target_queue' => $queue,
                    'items' => [],
                ];

                $rawItems = $c['items'] ?? [];
                foreach ($rawItems as $iIdx => $it) {
                    if (is_string($it)) {
                        $parsedItem = $this->parseCscpItemString($it, $catObj);
                    } else {
                        $id = $it['id'] ?? $it['catalog_id'] ?? ('i_' . ($iIdx + 1));
                        $name = $it['name'] ?? $it['item_name'] ?? '';
                        $parsedItem = [
                            'id' => $id,
                            'catalog_id' => $id,
                            'code' => $it['code'] ?? null,
                            'name' => $name,
                            'item_name' => $name,
                            'category_id' => $catId,
                            'category_name' => $catObj['name'],
                            'category_type' => $catObj['type'],
                            'order_type' => $catObj['order_type'],
                            'service_type' => strtolower($catObj['type']),
                            'target_queue' => $catObj['target_queue'],
                            'unit_price' => (float) ($it['price'] ?? ($it['unit_price'] ?? 0)),
                            'is_matched' => true,
                        ];
                    }
                    $catObj['items'][] = $parsedItem;
                    $itemsFlat[] = $parsedItem;
                }

                $categories[] = $catObj;
            }
        } elseif (isset($data['items']) && is_array($data['items'])) {
            // Flat items list
            $catObj = [
                'id' => 'custom_catalog',
                'type' => 'SERVICE',
                'order_type' => 'SERVICE',
                'name' => 'Custom Partner Catalog',
                'target_queue' => 'general_queue',
                'items' => [],
            ];
            foreach ($data['items'] as $it) {
                if (is_string($it)) {
                    $itemObj = $this->parseCscpItemString($it, $catObj);
                } else {
                    $type = $this->normalizeCategoryType($it['type'] ?? 'SERVICE');
                    $itemObj = [
                        'id' => $it['id'] ?? $it['catalog_id'] ?? 'item',
                        'catalog_id' => $it['id'] ?? $it['catalog_id'] ?? 'item',
                        'code' => $it['code'] ?? null,
                        'name' => $it['name'] ?? $it['item_name'] ?? '',
                        'item_name' => $it['name'] ?? $it['item_name'] ?? '',
                        'category_id' => $it['category_id'] ?? 'custom',
                        'category_name' => $it['category_name'] ?? 'General',
                        'category_type' => $type,
                        'order_type' => $type === 'RX' ? 'PRODUCT' : 'SERVICE',
                        'service_type' => strtolower($type),
                        'target_queue' => $it['target_queue'] ?? $this->resolveQueueForType($type),
                        'unit_price' => (float) ($it['price'] ?? 0),
                        'is_matched' => true,
                    ];
                }
                $catObj['items'][] = $itemObj;
                $itemsFlat[] = $itemObj;
            }
            $categories[] = $catObj;
        }

        return [
            'categories' => $categories,
            'items_flat' => $itemsFlat,
        ];
    }

    /**
     * Compress items or categories into CSCP format.
     */
    public function compress(array $itemsOrCategories): string
    {
        if (empty($itemsOrCategories)) {
            return '';
        }

        // If it's a list of categories
        if (isset($itemsOrCategories[0]['items'])) {
            return $this->serializeToCscp($itemsOrCategories);
        }

        // If it's a flat list of items, group them by type/category
        $grouped = [];
        foreach ($itemsOrCategories as $it) {
            $catType = $it['type'] ?? 'general';
            if (!isset($grouped[$catType])) {
                $grouped[$catType] = [
                    'id' => $catType,
                    'type' => $catType,
                    'name' => ucfirst($catType),
                    'items' => [],
                ];
            }
            $grouped[$catType]['items'][] = $it;
        }

        return $this->serializeToCscp(array_values($grouped));
    }

    /**
     * Serialize a categories collection into CSCP compact text.
     */
    public function serializeToCscp(array $categories): string
    {
        $lines = [];
        foreach ($categories as $cat) {
            $catId = $cat['id'] ?? 'c';
            $catType = $cat['type'] ?? ($cat['order_type'] ?? 'SERVICE');
            $catName = $cat['name'] ?? 'Category';
            $lines[] = "#CAT:{$catId}|{$catType}|{$catName}";

            $items = $cat['items'] ?? [];
            $itemTokens = [];
            foreach ($items as $it) {
                $id = $it['id'] ?? ($it['catalog_id'] ?? '');
                $name = $it['name'] ?? ($it['item_name'] ?? '');
                $code = $it['code'] ?? null;
                $price = !empty($it['unit_price']) ? $it['unit_price'] : (!empty($it['price']) ? $it['price'] : null);

                if ($price !== null && $code !== null) {
                    $itemTokens[] = "{$id}:{$code}:{$name}:{$price}";
                } elseif ($code !== null) {
                    $itemTokens[] = "{$id}:{$code}:{$name}";
                } else {
                    $itemTokens[] = "{$id}:{$name}";
                }
            }

            if (!empty($itemTokens)) {
                $lines[] = implode('|', $itemTokens);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Convert parsed custom catalog into an ultra-dense prompt context string.
     */
    public function toPromptContext(array $parsedCatalog): string
    {
        $categories = $parsedCatalog['categories'] ?? [];
        if (empty($categories)) {
            return '';
        }

        $sections = ["PARTNER EMR REGISTERED CATALOGS (Match extracted orders against these exact IDs and names):"];

        foreach ($categories as $cat) {
            $catId = $cat['id'];
            $catType = $cat['type'];
            $catName = $cat['name'];
            $items = $cat['items'] ?? [];

            if (empty($items)) {
                continue;
            }

            $itemStrings = array_map(function ($it) {
                return "{$it['id']}:{$it['name']}";
            }, $items);

            $sections[] = "[CATEGORY: {$catId} | TYPE: {$catType} | {$catName}]:\n" . implode(' | ', $itemStrings);
        }

        return implode("\n\n", $sections);
    }

    /**
     * Match a clinical term or drug string against the parsed partner catalog.
     */
    public function matchItem(string $term, array $parsedCatalog, string $typeHint = 'AUTO'): ?array
    {
        $cleanTerm = trim(strtolower($term));
        if (empty($cleanTerm)) {
            return null;
        }

        $items = $parsedCatalog['items_flat'] ?? [];
        if (empty($items)) {
            return null;
        }

        // 1. Exact match on ID or Name
        foreach ($items as $it) {
            if (strtolower($it['name']) === $cleanTerm || strval($it['id']) === $term) {
                return $it;
            }
        }

        // 2. Acronym & Synonym lookup
        if (isset(self::CLINICAL_SYNONYMS[$cleanTerm])) {
            foreach (self::CLINICAL_SYNONYMS[$cleanTerm] as $syn) {
                foreach ($items as $it) {
                    if (str_contains(strtolower($it['name']), $syn)) {
                        return $it;
                    }
                }
            }
        }

        // 3. Substring match
        foreach ($items as $it) {
            $itLower = strtolower($it['name']);
            if (str_contains($itLower, $cleanTerm) || str_contains($cleanTerm, $itLower)) {
                return $it;
            }
        }

        // 4. Token-based word overlap
        $words = array_filter(preg_split('/\s+/', $cleanTerm), fn ($w) => strlen($w) > 2);
        if (!empty($words)) {
            foreach ($items as $it) {
                $itLower = strtolower($it['name']);
                foreach ($words as $w) {
                    if (str_contains($itLower, $w)) {
                        return $it;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Normalize category type strings.
     */
    protected function normalizeCategoryType(string $type): string
    {
        $u = strtoupper(trim($type));

        return match ($u) {
            'LAB', 'LABORATORY', 'INVESTIGATION', 'INVESTIGATIONS' => 'LAB',
            'RAD', 'RADIOLOGY', 'IMAGING', 'XRAY', 'SCAN' => 'RAD',
            'PROC', 'PROCEDURE', 'PROCEDURES', 'SURGERY' => 'PROC',
            'NURS', 'NURSING', 'CARE' => 'NURS',
            'CONSULT', 'CONSULTATION', 'REFERRAL', 'REVIEW' => 'CONSULT',
            'RX', 'DRUG', 'DRUGS', 'PHARMACY', 'PRODUCT', 'MEDICATION', 'MEDICATIONS' => 'RX',
            default => 'SERVICE',
        };
    }

    /**
     * Resolve queue name for standardized category type.
     */
    protected function resolveQueueForType(string $type): string
    {
        return match ($type) {
            'LAB' => 'lab_queue',
            'RAD' => 'imaging_queue',
            'PROC' => 'procedure_queue',
            'NURS' => 'nursing_queue',
            'CONSULT' => 'consultation_queue',
            'RX' => 'dispensing_queue',
            default => 'general_queue',
        };
    }
}
