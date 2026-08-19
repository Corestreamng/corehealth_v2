    <div class="tab-pane fade" id="pane-requisitions" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-requisitions"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-3">
                <select name="from_store_id" class="form-select form-select-sm ops-tab-filter" data-tab="requisitions">
                    <option value="">From Store</option>
                    @foreach($stores as $id => $name)
                        <option value="{{ $id }}">{{ Str::limit($name, 40) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="to_store_id" class="form-select form-select-sm ops-tab-filter" data-tab="requisitions">
                    <option value="">To Store</option>
                    @foreach($stores as $id => $name)
                        <option value="{{ $id }}">{{ Str::limit($name, 40) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="requisitions">
                    <option value="">Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="fulfilled">Fulfilled</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-requisitions">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Req No</th>
                        <th>From Store</th>
                        <th>To Store</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th>Approved By</th>
                        <th>Fulfilled By</th>
                        <th>Items</th>
                        <th>Req Value</th>
                        <th>Appr Value</th>
                        <th>Ful Value</th>
                        <th>Rej Value</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
