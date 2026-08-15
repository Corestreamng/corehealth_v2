<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditMark;

class AuditMarkController extends Controller
{
    private function resolveModelClass(string $modelType): string
    {
        $clean = ltrim($modelType, '\\');
        if (str_starts_with($clean, 'App\\Models\\')) {
            return $clean;
        }
        return 'App\\Models\\' . $clean;
    }

    /**
     * Individually mark an item as audited.
     */
    public function stamp(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
            'zone_key' => 'required|string'
        ]);

        $modelClass = $this->resolveModelClass($request->model_type);

        if (!class_exists($modelClass)) {
            return response()->json(['success' => false, 'message' => 'Invalid model type.'], 400);
        }

        $record = $modelClass::find($request->model_id);
        
        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }

        $mark = new AuditMark();
        $mark->auditable_type = $modelClass;
        $mark->auditable_id = $record->id;
        $mark->zone_key = $request->zone_key;
        $mark->auditor_id = auth()->id();
        $mark->status = 'audited';
        $mark->save();

        // Sync direct table columns if present
        $table = $record->getTable();
        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'is_audited')) {
            $record->is_audited = 1;
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'audited_by')) {
                $record->audited_by = auth()->id();
                $record->audited_at = now();
            }
            $record->save();
        }

        return response()->json(['success' => true, 'message' => 'Item marked as audited successfully.']);
    }

    /**
     * Raise a query/flag on an item.
     */
    public function raiseQuery(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
            'zone_key' => 'required|string',
            'query_notes' => 'required|string'
        ]);

        $modelClass = $this->resolveModelClass($request->model_type);

        if (!class_exists($modelClass)) {
            return response()->json(['success' => false, 'message' => 'Invalid model type.'], 400);
        }

        $mark = new AuditMark();
        $mark->auditable_type = $modelClass;
        $mark->auditable_id = $request->model_id;
        $mark->zone_key = $request->zone_key;
        $mark->auditor_id = auth()->id();
        $mark->status = 'queried';
        $mark->query_notes = $request->query_notes;
        $mark->save();

        // Sync direct table columns if present
        $record = $modelClass::find($request->model_id);
        if ($record) {
            $table = $record->getTable();
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'is_queried')) {
                $record->is_queried = 1;
                if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'queried_by')) {
                    $record->queried_by = auth()->id();
                    $record->queried_at = now();
                    $record->query_notes = $request->query_notes;
                }
                $record->save();
            }
        }

        return response()->json(['success' => true, 'message' => 'Audit query raised successfully.']);
    }

    /**
     * Resolve an existing query.
     */
    public function resolveQuery(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
            'resolution_notes' => 'required|string'
        ]);

        $modelClass = $this->resolveModelClass($request->model_type);
        $baseClass = class_basename($modelClass);

        $queryMark = AuditMark::where(function($q) use ($modelClass, $baseClass) {
                $q->where('auditable_type', $modelClass)
                  ->orWhere('auditable_type', $baseClass)
                  ->orWhere('auditable_type', 'App\\Models\\' . $baseClass);
            })
            ->where('auditable_id', $request->model_id)
            ->where('status', 'queried')
            ->latest()
            ->first();

        if (!$queryMark) {
            $queryMark = AuditMark::where('auditable_id', $request->model_id)
                ->where('status', 'queried')
                ->latest()
                ->first();
        }

        if ($queryMark) {
            $queryMark->status = 'resolved';
            $queryMark->query_resolved_by = auth()->id();
            $queryMark->query_resolved_at = now();
            $queryMark->query_resolution_notes = $request->resolution_notes;
            $queryMark->save();

            // Sync direct table columns if present
            if (class_exists($modelClass)) {
                $record = $modelClass::find($request->model_id);
                if ($record) {
                    $table = $record->getTable();
                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'is_queried')) {
                        $record->is_queried = 0;
                        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'query_resolved_by')) {
                            $record->query_resolved_by = auth()->id();
                            $record->query_resolved_at = now();
                            $record->query_resolution_notes = $request->resolution_notes;
                        }
                        $record->save();
                    }
                }
            }

            return response()->json(['success' => true, 'message' => 'Audit query resolved successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'No active query found to resolve.'], 404);
    }
    
    /**
     * Bulk stamp multiple IDs efficiently.
     */
    public function bulkStamp(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string',
            'ids' => 'required|array',
            'zone_key' => 'required|string'
        ]);
        
        $modelClass = $this->resolveModelClass($request->model_type);
        $ids = $request->ids;
        $zoneKey = $request->zone_key;
        $auditorId = auth()->id();
        $now = now();
        
        // Find IDs that are currently queried (and unresolved)
        $queriedIds = AuditMark::where('auditable_type', $modelClass)
            ->whereIn('auditable_id', $ids)
            ->where('status', 'queried')
            ->whereNull('query_resolved_at')
            ->pluck('auditable_id')
            ->toArray();
            
        // Filter out queried IDs
        $validIds = array_diff($ids, $queriedIds);
        
        if (empty($validIds)) {
             return response()->json(['success' => true, 'message' => 'No valid items to stamp (they may all be queried).', 'stamped_count' => 0]);
        }
        
        // Chunk inserts to handle large arrays safely
        $chunks = array_chunk($validIds, 1000);
        foreach ($chunks as $chunk) {
            $insertData = [];
            foreach ($chunk as $id) {
                $insertData[] = [
                    'auditable_type' => $modelClass,
                    'auditable_id' => $id,
                    'zone_key' => $zoneKey,
                    'auditor_id' => $auditorId,
                    'status' => 'audited',
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
            AuditMark::insert($insertData);
        }
        
        return response()->json([
            'success' => true, 
            'message' => count($validIds) . ' items marked as audited successfully.',
            'stamped_count' => count($validIds),
            'skipped_count' => count($queriedIds)
        ]);
    }
}
