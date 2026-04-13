<?php

namespace App\Http\Controllers;

use App\Models\Hmo;
use App\Models\HmoTariff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TariffController extends Controller
{
    /**
     * AJAX: Update a single tariff or bulk-update multiple tariffs.
     */
    public function update(Request $request)
    {
        $request->validate([
            'item_type' => 'required|in:product,service',
            'item_id'   => 'required|integer|min:1',
        ]);

        $itemType = $request->input('item_type');
        $itemId   = (int) $request->input('item_id');
        $bulk     = $request->input('bulk');

        DB::beginTransaction();
        try {
            if ($bulk) {
                $rows = json_decode($bulk, true);
                if (!is_array($rows)) {
                    return response()->json(['message' => 'Invalid bulk data.'], 422);
                }

                $updated = 0;
                $created = 0;
                foreach ($rows as $row) {
                    $result = $this->upsertTariff(
                        $itemType,
                        $itemId,
                        (int) $row['hmo_id'],
                        (float) ($row['payable_amount'] ?? 0),
                        (float) ($row['claims_amount'] ?? 0),
                        $row['coverage_mode'] ?? null
                    );
                    if ($result === 'created') $created++;
                    else $updated++;
                }
                DB::commit();
                return response()->json([
                    'message' => "{$updated} updated, {$created} created.",
                    'updated' => $updated,
                    'created' => $created,
                ]);
            } else {
                $request->validate([
                    'hmo_id'          => 'required|integer|min:1',
                    'payable_amount'  => 'required|numeric|min:0',
                    'claims_amount'   => 'required|numeric|min:0',
                ]);

                $result = $this->upsertTariff(
                    $itemType,
                    $itemId,
                    (int) $request->input('hmo_id'),
                    (float) $request->input('payable_amount'),
                    (float) $request->input('claims_amount'),
                    $request->input('coverage_mode')
                );

                DB::commit();
                return response()->json([
                    'message' => "Tariff {$result} successfully.",
                    'result'  => $result,
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("TariffController@update: " . $e->getMessage());
            return response()->json(['message' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    private function upsertTariff(string $itemType, int $itemId, int $hmoId, float $payable, float $claims, ?string $coverageMode = null): string
    {
        $validModes = ['express', 'primary', 'secondary'];
        if ($coverageMode && !in_array($coverageMode, $validModes)) {
            $coverageMode = null;
        }

        $query = HmoTariff::where('hmo_id', $hmoId);
        if ($itemType === 'product') {
            $query->where('product_id', $itemId)->whereNull('service_id');
        } else {
            $query->where('service_id', $itemId)->whereNull('product_id');
        }

        $tariff = $query->first();

        if ($tariff) {
            $data = [
                'payable_amount' => $payable,
                'claims_amount'  => $claims,
            ];
            if ($coverageMode) {
                $data['coverage_mode'] = $coverageMode;
            }
            $tariff->update($data);
            return 'updated';
        } else {
            HmoTariff::create([
                'hmo_id'         => $hmoId,
                'product_id'     => $itemType === 'product' ? $itemId : null,
                'service_id'     => $itemType === 'service' ? $itemId : null,
                'payable_amount' => $payable,
                'claims_amount'  => $claims,
                'coverage_mode'  => $coverageMode ?: 'primary',
            ]);
            return 'created';
        }
    }
}
