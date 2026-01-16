{{-- Pharmacy/Store Dashboard Tab --}}
<div class="row g-4 mb-4">
    {{-- Quick Stats --}}
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Queue Today</h6>
                        <h2 class="fw-bold mb-0" id="pharm-stat-queue">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-cart mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Dispensed Today</h6>
                        <h2 class="fw-bold mb-0" id="pharm-stat-dispensed">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-pill mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #dd5e89 0%, #f7bb97 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Total Products</h6>
                        <h2 class="fw-bold mb-0" id="pharm-stat-products">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-package-variant mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Low Stock</h6>
                        <h2 class="fw-bold mb-0" id="pharm-stat-low-stock">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-alert mdi-24px"></i>
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
                    <i class="mdi mdi-pill text-warning me-2"></i>Pharmacy/Store Shortcuts
                </h5>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <a href="{{ route('pharmacy.workbench') }}" class="btn btn-outline-primary w-100 rounded-pill py-3">
                            <i class="mdi mdi-pill d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Pharmacy Workbench</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('product-category.index') }}" class="btn btn-outline-warning w-100 rounded-pill py-3">
                            <i class="mdi mdi-tag-multiple d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Categories</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('stores.index') }}" class="btn btn-outline-success w-100 rounded-pill py-3">
                            <i class="mdi mdi-store d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Stores</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-3">
                            <i class="mdi mdi-package-variant d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Products</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index') }}" class="btn btn-outline-info w-100 rounded-pill py-3">
                            <i class="mdi mdi-account-multiple d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Patients</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
