@foreach ($page['sections'] as $section)
    @php
        $columns = $section['columns'] ?? ['total' => ['label' => 'Total', 'is_total' => true]];
        $rows = $section['rows'] ?? [];
        $groups = [];
        foreach ($columns as $cKey => $col) {
            $grp = $col['group'] ?? '';
            $groups[$grp][$cKey] = $col;
        }
        $hasGroups = count(array_filter(array_keys($groups))) > 0;
        $totalCols = 2 + count($columns);
    @endphp

    <div class="nhmis-section-card mb-4" id="{{ $section['id'] }}">
        <div class="nhmis-section-header">
            <h5 class="nhmis-section-title">
                <i class="mdi mdi-table text-primary"></i>
                {{ $section['title'] }}
            </h5>
            <span class="badge badge-light border text-muted">
                {{ count($rows) }} items
            </span>
        </div>
        <div class="nhmis-table-wrapper">
            <table class="nhmis-table table-bordered table-hover">
                <thead>
                    @if ($hasGroups)
                        <tr>
                            <th rowspan="2" class="col-row-num">#</th>
                            <th rowspan="2" class="col-row-label">Data Element / Parameter</th>
                            @foreach ($groups as $grpName => $cols)
                                @if ($grpName !== '')
                                    <th colspan="{{ count($cols) }}" class="text-center">{{ $grpName }}</th>
                                @else
                                    @foreach ($cols as $cKey => $col)
                                        <th rowspan="2" class="text-center">{{ $col['label'] }}</th>
                                    @endforeach
                                @endif
                            @endforeach
                        </tr>
                        <tr>
                            @foreach ($groups as $grpName => $cols)
                                @if ($grpName !== '')
                                    @foreach ($cols as $cKey => $col)
                                        <th class="text-center">{{ $col['label'] }}</th>
                                    @endforeach
                                @endif
                            @endforeach
                        </tr>
                    @else
                        <tr>
                            <th class="col-row-num">#</th>
                            <th class="col-row-label">Data Element / Parameter</th>
                            @foreach ($columns as $cKey => $col)
                                <th class="text-center">{{ $col['label'] }}</th>
                            @endforeach
                        </tr>
                    @endif
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @if ($row['is_header'] ?? false)
                            <tr class="table-secondary font-weight-bold">
                                <td colspan="{{ $totalCols }}" class="py-2 px-3">
                                    <i class="mdi mdi-label-outline text-muted mr-1"></i>
                                    {{ $row['label'] }}
                                </td>
                            </tr>
                        @else
                            @php
                                $rowNum = $row['number'] ?? '';
                                $rowId = $row['id'] ?? ('row_' . $rowNum);
                                $isClinicalAudit = in_array((int)$rowNum, [
                                    93, 94, 95, 96, 105, 106, 107, 108, 110, 111, 112, 113, 114,
                                    115, 116, 117, 118, 119, 120, 121, 122, 123, 124, 125, 126,
                                    137, 138, 139, 140, 141, 142, 143, 144, 145, 147, 148, 149,
                                    150, 151, 152, 153, 154, 155, 156, 161, 162, 163, 164, 165
                                ]);
                            @endphp
                            <tr data-row-id="{{ $rowId }}" data-row-num="{{ $rowNum }}">
                                <td class="cell-num">{{ $rowNum }}</td>
                                <td class="cell-label">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="row-label-text">{{ $row['label'] }}</span>
                                        <div class="row-actions-group d-flex align-items-center ml-2 flex-shrink-0">
                                            <button type="button" 
                                                    class="btn btn-outline-primary btn-xs btn-row-drilldown mr-1" 
                                                    data-row-id="{{ $rowId }}" 
                                                    data-row-num="{{ $rowNum }}" 
                                                    data-label="{{ $row['label'] }}" 
                                                    title="Drill down into underlying patient records for this row">
                                                <i class="mdi mdi-table-search"></i> Drill Down
                                            </button>
                                            @if ($isClinicalAudit)
                                                <button type="button" class="btn btn-outline-info btn-xs btn-audit-row" data-row-id="{{ $rowNum }}" data-label="{{ $row['label'] }}" title="Audit underlying clinical diagnoses and encounters">
                                                    <i class="mdi mdi-microscope"></i> Audit
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                @foreach ($columns as $cKey => $col)
                                    @php
                                        $primaryKey = "{$rowId}:{$cKey}";
                                        $altKey = "{$rowNum}:{$cKey}";
                                        $valRec = $values[$primaryKey] ?? ($values[$altKey] ?? null);
                                        $finalVal = $valRec ? $valRec->final_value : 0;
                                        $autoVal = $valRec ? $valRec->auto_value : 0;
                                        $isOverridden = $valRec ? (bool)$valRec->is_overridden : false;
                                        $reason = $valRec ? $valRec->override_reason : '';
                                        $isTotalCol = $col['is_total'] ?? false;
                                    @endphp
                                    <td class="cell-input-td">
                                        <input type="number" 
                                               min="0" 
                                               class="nhmis-input {{ $isTotalCol ? 'is-total' : '' }} {{ $isOverridden ? 'is-overridden' : '' }}" 
                                               data-cell-key="{{ $primaryKey }}" 
                                               data-row-id="{{ $rowId }}" 
                                               data-col-key="{{ $cKey }}" 
                                               data-is-total="{{ $isTotalCol ? '1' : '0' }}" 
                                               data-auto-val="{{ $autoVal }}" 
                                               data-initial-val="{{ $finalVal }}" 
                                               value="{{ $finalVal }}" 
                                               {{ $report->status === 'locked' ? 'readonly' : '' }} 
                                               title="{{ $isOverridden ? 'Manual override: was ' . $autoVal . ($reason ? ' (Reason: ' . $reason . ')' : '') : 'Auto count: ' . $autoVal }} (Double-click to drill down)" />
                                        @if ($isOverridden)
                                            <span class="override-dot" title="Manually overridden from {{ $autoVal }}"></span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endforeach
