<h4>Requested Investigations(billing)</h4>
<form action="{{ route('service-bill-patient') }}" method="post">
    @csrf
    <h6>Requested Items</h6>
    <input type="hidden" name="patient_user_id" id="" value="{{ $patient->user->id }}">
    <input type="hidden" name="patient_id" id="" value="{{ $patient->id }}">
    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="invest_history_bills">
        <thead>
            <th>#</th>
            <th>Select</th>
            <th>Service</th>
            <th>Details</th>
        </thead>
    </table>
    <hr>
    <h6>Other Items</h6>
    <label for="consult_invest_search">Search services</label>
    <input type="text" class="form-control" id="consult_invest_search" onkeyup="searchServices(this.value)"
        placeholder="search services..." autocomplete="off">
    <ul class="list-group" id="consult_invest_res" style="display: none;">

    </ul>
    <br>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped">
            <thead>
                <th>*</th>
                <th>Name</th>
                <th>Price</th>
                <th>Notes/Specimen</th>
                <th>*</th>
            </thead>
            <tbody id="selected-services">

            </tbody>
        </table>
    </div>
    <hr>
    <div class="form-group">
        <label for="">Total cost of selected items</label>
        <input type="number" value="0" class="form-control" id="invest_bill_tot" name="invest_bill_tot" readonly
            required>

    </div>
    <button type="submit" class="btn btn-primary"
        onclick="return confirm('Are you sure you wish to bill the selected items')">Bill</button>
    <button type="submit" value="dismiss_invest_bill" name="dismiss_invest_bill" class="btn btn-danger"
        onclick="return confirm('Are you sure you wish to dissmiss the selected items')"
        style="float: right">Dismiss</button>
</form>
<hr>
<h4>Requested Investigations(sample collection)</h4>
<form action="{{ route('service-sample-patient') }}" method="post">
    @csrf
    <h6>Requested Items</h6>
    <input type="hidden" name="patient_user_id" id="" value="{{ $patient->user->id }}">
    <input type="hidden" name="patient_id" id="" value="{{ $patient->id }}">
    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="invest_history_sample">
        <thead>
            <th>#</th>
            <th>Select</th>
            <th>Service</th>
            <th>Details</th>
        </thead>
    </table>
    <hr>
    <button type="submit" class="btn btn-primary"
        onclick="return confirm('Are you sure you wish to mark the selected items as \'sample taken\'?')">Take
        Sample</button>
    <button type="submit" value="dismiss_invest_sample" name="dismiss_invest_bill" class="btn btn-danger"
        onclick="return confirm('Are you sure you wish to dissmiss the selected items, you cannot undo this!!!')"
        style="float: right">Dismiss</button>
</form>
<hr>

<h4>Investigation Result Entry</h4>
<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="invest_history_res">
        <thead>
            <th>#</th>
            <th>Service</th>
            <th>Details</th>
            <th>Entry</th>
        </thead>
    </table>
</div>
<hr>
<h4>Investigation History</h4>
<div class="table responsive">
    <table class="table table-hover" style="width: 100%" id="investigation_history_list">
        <thead class="table-light">
            <th><i class="mdi mdi-test-tube"></i> Laboratory Requests</th>
        </thead>
    </table>
</div>
