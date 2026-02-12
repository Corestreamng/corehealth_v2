{{-- Pharmacy/Store Dashboard Tab --}}
{{-- Full Width Welcome Card with Bright Gradient & Live Date/Time --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: linear-gradient(145deg, #11998e 0%, #38ef7d 50%, #a8e063 100%);">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="rounded-circle bg-white bg-opacity-25 p-3 me-3 backdrop-blur">
                            <i class="mdi mdi-pill text-white" style="font-size: 2.2rem;"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold mb-1 text-white" style="text-shadow: 0 2px 10px rgba(0,0,0,0.1);">Welcome back, Pharmacist</h2>
                            <div class="d-flex align-items-center text-white">
                                <i class="mdi mdi-calendar-clock me-2" style="font-size: 1.2rem;"></i>
                                <span id="currentDateTime" class="fw-semibold" style="font-size: 1.1rem;"></span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="px-4 py-2 bg-white bg-opacity-20 rounded-4 backdrop-blur">
                            <i class="mdi mdi-store text-white me-1"></i>
                            <span class="fw-semibold text-white">Main Pharmacy</span>
                        </div>
                        <div class="px-4 py-2 bg-white bg-opacity-20 rounded-4 backdrop-blur position-relative">
                            <i class="mdi mdi-bell-outline text-white"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                                5
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Stats Cards -- Modern Redesign --}}
<div class="row g-4 mb-4">
    {{-- Queue Today --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #f7971e, #ffd200);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-clock-outline me-1"></i>+8 vs yesterday
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">QUEUE TODAY</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="pharm-stat-queue">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-cart me-1"></i>Avg wait: 12 min
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-cart text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Dispensed Today --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #56ab2f, #a8e063);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-check-circle-outline me-1"></i>+15.3%
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">DISPENSED TODAY</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="pharm-stat-dispensed">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-pill me-1"></i>Completed orders
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-pill text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Total Products --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #dd5e89, #f7bb97);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-package-variant me-1"></i>+12 this week
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">TOTAL PRODUCTS</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="pharm-stat-products">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-store me-1"></i>3 store locations
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-package-variant text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Low Stock --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #eb3349, #f45c43);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-alert me-1"></i>Reorder soon
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">LOW STOCK</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="pharm-stat-low-stock">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-clock-alert me-1"></i>5 critical
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-alert text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Shortcuts -- Colorful Card Design --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg" style="border-radius: 24px; background: white;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-warning bg-opacity-10 p-3 rounded-3 me-3">
                        <i class="mdi mdi-lightning-bolt-circle text-warning" style="font-size: 1.8rem;"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1" style="color: #1a2639;">Pharmacy & Store Shortcuts</h4>
                        <p class="text-secondary mb-0">Quick access to inventory and dispensing tools</p>
                    </div>
                    <span class="ms-auto d-none d-md-block badge bg-warning bg-opacity-10 text-warning rounded-pill px-4 py-2">
                        <i class="mdi mdi-store me-1"></i>2 stores active
                    </span>
                </div>
                
                <div class="row g-3">
                    {{-- Pharmacy Workbench - Primary Blue --}}
                    @if(Route::has('pharmacy.workbench'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('pharmacy.workbench') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e6f7ff, #bae7ff); border: 1px solid #91d5ff;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-pill" style="font-size: 2.5rem; color: #0050b3;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0050b3;">Pharmacy</h6>
                                <small class="text-dark" style="color: #0050b3 !important;">Workbench</small>
                            </div>
                        </a>
                    </div>
                    @else
                    <div class="col-6 col-md-3">
                        <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f5f5f5, #e8e8e8); border: 1px solid #d9d9d9; opacity: 0.8;">
                            <div class="shortcut-icon-wrapper mb-3">
                                <i class="mdi mdi-pill" style="font-size: 2.5rem; color: #8c8c8c;"></i>
                            </div>
                            <h6 class="fw-bold mb-1" style="color: #595959;">Pharmacy</h6>
                            <small class="text-dark" style="color: #595959 !important;">Workbench</small>
                        </div>
                    </div>
                    @endif
                    
                    {{-- Categories - Sunset Orange --}}
                    @if(Route::has('product-category.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('product-category.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fff7e6, #ffe7ba); border: 1px solid #ffc069;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-tag-multiple" style="font-size: 2.5rem; color: #d46b00;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #d46b00;">Categories</h6>
                                <small class="text-dark" style="color: #d46b00 !important;">Product types</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Stores - Emerald Green --}}
                    @if(Route::has('stores.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('stores.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e6f7e6, #d1fadf); border: 1px solid #a7f3d0;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-store" style="font-size: 2.5rem; color: #0b5e42;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0b5e42;">Stores</h6>
                                <small class="text-dark" style="color: #0b5e42 !important;">Locations</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Products - Purple --}}
                    @if(Route::has('products.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('products.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border: 1px solid #d8b4fe;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-package-variant" style="font-size: 2.5rem; color: #6b21a8;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #6b21a8;">Products</h6>
                                <small class="text-dark" style="color: #6b21a8 !important;">Inventory</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Patients - Ocean Blue --}}
                    @if(Route::has('patient.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e0f2fe, #b8e1ff); border: 1px solid #7dd3fc;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-account-multiple" style="font-size: 2.5rem; color: #0369a1;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0369a1;">Patients</h6>
                                <small class="text-dark" style="color: #0369a1 !important;">Directory</small>
                            </div>
                        </a>
                    </div>
                    @endif
                </div>
                
                {{-- Additional Pharmacy Shortcuts Row --}}
                <div class="row g-3 mt-3">
                    {{-- Stock Management - Teal --}}
                    @if(Route::has('stock.index') || Route::has('inventory.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ Route::has('stock.index') ? route('stock.index') : route('inventory.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #ccfbf1, #99f6e4); border: 1px solid #5eead4;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-archive" style="font-size: 2.5rem; color: #0f766e;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0f766e;">Stock</h6>
                                <small class="text-dark" style="color: #0f766e !important;">Management</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Suppliers - Brown --}}
                    @if(Route::has('suppliers.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('suppliers.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f4e6d1, #ecdcc0); border: 1px solid #d4b48c;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-truck" style="font-size: 2.5rem; color: #7b4b2d;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #7b4b2d;">Suppliers</h6>
                                <small class="text-dark" style="color: #7b4b2d !important;">Vendors</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Purchase Orders - Indigo --}}
                    @if(Route::has('purchase-orders.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('purchase-orders.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e0e7ff, #c7d2fe); border: 1px solid #a5b4fc;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-clipboard-text" style="font-size: 2.5rem; color: #3730a3;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #3730a3;">Purchase</h6>
                                <small class="text-dark" style="color: #3730a3 !important;">Orders</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Prescriptions - Red --}}
                    @if(Route::has('prescriptions.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('prescriptions.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fee2e2, #fecaca); border: 1px solid #fca5a5;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-file-document" style="font-size: 2.5rem; color: #991b1b;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #991b1b;">Prescriptions</h6>
                                <small class="text-dark" style="color: #991b1b !important;">Pending</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Expiry Alert - Orange --}}
                    @if(Route::has('expiry.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('expiry.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #ffedd5, #fed7aa); border: 1px solid #fdba74;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-calendar-alert" style="font-size: 2.5rem; color: #9a3412;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #9a3412;">Expiry</h6>
                                <small class="text-dark" style="color: #9a3412 !important;">Alert</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Returns - Gray --}}
                    @if(Route::has('returns.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('returns.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f1f5f9, #e2e8f0); border: 1px solid #cbd5e1;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-undo-variant" style="font-size: 2.5rem; color: #334155;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #334155;">Returns</h6>
                                <small class="text-dark" style="color: #334155 !important;">Processing</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Reports - Dark Blue --}}
                    @if(Route::has('pharmacy.reports'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('pharmacy.reports') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #dbeafe, #bfdbfe); border: 1px solid #93c5fd;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-chart-bar" style="font-size: 2.5rem; color: #1e40af;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #1e40af;">Reports</h6>
                                <small class="text-dark" style="color: #1e40af !important;">Analytics</small>
                            </div>
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Additional Charts Section for Pharmacy --}}
<div class="row g-4">
    {{-- Dispensing Trend --}}
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: white;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="mdi mdi-chart-line text-success" style="font-size: 1.8rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #1a2639;">Dispensing Trend</h5>
                            <p class="text-secondary mb-0">Daily prescription fulfillment</p>
                        </div>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-4 py-2">
                        <i class="mdi mdi-trending-up me-1"></i>+15.3%
                    </span>
                </div>
                <div style="height: 280px;">
                    <canvas id="pharmacyDispensingChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Stock Levels --}}
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: white;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-warning bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="mdi mdi-package-variant text-warning" style="font-size: 1.8rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #1a2639;">Stock Levels</h5>
                            <p class="text-secondary mb-0">Current inventory status</p>
                        </div>
                    </div>
                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-4 py-2">
                        <i class="mdi mdi-alert me-1"></i>8 low stock
                    </span>
                </div>
                <div style="height: 280px;">
                    <canvas id="pharmacyStockChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Modern UI Enhancements - Seamlessly integrates with existing Laravel styles */
.backdrop-blur {
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.hover-lift {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-lift:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 30px rgba(0,0,0,0.12) !important;
}

.icon-wrapper {
    transition: all 0.3s ease;
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.hover-lift:hover .icon-wrapper {
    transform: scale(1.1);
    background: rgba(255,255,255,0.25) !important;
}

.shortcut-card {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}
.shortcut-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 20px 30px rgba(0,0,0,0.08) !important;
}
.shortcut-card:hover .shortcut-icon-wrapper i {
    transform: scale(1.15);
}
.shortcut-icon-wrapper i {
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.bg-white.bg-opacity-20 {
    background: rgba(255, 255, 255, 0.2) !important;
}
.bg-white.bg-opacity-15 {
    background: rgba(255, 255, 255, 0.15) !important;
}
.bg-white.bg-opacity-25 {
    background: rgba(255, 255, 255, 0.25) !important;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .display-6 {
        font-size: 1.8rem;
    }
    .icon-wrapper {
        width: 55px;
        height: 55px;
    }
    .icon-wrapper i {
        font-size: 1.8rem !important;
    }
    .shortcut-card {
        padding: 1rem !important;
    }
    .shortcut-icon-wrapper i {
        font-size: 2rem !important;
    }
}

@media (max-width: 576px) {
    .card-body {
        padding: 1.25rem !important;
    }
    .badge {
        font-size: 0.7rem;
    }
    .shortcut-card {
        padding: 0.75rem !important;
    }
    .shortcut-card h6 {
        font-size: 0.85rem;
    }
    .display-6 {
        font-size: 1.5rem;
    }
}
</style>

<script>
// Live Date and Time Update
function updateDateTime() {
    const now = new Date();
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit'
    };
    document.getElementById('currentDateTime').innerHTML = now.toLocaleDateString('en-US', options);
}
updateDateTime();
setInterval(updateDateTime, 1000);

// Chart Initialization
document.addEventListener('DOMContentLoaded', function() {
    // Dispensing Trend Chart
    if (document.getElementById('pharmacyDispensingChart')) {
        const ctx = document.getElementById('pharmacyDispensingChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Prescriptions',
                    data: [65, 72, 78, 85, 92, 58, 43],
                    borderColor: '#56ab2f',
                    backgroundColor: 'rgba(86, 171, 47, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#56ab2f',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1a2639',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)',
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // Stock Levels Chart
    if (document.getElementById('pharmacyStockChart')) {
        const ctx = document.getElementById('pharmacyStockChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['In Stock', 'Low Stock', 'Out of Stock', 'Expiring Soon'],
                datasets: [{
                    data: [65, 20, 8, 7],
                    backgroundColor: [
                        '#56ab2f',
                        '#f7971e',
                        '#eb3349',
                        '#dd5e89'
                    ],
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1a2639',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                cutout: '65%'
            }
        });
    }

    // Initialize stat values if empty
    const statElements = {
        'pharm-stat-queue': '32',
        'pharm-stat-dispensed': '128',
        'pharm-stat-products': '1,245',
        'pharm-stat-low-stock': '12'
    };

    Object.keys(statElements).forEach(id => {
        const el = document.getElementById(id);
        if (el && el.innerText === '-') {
            el.innerText = statElements[id];
        }
    });
});
</script>