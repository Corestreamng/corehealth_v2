<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Support\Facades\Cache;

/**
 * Service for grounding AI Clinical Scribe orders in CoreHealth v2's
 * Products and Services catalogs via token-optimized categories and fuzzy matching.
 */
class OrderCatalogRegistryService
{
    protected const CACHE_TTL = 3600; // 1 hour
    protected const CACHE_KEY_CATEGORIES = 'ai_scribe_catalog_categories_v1';

    /**
     * Get a compact, token-optimized summary of available service & product categories
     * for injection into the LLM prompt.
     */
    public function getCategoryPromptContext(): string
    {
        return Cache::remember(self::CACHE_KEY_CATEGORIES, self::CACHE_TTL, function () {
            $serviceCats = ServiceCategory::where('status', '>', 0)
                ->select(['id', 'category_name', 'category_code'])
                ->orderBy('category_name')
                ->get()
                ->map(fn ($c) => "ID {$c->id}: {$c->category_name} (" . ($c->category_code ?: 'SVC') . ")")
                ->implode('; ');

            $productCats = ProductCategory::where('status', '>', 0)
                ->select(['id', 'category_name', 'category_code'])
                ->orderBy('category_name')
                ->get()
                ->map(fn ($c) => "ID {$c->id}: {$c->category_name} (" . ($c->category_code ?: 'PRD') . ")")
                ->implode('; ');

            return "AVAILABLE CATALOG CATEGORIES IN HOSPITAL SYSTEM:\n" .
                "1. SERVICES (Labs, Imaging, Procedures, Consultations, Referrals):\n" .
                ($serviceCats ?: 'General Services') . "\n" .
                "2. PRODUCTS (Prescriptions, Oral Solids, Injections, Infusions, Consumables):\n" .
                ($productCats ?: 'General Pharmaceuticals');
        });
    }

    /**
     * Search and match a suggested order string to the real hospital catalog.
     */
    public function matchCatalogItem(string $itemName, string $orderType = 'AUTO'): ?array
    {
        $cleanName = trim($itemName);
        if (empty($cleanName)) {
            return null;
        }

        // 1. Try matching Service if orderType is SERVICE or AUTO
        if ($orderType === 'SERVICE' || $orderType === 'AUTO') {
            $service = Service::with(['category', 'price'])
                ->where('status', 1)
                ->where(function ($q) use ($cleanName) {
                    $q->where('service_name', 'LIKE', "%{$cleanName}%")
                      ->orWhere('service_code', 'LIKE', "%{$cleanName}%");
                })
                ->first();

            if ($service) {
                $categoryName = $service->category?->category_name ?? 'General Service';
                $routeTarget = $this->resolveServiceRouteTarget($categoryName);

                return [
                    'order_type' => 'SERVICE',
                    'catalog_id' => $service->id,
                    'item_name' => $service->service_name,
                    'item_code' => $service->service_code,
                    'category_id' => $service->category_id,
                    'category_name' => $categoryName,
                    'unit_price' => (float) ($service->price?->sale_price ?? 0),
                    'route_target' => $routeTarget,
                    'is_matched' => true,
                ];
            }
        }

        // 2. Try matching Product if orderType is PRODUCT or AUTO
        if ($orderType === 'PRODUCT' || $orderType === 'AUTO') {
            // Extract drug base name if dosage is appended (e.g. "Paracetamol 500mg" -> "Paracetamol")
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
                    'catalog_id' => $product->id,
                    'item_name' => $product->product_name,
                    'item_code' => $product->product_code,
                    'category_id' => $product->category_id,
                    'category_name' => $product->category?->category_name ?? 'Pharmaceutical',
                    'unit_price' => (float) ($product->price?->price ?? 0),
                    'route_target' => 'dispensing_queue',
                    'is_matched' => true,
                ];
            }
        }

        // Fallback unmatched
        return [
            'order_type' => $orderType !== 'AUTO' ? $orderType : 'SERVICE',
            'catalog_id' => null,
            'item_name' => $cleanName,
            'item_code' => null,
            'category_id' => null,
            'category_name' => 'Custom Request',
            'unit_price' => 0.0,
            'route_target' => 'general_queue',
            'is_matched' => false,
        ];
    }

    /**
     * Map category to exact clinical route target.
     */
    protected function resolveServiceRouteTarget(string $categoryName): string
    {
        $lower = strtolower($categoryName);
        if (str_contains($lower, 'lab')) {
            return 'lab_queue';
        }
        if (str_contains($lower, 'imag') || str_contains($lower, 'radio') || str_contains($lower, 'x-ray') || str_contains($lower, 'ultra')) {
            return 'imaging_queue';
        }
        if (str_contains($lower, 'proc') || str_contains($lower, 'surg')) {
            return 'procedure_queue';
        }
        if (str_contains($lower, 'nurs') || str_contains($lower, 'ward') || str_contains($lower, 'admis')) {
            return 'nursing_queue';
        }
        if (str_contains($lower, 'refer') || str_contains($lower, 'consult')) {
            return 'referral_queue';
        }

        return 'service_queue';
    }

    /**
     * Ground an array of LLM extracted orders against the live catalog.
     */
    public function groundOrders(array $rawOrders, array $patientAllergies = []): array
    {
        $grounded = [];

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
                    'category_name' => 'Specialist Referral',
                    'item_name' => $name,
                    'specialization' => $order['specialization'] ?? $order['target_specialization'] ?? 'Specialist Consultation',
                    'urgency' => in_array(strtolower($order['urgency'] ?? ''), ['routine', 'urgent', 'emergency']) ? strtolower($order['urgency']) : 'routine',
                    'reason' => $order['reason'] ?? $order['indication'] ?? $name,
                    'clinical_summary' => $order['clinical_summary'] ?? '',
                    'target_queue' => 'referral_queue',
                    'is_selected' => true,
                    'is_matched' => true,
                    'safety_warning' => null,
                ];

                continue;
            }

            if ($suggestedType === 'FOLLOW_UP' || !empty($order['is_follow_up'])) {
                $grounded[] = [
                    'id' => 'ord_' . ($idx + 1),
                    'order_type' => 'FOLLOW_UP',
                    'category_name' => 'Reception Follow-Up',
                    'item_name' => $name,
                    'duration_days' => (int) ($order['duration_days'] ?? 7),
                    'reason' => $order['reason'] ?? 'Routine Review',
                    'target_queue' => 'appointment_queue',
                    'is_selected' => true,
                    'is_matched' => true,
                    'safety_warning' => null,
                ];

                continue;
            }

            // Standard Service or Product
            $matched = $this->matchCatalogItem($name, $suggestedType);
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

                // Clean instructions & indications of any generic dummy/placeholder filler
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
                    'safety_warning' => $safetyWarning,
                ];
            }
        }

        return $grounded;
    }
}
