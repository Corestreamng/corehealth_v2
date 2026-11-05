<?php

namespace App\Helpers;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClinicalSearchHelper
{
    /**
     * Common clinical typos, abbreviations, and synonym mappings.
     * Maps user search tokens to standard canonical tokens.
     *
     * @return array<string, string>
     */
    public static function getSynonyms(): array
    {
        return [
            // Typos & common phonetic variations
            'wond' => 'wound',
            'woud' => 'wound',
            'wnd' => 'wound',
            'drss' => 'dress',
            'dres' => 'dress',
            'inj' => 'injection',
            'injs' => 'injection',
            'injections' => 'injection',
            'tab' => 'tablet',
            'tabs' => 'tablet',
            'tablets' => 'tablet',
            'syr' => 'syrup',
            'syrups' => 'syrup',
            'susp' => 'suspension',
            'susps' => 'suspension',
            'cap' => 'capsule',
            'caps' => 'capsule',
            'capsules' => 'capsule',
            'paracet' => 'paracetamol',
            'dress' => 'dressing',
            'dressings' => 'dressing',
            'canula' => 'cannula',
            'cannula' => 'canula',
            'cather' => 'catheter',
            'catheters' => 'catheter',
            'artem' => 'artemether',
            'amox' => 'amoxicillin',
            'cipro' => 'ciprofloxacin',
            'metro' => 'metronidazole',
            'salb' => 'salbutamol',
            'ceftriax' => 'ceftriaxone',
            'genta' => 'gentamicin',
            'diclo' => 'diclofenac',
            'cotrim' => 'cotrimoxazole',
            'erythro' => 'erythromycin',
            'ampic' => 'ampicillin',
            'dext' => 'dextrose',
            'inf' => 'infusion',
            'sol' => 'solution',
            'ung' => 'ointment',
            'oint' => 'ointment',
        ];
    }

    /**
     * Clean and split a search term into meaningful keyword tokens.
     *
     * @param string $term
     * @return array<int, string>
     */
    public static function tokenize(string $term): array
    {
        $cleaned = preg_replace('/[^\p{L}\p{N}]+/u', ' ', strtolower(trim($term)));
        if ($cleaned === null || $cleaned === '') {
            return [];
        }

        $rawTokens = explode(' ', $cleaned);
        $tokens = [];

        foreach ($rawTokens as $tok) {
            $trimmed = trim($tok);
            // Allow tokens with length >= 2, or single digits (e.g. "1g", "5ml" handled as whole tokens)
            if (mb_strlen($trimmed) >= 2 || is_numeric($trimmed)) {
                $tokens[] = $trimmed;
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Expand tokens with synonyms or typo corrections.
     *
     * @param array<int, string> $tokens
     * @return array<int, string|null>
     */
    public static function expandTokens(array $tokens): array
    {
        $synonyms = self::getSynonyms();
        $expanded = [];

        foreach ($tokens as $tok) {
            $expanded[] = $synonyms[$tok] ?? null;
        }

        return $expanded;
    }

    /**
     * Apply multi-token, middle-of-string search to a Service query.
     *
     * @param Builder $query
     * @param string $term
     * @param int|null $categoryId
     * @param array $allowedCategoryIds
     * @return Builder
     */
    public static function applyServiceSearch(
        Builder $query,
        string $term,
        ?int $categoryId = null,
        array $allowedCategoryIds = []
    ): Builder {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        $tokens = self::tokenize($term);
        $expandedTokens = self::expandTokens($tokens);

        $query->where(function ($q) use ($term, $tokens, $expandedTokens) {
            // 1. Direct exact contiguous substring match on service_name or service_code
            $q->where('service_name', 'LIKE', "%{$term}%")
              ->orWhere('service_code', 'LIKE', "%{$term}%");

            // 2. Tokenized match: every token (or its mapped synonym) must match
            if (!empty($tokens)) {
                $q->orWhere(function ($sub) use ($tokens, $expandedTokens) {
                    foreach ($tokens as $idx => $token) {
                        $syn = $expandedTokens[$idx] ?? null;
                        $sub->where(function ($tokQ) use ($token, $syn) {
                            $tokQ->where('service_name', 'LIKE', "%{$token}%")
                                 ->orWhere('service_code', 'LIKE', "%{$token}%");
                            if ($syn && $syn !== $token) {
                                $tokQ->orWhere('service_name', 'LIKE', "%{$syn}%")
                                     ->orWhere('service_code', 'LIKE', "%{$syn}%");
                            }
                        });
                    }
                });
            }
        });

        if (!empty($categoryId)) {
            $query->where('category_id', $categoryId);
        } elseif (!empty($allowedCategoryIds)) {
            $query->whereIn('category_id', $allowedCategoryIds);
        }

        return $query;
    }

    /**
     * Apply multi-token, middle-of-string search to a Product query.
     *
     * @param Builder $query
     * @param string $term
     * @param int|null $categoryId
     * @param string|null $type
     * @return Builder
     */
    public static function applyProductSearch(
        Builder $query,
        string $term,
        ?int $categoryId = null,
        ?string $type = null
    ): Builder {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        $tokens = self::tokenize($term);
        $expandedTokens = self::expandTokens($tokens);

        $query->where(function ($q) use ($term, $tokens, $expandedTokens) {
            // 1. Direct contiguous match
            $q->where('product_name', 'LIKE', "%{$term}%")
              ->orWhere('product_code', 'LIKE', "%{$term}%");

            // 2. Tokenized match: all tokens must be present
            if (!empty($tokens)) {
                $q->orWhere(function ($sub) use ($tokens, $expandedTokens) {
                    foreach ($tokens as $idx => $token) {
                        $syn = $expandedTokens[$idx] ?? null;
                        $sub->where(function ($tokQ) use ($token, $syn) {
                            $tokQ->where('product_name', 'LIKE', "%{$token}%")
                                 ->orWhere('product_code', 'LIKE', "%{$token}%");
                            if ($syn && $syn !== $token) {
                                $tokQ->orWhere('product_name', 'LIKE', "%{$syn}%")
                                     ->orWhere('product_code', 'LIKE', "%{$syn}%");
                            }
                        });
                    }
                });
            }
        });

        if (!empty($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        if (!empty($type)) {
            $query->where('product_type', $type);
        }

        return $query;
    }

    /**
     * Apply multi-token search to Service Combos / Clinical Kits.
     * Matches either combo name or any product/service item within the combo bundle.
     *
     * @param Builder $query
     * @param string $term
     * @param int|null $categoryId
     * @return Builder
     */
    public static function applyComboSearch(
        Builder $query,
        string $term,
        ?int $categoryId = null
    ): Builder {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        $tokens = self::tokenize($term);
        $expandedTokens = self::expandTokens($tokens);

        $query->where('is_combo', true)
              ->where('status', 1)
              ->where(function ($q) use ($term, $tokens, $expandedTokens) {
                  // Direct combo name match
                  $q->where('service_name', 'LIKE', "%{$term}%")
                    ->orWhere('service_code', 'LIKE', "%{$term}%");

                  // Multi-token combo name match
                  if (!empty($tokens)) {
                      $q->orWhere(function ($sub) use ($tokens, $expandedTokens) {
                          foreach ($tokens as $idx => $token) {
                              $syn = $expandedTokens[$idx] ?? null;
                              $sub->where(function ($tokQ) use ($token, $syn) {
                                  $tokQ->where('service_name', 'LIKE', "%{$token}%");
                                  if ($syn && $syn !== $token) {
                                      $tokQ->orWhere('service_name', 'LIKE', "%{$syn}%");
                                  }
                              });
                          }
                      });
                  }

                  // Match bundle items (services or products inside the combo)
                  $q->orWhereHas('bundleItems', function ($bq) use ($term, $tokens, $expandedTokens) {
                      $bq->where(function ($subQ) use ($term, $tokens, $expandedTokens) {
                          $subQ->where('item_type', 'service')
                               ->whereHas('service', function ($sQ) use ($term, $tokens, $expandedTokens) {
                                   $sQ->where('service_name', 'LIKE', "%{$term}%");
                                   if (!empty($tokens)) {
                                       $sQ->orWhere(function ($sSub) use ($tokens, $expandedTokens) {
                                           foreach ($tokens as $idx => $token) {
                                               $syn = $expandedTokens[$idx] ?? null;
                                               $sSub->where(function ($tokenQ) use ($token, $syn) {
                                                   $tokenQ->where('service_name', 'LIKE', "%{$token}%");
                                                   if ($syn && $syn !== $token) {
                                                       $tokenQ->orWhere('service_name', 'LIKE', "%{$syn}%");
                                                   }
                                               });
                                           }
                                       });
                                   }
                               });
                      })->orWhere(function ($subQ) use ($term, $tokens, $expandedTokens) {
                          $subQ->where('item_type', 'product')
                               ->whereHas('product', function ($pQ) use ($term, $tokens, $expandedTokens) {
                                   $pQ->where('product_name', 'LIKE', "%{$term}%");
                                   if (!empty($tokens)) {
                                       $pQ->orWhere(function ($pSub) use ($tokens, $expandedTokens) {
                                           foreach ($tokens as $idx => $token) {
                                               $syn = $expandedTokens[$idx] ?? null;
                                               $pSub->where(function ($tokenQ) use ($token, $syn) {
                                                   $tokenQ->where('product_name', 'LIKE', "%{$token}%");
                                                   if ($syn && $syn !== $token) {
                                                       $tokenQ->orWhere('product_name', 'LIKE', "%{$syn}%");
                                                   }
                                               });
                                           }
                                       });
                                   }
                               });
                      });
                  });
              });

        if (!empty($categoryId)) {
            $query->where(function ($cq) use ($categoryId) {
                $cq->where('category_id', $categoryId)
                   ->orWhereHas('bundleItems', function ($q) use ($categoryId) {
                       $q->where('item_type', 'service')
                         ->whereHas('service', function ($sQ) use ($categoryId) {
                             $sQ->where('category_id', $categoryId);
                         });
                   });
            });
        }

        return $query;
    }

    /**
     * Find nursing/consumable services (such as wound dressings, ward procedures, nursing care)
     * that match the given search term.
     *
     * @param string $term
     * @param int $limit
     * @return Collection
     */
    public static function findConsumableServices(string $term, int $limit = 10): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }

        // Nursing (4), Procedures (8), Miscellaneous (5)
        $consumableCategoryIds = [4, 8, 5];

        $query = Service::with(['price', 'category'])
            ->where('status', 1)
            ->whereIn('category_id', $consumableCategoryIds);

        self::applyServiceSearch($query, $term);

        return $query->limit($limit)->get();
    }
}
