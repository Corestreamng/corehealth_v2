<h4>Requested Prescription(billing)</h4>
<form action="{{ route('product-bill-patient') }}" method="post">
    @csrf
    <h6>Requested Items</h6>
    <input type="hidden" name="patient_user_id" id="" value="{{ $patient->user->id }}">
    <input type="hidden" name="patient_id" id="" value="{{ $patient->id }}">
    <table class="table table-sm table-bordered table-striped" style="width: 100%"
        id="presc_history_bills">
        <thead>
            <th>#</th>
            <th>Select</th>
            <th>Product</th>
            <th>Details</th>
        </thead>
    </table>
    <hr>
    <h6>Other Items</h6>
    <label for="">Search products</label>
    <input type="text" class="form-control" id="consult_presc_search"
        onkeyup="searchProducts(this.value)" placeholder="search products..." autocomplete="off">
    <ul class="list-group" id="consult_presc_res" style="display: none;">

    </ul>
    <br>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped">
            <thead>
                <th>*</th>
                <th>Name</th>
                <th>Price</th>
                <th>Dose/Freq.</th>
                <th>*</th>
            </thead>
            <tbody id="selected-products">

            </tbody>
        </table>
    </div>
    <hr>
    <div class="form-group">
        <label for="">Total cost of selected items</label>
        <input type="number" value="0" class="form-control" id="presc_bill_tot"
            name="presc_bill_tot" readonly required>

    </div>
    <button type="submit" class="btn btn-primary"
        onclick="return confirm('Are you sure you wish to bill the selected items')">Bill</button>
    <button type="submit" value="dismiss_presc_bill" name="dismiss_presc_bill" class="btn btn-danger"
        onclick="return confirm('Are you sure you wish to dissmiss the selected items')"
        style="float: right">Dismiss</button>
</form>
<hr>
<h4>Requested Prescription(Dispense)</h4>
<form action="{{ route('product-dispense-patient') }}" method="post">
    @csrf
    <h6>Requested Items</h6>
    <input type="hidden" name="patient_user_id" id="" value="{{ $patient->user->id }}">
    <input type="hidden" name="patient_id" id="" value="{{ $patient->id }}">
    <table class="table table-sm table-bordered table-striped" style="width: 100%"
        id="presc_history_dispense">
        <thead>
            <th>#</th>
            <th>Select</th>
            <th>Product</th>
            <th>Details</th>
        </thead>
    </table>
    <hr>
    <button type="submit" class="btn btn-primary"
        onclick="return confirm('Are you sure you wish to dispense the selected items')">Dispense</button>
    <button type="submit" value="dismiss_presc_dispense" name="dismiss_presc_bill"
        class="btn btn-danger"
        onclick="return confirm('Are you sure you wish to dissmiss the selected items')"
        style="float: right">Dismiss</button>
<hr>
<h4>Prescription History</h4>
<table class="table table-hover" style="width: 100%" id="presc_history_list">
    <thead class="table-light">
        <th><i class="mdi mdi-pill"></i> Prescriptions</th>
    </thead>
</table>
