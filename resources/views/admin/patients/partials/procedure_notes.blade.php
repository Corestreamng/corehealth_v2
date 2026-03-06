{{-- Procedure History DataTable --}}
{{-- Aligned with doctor new_encounter procedure history table --}}

<h5 class="mb-3"><i class="fa fa-user-md"></i> Procedure History</h5>
<div class="table-responsive">
    <table class="table table-hover" style="width: 100%" id="procedure_history_list">
        <thead class="table-light">
            <tr>
                <th><i class="fa fa-user-md"></i> Procedure</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

@push('scripts')
<script>
$(function() {
    if ($.fn.DataTable.isDataTable('#procedure_history_list')) {
        $('#procedure_history_list').DataTable().destroy();
    }

    $('#procedure_history_list').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("procedureHistoryList", ["patient_id" => $patient->id]) }}',
            type: 'GET',
            error: function(xhr, error, thrown) {
                console.log('Error loading procedure history:', error);
            }
        },
        columns: [
            { data: 'procedure', name: 'procedure' },
            { data: 'priority', name: 'priority' },
            { data: 'status', name: 'procedure_status' },
            { data: 'date', name: 'requested_on' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[3, 'desc']],
        language: {
            emptyTable: "No procedures found for this patient"
        }
    });
});
</script>
@endpush
