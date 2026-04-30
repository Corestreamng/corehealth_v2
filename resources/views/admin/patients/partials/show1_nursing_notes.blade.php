{{-- show1_nursing_notes.blade.php
     Read-only view of "Other Notes" (type_id = 5) nursing notes for a patient.
     Uses the same endpoint as the nurse workbench: GET /nursing-workbench/patient/{id}/nursing-notes?type_id=5
--}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="mdi mdi-note-text me-2 text-primary"></i>Nursing Notes</h5>
    <a href="{{ route('nursing-workbench.index', ['patient_id' => $patient->id]) }}"
       class="btn btn-sm btn-outline-primary" target="_blank">
        <i class="mdi mdi-open-in-new me-1"></i>Open in Nurse Workbench
    </a>
</div>

<div class="alert alert-light border mb-3">
    <i class="mdi mdi-information-outline me-1 text-muted"></i>
    Showing <strong>Other Notes</strong> recorded for this patient. Notes are read-only here. To add or edit notes, use the Nursing Workbench.
</div>

{{-- Notes table (cards rendered server-side) --}}
<div id="show1-nursing-notes-wrap">
    <table id="show1_nursing_notes_table"
           class="table table-sm"
           style="width:100%">
        <thead>
            <tr><th>Note</th></tr>
        </thead>
    </table>
</div>

