{{-- show1_procedures.blade.php — Read-only procedures view for patient profile --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="mdi mdi-medical-bag me-2 text-primary"></i>Procedures</h5>
</div>

<div class="alert alert-light border mb-3">
    <i class="mdi mdi-information-outline me-1 text-muted"></i>
    Read-only history of all procedures — surgical and non-surgical. Use a clinical workbench to manage procedures.
</div>

{{-- Sub-tabs --}}
<ul class="nav nav-tabs mb-3" id="show1ProcTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link active" id="show1ProcAllTab" data-bs-toggle="tab" href="#show1ProcAllPanel" role="tab">
            <i class="mdi mdi-format-list-bulleted me-1"></i> All Procedures
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="show1ProcSurgicalTab" data-bs-toggle="tab" href="#show1ProcSurgicalPanel" role="tab">
            <i class="mdi mdi-scalpel me-1"></i> Surgical
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="show1ProcNonSurgicalTab" data-bs-toggle="tab" href="#show1ProcNonSurgicalPanel" role="tab">
            <i class="mdi mdi-needle me-1"></i> Non-Surgical
        </a>
    </li>
</ul>

<div class="tab-content">
    {{-- All Procedures --}}
    <div class="tab-pane fade show active" id="show1ProcAllPanel" role="tabpanel">
        <div class="table-responsive">
            <table id="show1_proc_all_table" class="table table-sm table-bordered table-striped" style="width:100%">
                <thead class="thead-light">
                    <tr><th>Procedure</th></tr>
                </thead>
            </table>
        </div>
    </div>

    {{-- Surgical --}}
    <div class="tab-pane fade" id="show1ProcSurgicalPanel" role="tabpanel">
        <div class="table-responsive">
            <table id="show1_proc_surgical_table" class="table table-sm table-bordered table-striped" style="width:100%">
                <thead class="thead-light">
                    <tr><th>Procedure</th></tr>
                </thead>
            </table>
        </div>
    </div>

    {{-- Non-Surgical --}}
    <div class="tab-pane fade" id="show1ProcNonSurgicalPanel" role="tabpanel">
        <div class="table-responsive">
            <table id="show1_proc_nonsurgical_table" class="table table-sm table-bordered table-striped" style="width:100%">
                <thead class="thead-light">
                    <tr><th>Procedure</th></tr>
                </thead>
            </table>
        </div>
    </div>
</div>

