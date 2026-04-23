<?php

namespace App\Observers;

use App\Models\Store;

/**
 * StoreObserver — enforces immutability on canonical stores.
 *
 * When is_immutable = true on a store:
 *   - It CANNOT be soft-deleted or hard-deleted.
 *   - Its status cannot be set to inactive (0).
 *   - Its distribution_role cannot be changed.
 *
 * These constraints protect the canonical Pharmacy Hub and Central Store from
 * accidental deactivation through the governance config UI.
 *
 * Blocking is done by reverting the change and throwing an exception
 * (which becomes a 422 JSON response for AJAX callers via the Handler).
 */
class StoreObserver
{
    /**
     * Before saving, block forbidden changes to immutable stores.
     */
    public function updating(Store $store): void
    {
        if (! $store->is_immutable) {
            return;
        }

        // Block deactivation
        if ($store->isDirty('status') && ! $store->status) {
            $store->status = true; // revert
            throw new \RuntimeException(
                "Store [{$store->store_name}] is immutable and cannot be deactivated."
            );
        }

        // Block role change
        if ($store->isDirty('distribution_role')) {
            $original = $store->getOriginal('distribution_role');
            $store->distribution_role = $original; // revert
            throw new \RuntimeException(
                "Store [{$store->store_name}] is immutable — its distribution role cannot be changed."
            );
        }
    }

    /**
     * Block deletion of immutable stores.
     */
    public function deleting(Store $store): bool
    {
        if ($store->is_immutable) {
            throw new \RuntimeException(
                "Store [{$store->store_name}] is immutable and cannot be deleted."
            );
        }

        return true;
    }
}
