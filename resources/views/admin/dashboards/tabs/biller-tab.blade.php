{{-- Biller/Accounts Dashboard Tab --}}
<div class="row g-4 mb-4">
    {{-- Quick Stats --}}
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Today's Revenue</h6>
                        <h2 class="fw-bold mb-0" id="biller-stat-revenue">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-cash-multiple mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Payment Requests</h6>
                        <h2 class="fw-bold mb-0" id="biller-stat-payment-requests">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-clipboard-list mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #8e2de2 0%, #4a00e0 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">My Payments</h6>
                        <h2 class="fw-bold mb-0" id="biller-stat-my-payments">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-receipt mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Consultations</h6>
                        <h2 class="fw-bold mb-0" id="biller-stat-consultations">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-stethoscope mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Shortcuts --}}
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px;">
            <div class="card-body">
                <h5 class="card-title mb-4">
                    <i class="mdi mdi-cash-multiple text-success me-2"></i>Billing & Accounts Shortcuts
                </h5>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <a href="{{ route('billing.workbench') }}" class="btn btn-outline-primary w-100 rounded-pill py-3">
                            <i class="mdi mdi-view-dashboard d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Billing Workbench</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('product-or-service-request.index') }}" class="btn btn-outline-warning w-100 rounded-pill py-3">
                            <i class="mdi mdi-clipboard-list d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Payment Requests</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('my-transactions') }}" class="btn btn-outline-success w-100 rounded-pill py-3">
                            <i class="mdi mdi-receipt d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">My Transactions</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('allPrevEncounters') }}" class="btn btn-outline-info w-100 rounded-pill py-3">
                            <i class="mdi mdi-stethoscope d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Consultations</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-3">
                            <i class="mdi mdi-account-multiple d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">All Patients</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Charts Section --}}
<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px;">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <i class="mdi mdi-chart-line text-success me-2"></i>Revenue Trend This Month
                </h5>
                <div style="height: 250px;">
                    <canvas id="billerRevenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
