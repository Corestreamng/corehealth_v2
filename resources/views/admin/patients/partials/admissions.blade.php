<div class="table-responsive">
    <table id="admission-request-list" class="table table-hover"
        style="width: 100%">
        <thead class="table-light">
            <tr>
                <th style="width: 100%;"><i class="fa fa-bed"></i> Admission History</th>
            </tr>
        </thead>
    </table>
</div>

<div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
    <button type="button" onclick="switch_tab(event,'medications_tab')" class="btn btn-secondary">
        <i class="fa fa-arrow-left"></i> Previous
    </button>
    <button type="button" onclick="$('#concludeEncounterModal').modal('show')" class="btn btn-primary">
        <i class="fa fa-check-circle"></i> Conclude Encounter
    </button>
</div>
