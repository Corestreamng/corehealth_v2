@if (null != $patient_acc)
    <div class="table-responsive">
        <table class="table">
            <thead class="bg-dark text-light">
                <th>Account Id</th>
                <th>Account bal</th>
                <th>Last Updated</th>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $patient_acc->id }}</td>
                    <td>{{ $patient_acc->balance }}</td>
                    <td>{{ date('h:i a D M j, Y', strtotime($patient_acc->updated_at)) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    <hr>
    <h5>Make Deposit</h5>
    <form action="{{ route('account-make-deposit') }}" method="post">
        @csrf
        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
        <input type="hidden" name="acc_id" value="{{ $patient_acc->id }}">
        <div class="form-group">
            <label for="">Amount | <small>Enter negative values for debt / credit</small></label>
            <input type="number" name="amount" id="" class="form-control"
                placeholder="Enter amount to deposit" required>
        </div>
        <button type="submit" class="btn btn-primary"
            onclick="return confirm('Are you sure you wish to save this deposit?')">Save</button>
    </form>
@else
    <h4>Patient Has no acc</h4>
    <form action="{{ route('patient-account.store') }}" method="post">
        @csrf
        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
        <button type="submit" class="btn btn-primary">Create account</button>
    </form>
@endif
<hr>
<h4>All services Rendered</h4>
<a href="{{ route('patient-services-rendered', $patient->id) }}" class="btn btn-primary">See Details</a>
<hr>
<h4>Add Misc. Bills</h4>
<form action="{{ route('add-misc-bill') }}" method="post">
    @csrf
    <table class="table">
        <thead>
            <tr>
                <th>Service desc.</th>
                <th>Cost (NGN)</th>
                <th><button type="button" class="btn btn-primary btn-sm" onclick="addMiscBillRow()"><i
                            class="fa fa-plus"></i> Add row</button></th>
            </tr>
        </thead>
        <tbody id="misc-bill-row">
            <tr>
                <td>
                    <input type="text" class="form-control" name="names[]"
                        placeholder="Describe service rendered...">
                </td>
                <td>
                    <input type="number" class="form-control" name="prices[]" min="1">
                    <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                </td>
                <td><button type="button" onclick="removeMiscBillRow(this)" class="btn btn-danger btn-sm"><i
                            class="fa fa-times"></i></button></td>
            </tr>
        </tbody>
    </table>

    <button type="submit" class="btn btn-primary"
        onclick="return confirm('Are you sure you wish to Save these Misc. bills?')">Save</button>
</form>
<hr>
<h4>Requested Misc. Bill Items</h4>
<form action="{{ route('bill-misc-bill') }}" method="post">
    @csrf
    <input type="hidden" name="patient_id" id="" value="{{ $patient->id }}">
    <input type="hidden" name="patient_user_id" id="" value="{{ $patient->user_id }}">
    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="misc_bill_bills">
        <thead>
            <th>#</th>
            <th>Select</th>
            <th>Service</th>
            <th>Details</th>
        </thead>
    </table>
    <hr>
    <div class="form-group">
        <label for="">Total cost of selected items</label>
        <input type="number" value="0" class="form-control" id="misc_bill_tot" name="misc_bill_tot" readonly
            required>

    </div>
    <button type="submit" class="btn btn-primary"
        onclick="return confirm('Are you sure you wish to bill the selected items')">Bill</button>
    <button type="submit" value="dismiss_misc_bill" name="dismiss_misc_bill" class="btn btn-danger"
        onclick="return confirm('Are you sure you wish to dissmiss the selected items')"
        style="float: right">Dismiss</button>
</form>
<hr>
<h4>All Previous Misc. Bill Items</h4>
<table class="table table-sm table-bordered table-striped" style="width: 100%" id="misc_bill_bills_hist">
    <thead>
        <th>#</th>
        <th>Service</th>
        <th>Details</th>
    </thead>
</table>
<hr>
<h4>Pending Paymnets</h4>
<div class="table-responsive">
    <table id="pending-paymnet-list" class="table table-sm table-bordered table-striped" style="width: 100%">
        <thead>
            <tr>
                <th>SN</th>
                <th>Service</th>
                <th>Product</th>
                <th>View</th>
            </tr>
        </thead>
    </table>
</div>
<hr>
<h4>All Previous Transactions</h4>
<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="payment_history_list">
        <thead>
            <th>#</th>
            <th>Staff</th>
            <th>Amount</th>
            <th>Type</th>
            <th>Service(s)</th>
            <th>Date</th>
        </thead>
    </table>
</div>
