<h4>Requested Imaging(billing)</h4>
<form action="{{ route('bill-imaging') }}" method="post">
    @csrf
    <h6>Requested Items</h6>
    <input type="hidden" name="patient_user_id" id="" value="{{ $patient->user->id }}">
    <input type="hidden" name="patient_id" id="" value="{{ $patient->id }}">
    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="imaging_history_bills">
        <thead>
            <th>#</th>
            <th>Select</th>
            <th>Service</th>
            <th>Details</th>
        </thead>
    </table>
    <hr>
    <h6>Other Items</h6>
    <label for="consult_imaging_search">Search imaging services</label>
    <input type="text" class="form-control" id="consult_imaging_search" onkeyup="searchImagingServices(this.value)"
        placeholder="search imaging services..." autocomplete="off">
    <ul class="list-group" id="consult_imaging_res" style="display: none;">

    </ul>
    <br>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped">
            <thead>
                <th>*</th>
                <th>Name</th>
                <th>Price</th>
                <th>Notes</th>
                <th>*</th>
            </thead>
            <tbody id="selected-imaging-services">

            </tbody>
        </table>
    </div>
    <hr>
    <div class="form-group">
        <label for="">Total cost of selected items</label>
        <input type="number" value="0" class="form-control" id="imaging_bill_tot" name="imaging_bill_tot" readonly
            required>

    </div>
    <button type="submit" class="btn btn-primary"
        onclick="return confirm('Are you sure you wish to bill the selected items')">Bill</button>
    <button type="submit" value="dismiss_imaging_bill" name="dismiss_imaging_bill" class="btn btn-danger"
        onclick="return confirm('Are you sure you wish to dismiss the selected items')"
        style="float: right">Dismiss</button>
</form>
<hr>

<h4>Imaging Result Entry</h4>
<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="imaging_history_res">
        <thead>
            <th>#</th>
            <th>Service</th>
            <th>Details</th>
            <th>Entry</th>
        </thead>
    </table>
</div>
<hr>
<h4>Imaging History</h4>
<div class="table responsive">
    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="imaging_history_list">
        <thead>
            <th>#</th>
            <th>Results</th>
            <th>Details</th>
        </thead>
    </table>
</div>
