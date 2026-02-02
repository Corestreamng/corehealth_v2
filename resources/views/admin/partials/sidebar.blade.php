<nav class="ch-sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">
        <!-- Top Brand Section - Fixed -->
        <li class="nav-item nav-brand">
            <div class="text-center w-100">
                @if(appsettings()->logo)
                    <img src="data:image/jpeg;base64,{{ appsettings()->logo }}" alt="logo" class="nav-brand-logo" />
                @else
                    <div class="nav-brand-abbr">
                        {{ strtoupper(substr(appsettings()->site_abbreviation ?? 'CH', 0, 2)) }}
                    </div>
                @endif
                <h6 class="mb-1 nav-brand-title">{{ env('APP_NAME') }}</h6>
                <p class="mb-0 nav-brand-version">v{{ appsettings()->version ?? env('APP_VER') }}</p>
            </div>
        </li>

        <!-- Scrollable Navigation Area -->
        <div class="ch-sidebar-nav-scrollable">

            {{-- ========================================
                 GLOBAL SECTION (All Roles)
                 ======================================== --}}
            <li class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}" id="sidebar-global-dashboard">
                    <i class="mdi mdi-view-dashboard-outline menu-icon"></i>
                    <span class="menu-title">Dashboard</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('hr.ess.my-profile') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hr.ess.my-profile') ? 'active' : '' }}" href="{{ route('hr.ess.my-profile') }}" id="sidebar-global-profile">
                    <i class="mdi mdi-account-circle menu-icon"></i>
                    <span class="menu-title">Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('chat.index') }}" target="_blank" id="sidebar-global-messenger">
                    <i class="mdi mdi-message-text-outline menu-icon"></i>
                    <span class="menu-title">Messenger</span>
                    @include('messenger.unread-count')
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('inventory.requisitions.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('inventory.requisitions.*') ? 'active' : '' }}" href="{{ route('inventory.requisitions.index') }}" id="sidebar-global-requisitions">
                    <i class="mdi mdi-swap-horizontal menu-icon"></i>
                    <span class="menu-title">Requisitions</span>
                </a>
            </li>

            {{-- ========================================
                 EMPLOYEE SELF-SERVICE (ESS) - My HR Portal
                 Available to all staff with HR profiles
                 ======================================== --}}
            @auth
            @if(Auth::user()->staff_profile)
            @can('ess.access')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">My HR Portal</span>
            </li>
            <li class="nav-item {{ request()->routeIs('hr.ess.index') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hr.ess.index') ? 'active' : '' }}" href="{{ route('hr.ess.index') }}" id="sidebar-ess-dashboard">
                    <i class="mdi mdi-account-circle-outline menu-icon"></i>
                    <span class="menu-title">ESS Dashboard</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('hr.ess.my-leave') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hr.ess.my-leave') ? 'active' : '' }}" href="{{ route('hr.ess.my-leave') }}" id="sidebar-ess-my-leave">
                    <i class="mdi mdi-calendar-check menu-icon"></i>
                    <span class="menu-title">My Leave</span>
                </a>
            </li>
            @can('ess.view-payslips')
            <li class="nav-item {{ request()->routeIs('hr.ess.my-payslips') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hr.ess.my-payslips') ? 'active' : '' }}" href="{{ route('hr.ess.my-payslips') }}" id="sidebar-ess-my-payslips">
                    <i class="mdi mdi-file-document-outline menu-icon"></i>
                    <span class="menu-title">My Payslips</span>
                </a>
            </li>
            @endcan
            <li class="nav-item {{ request()->routeIs('hr.ess.my-disciplinary') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hr.ess.my-disciplinary') ? 'active' : '' }}" href="{{ route('hr.ess.my-disciplinary') }}" id="sidebar-ess-my-disciplinary">
                    <i class="mdi mdi-alert-circle-outline menu-icon"></i>
                    <span class="menu-title">My Disciplinary</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('hr.ess.my-profile') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hr.ess.my-profile') ? 'active' : '' }}" href="{{ route('hr.ess.my-profile') }}" id="sidebar-ess-my-profile">
                    <i class="mdi mdi-account-edit-outline menu-icon"></i>
                    <span class="menu-title">My Profile</span>
                </a>
            </li>
            @endcan
            @endif
            @endauth

            {{-- ========================================
                 RECEPTIONIST SECTION
                 ======================================== --}}
            @hasanyrole('SUPERADMIN|ADMIN|RECEPTIONIST')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Health Records</span>
            </li>
            <li class="nav-item {{ request()->routeIs('reception.workbench') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('reception.workbench') ? 'active' : '' }}" href="{{ route('reception.workbench') }}" id="sidebar-receptionist-workbench">
                    <i class="mdi mdi-desktop-mac-dashboard menu-icon"></i>
                    <span class="menu-title">Health Records Workbench</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('patient.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('patient.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-receptionist-patients" data-bs-target="#sidebar-receptionist-patients" aria-expanded="{{ request()->routeIs('patient.*') ? 'true' : 'false' }}" aria-controls="sidebar-receptionist-patients" id="sidebar-receptionist-patients-toggle">
                    <i class="mdi mdi-account-multiple-outline menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('patient.*') ? 'show' : '' }}" id="sidebar-receptionist-patients">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('patient.create') ? 'active' : '' }}" href="{{ route('patient.create') }}" id="sidebar-receptionist-patient-new">New Registration</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('patient.index') ? 'active' : '' }}" href="{{ route('patient.index') }}" id="sidebar-receptionist-patient-all">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            <!-- Patient Lookup - Old DataTable Approach
            <li class="nav-item {{ request()->routeIs('add-to-queue') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('add-to-queue') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-receptionist-patient-lookup" data-bs-target="#sidebar-receptionist-patient-lookup" aria-expanded="{{ request()->routeIs('add-to-queue') ? 'true' : 'false' }}" aria-controls="sidebar-receptionist-patient-lookup" id="sidebar-receptionist-patient-lookup-toggle">
                    <i class="mdi mdi-account-search-outline menu-icon"></i>
                    <span class="menu-title">Patient Lookup</span>
                    <span class="badge badge-warning ml-2" style="font-size: 9px;">Old</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('add-to-queue') ? 'show' : '' }}" id="sidebar-receptionist-patient-lookup">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('add-to-queue') ? 'active' : '' }}" href="{{ route('add-to-queue') }}" id="sidebar-receptionist-patient-search-old">Search</a>
                        </li>
                    </ul>
                </div>
            </li>
            -->
            <!-- Admissions - Old DataTable Approach
            <li class="nav-item {{ request()->routeIs('admission-requests.*', 'beds.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('admission-requests.*', 'beds.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-receptionist-admissions-old" data-bs-target="#sidebar-receptionist-admissions-old" aria-expanded="{{ request()->routeIs('admission-requests.*', 'beds.*') ? 'true' : 'false' }}" aria-controls="sidebar-receptionist-admissions-old" id="sidebar-receptionist-admissions-old-toggle">
                    <i class="mdi mdi-hotel menu-icon"></i>
                    <span class="menu-title">Admissions</span>
                    <span class="badge badge-warning ml-2" style="font-size: 9px;">Old</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('admission-requests.*', 'beds.*') ? 'show' : '' }}" id="sidebar-receptionist-admissions-old">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admission-requests.index') ? 'active' : '' }}" href="{{ route('admission-requests.index') }}" id="sidebar-receptionist-admissions-bed-old">Bed requests</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('beds.index') ? 'active' : '' }}" href="{{ route('beds.index') }}" id="sidebar-receptionist-admissions-beds-old">Manage Beds</a>
                        </li>
                    </ul>
                </div>
            </li>
            -->
            <!-- Bookings - Old DataTable Approach
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-receptionist-bookings-old" data-bs-target="#sidebar-receptionist-bookings-old" aria-expanded="false" aria-controls="sidebar-receptionist-bookings-old" id="sidebar-receptionist-bookings-old-toggle">
                    <i class="mdi mdi-calendar-check menu-icon"></i>
                    <span class="menu-title">Bookings</span>
                    <span class="badge badge-warning ml-2" style="font-size: 9px;">Old</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="sidebar-receptionist-bookings-old">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="#" id="sidebar-receptionist-booking-new-old">New Booking</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" id="sidebar-receptionist-booking-calendar-old">View calender</a>
                        </li>
                    </ul>
                </div>
            </li>
            -->
            <li class="nav-item {{ request()->routeIs('product-or-service-request.*', 'my-transactions', 'billing.workbench') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('product-or-service-request.*', 'my-transactions', 'billing.workbench') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-receptionist-accounts" data-bs-target="#sidebar-receptionist-accounts" aria-expanded="{{ request()->routeIs('product-or-service-request.*', 'my-transactions', 'billing.workbench') ? 'true' : 'false' }}" aria-controls="sidebar-receptionist-accounts" id="sidebar-receptionist-accounts-toggle">
                    <i class="mdi mdi-cash-multiple menu-icon"></i>
                    <span class="menu-title">Accounts</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('product-or-service-request.*', 'my-transactions', 'billing.workbench') ? 'show' : '' }}" id="sidebar-receptionist-accounts">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('billing.workbench') ? 'active' : '' }}" href="{{ route('billing.workbench') }}" id="sidebar-receptionist-billing-workbench">
                                <i class="mdi mdi-view-dashboard-outline"></i> Billing Workbench
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('product-or-service-request.index') ? 'active' : '' }}" href="{{ route('product-or-service-request.index') }}" id="sidebar-receptionist-payment-requests">All Payment Requests</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('my-transactions') ? 'active' : '' }}" href="{{ route('my-transactions') }}" id="sidebar-receptionist-my-transactions">All My Transactions</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-receptionist-consultations" data-bs-target="#sidebar-receptionist-consultations" aria-expanded="{{ request()->routeIs('allPrevEncounters') ? 'true' : 'false' }}" aria-controls="sidebar-receptionist-consultations" id="sidebar-receptionist-consultations-toggle">
                    <i class="mdi mdi-stethoscope menu-icon"></i>
                    <span class="menu-title">Consultations</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('allPrevEncounters') ? 'show' : '' }}" id="sidebar-receptionist-consultations">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}" href="{{ route('allPrevEncounters') }}" id="sidebar-receptionist-prev-consultations">All Previous Consultations</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endhasanyrole

            {{-- ========================================
                 BILLER / ACCOUNTS SECTION
                 ======================================== --}}
            @hasanyrole('SUPERADMIN|ADMIN|ACCOUNTS|BILLER')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Billing</span>
            </li>
            <li class="nav-item {{ request()->routeIs('billing.workbench') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('billing.workbench') ? 'active' : '' }}" href="{{ route('billing.workbench') }}" id="sidebar-biller-workbench">
                    <i class="mdi mdi-cash-register menu-icon"></i>
                    <span class="menu-title">Billing Workbench</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('patient.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('patient.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-biller-patients" data-bs-target="#sidebar-biller-patients" aria-expanded="{{ request()->routeIs('patient.*') ? 'true' : 'false' }}" aria-controls="sidebar-biller-patients" id="sidebar-biller-patients-toggle">
                    <i class="mdi mdi-account-multiple-outline menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('patient.*') ? 'show' : '' }}" id="sidebar-biller-patients">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('patient.create') ? 'active' : '' }}" href="{{ route('patient.create') }}" id="sidebar-biller-patient-new">New Registration</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('patient.index') ? 'active' : '' }}" href="{{ route('patient.index') }}" id="sidebar-biller-patient-all">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('product-or-service-request.*', 'my-transactions') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('product-or-service-request.*', 'my-transactions') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-biller-accounts" data-bs-target="#sidebar-biller-accounts" aria-expanded="{{ request()->routeIs('product-or-service-request.*', 'my-transactions') ? 'true' : 'false' }}" aria-controls="sidebar-biller-accounts" id="sidebar-biller-accounts-toggle">
                    <i class="mdi mdi-cash-multiple menu-icon"></i>
                    <span class="menu-title">Accounts</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('product-or-service-request.*', 'my-transactions') ? 'show' : '' }}" id="sidebar-biller-accounts">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('product-or-service-request.index') ? 'active' : '' }}" href="{{ route('product-or-service-request.index') }}" id="sidebar-biller-payment-requests">All Payment Requests</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('my-transactions') ? 'active' : '' }}" href="{{ route('my-transactions') }}" id="sidebar-biller-my-transactions">All My Transactions</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-biller-consultations" data-bs-target="#sidebar-biller-consultations" aria-expanded="{{ request()->routeIs('allPrevEncounters') ? 'true' : 'false' }}" aria-controls="sidebar-biller-consultations" id="sidebar-biller-consultations-toggle">
                    <i class="mdi mdi-stethoscope menu-icon"></i>
                    <span class="menu-title">Consultations</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('allPrevEncounters') ? 'show' : '' }}" id="sidebar-biller-consultations">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}" href="{{ route('allPrevEncounters') }}" id="sidebar-biller-prev-consultations">All Previous Consultations</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endhasanyrole

            {{-- ========================================
                 PHARMACIST SECTION
                 ======================================== --}}
            @hasanyrole('SUPERADMIN|ADMIN|PHARMACIST')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Pharmacy</span>
            </li>
            <li class="nav-item {{ request()->routeIs('pharmacy.workbench') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('pharmacy.workbench') ? 'active' : '' }}" href="{{ route('pharmacy.workbench') }}" id="sidebar-pharmacist-workbench">
                    <i class="mdi mdi-pill menu-icon"></i>
                    <span class="menu-title">Pharmacy Workbench</span>
                </a>
            </li>
            <!-- Pharmacy Queue - Old DataTable Approach
            <li class="nav-item {{ request()->routeIs('product-requests.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('product-requests.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-pharmacist-queue-old" data-bs-target="#sidebar-pharmacist-queue-old" aria-expanded="{{ request()->routeIs('product-requests.*') ? 'true' : 'false' }}" aria-controls="sidebar-pharmacist-queue-old" id="sidebar-pharmacist-queue-old-toggle">
                    <i class="mdi mdi-format-list-checks menu-icon"></i>
                    <span class="menu-title">Queue</span>
                    <span class="badge badge-warning ml-2" style="font-size: 9px;">Old</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('product-requests.*') ? 'show' : '' }}" id="sidebar-pharmacist-queue-old">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('product-requests.index') && !request()->has('history') ? 'active' : '' }}" href="{{ route('product-requests.index') }}" id="sidebar-pharmacist-queue-current-old">My Current Queue</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('product-requests.index') && request()->has('history') ? 'active' : '' }}" href="{{ route('product-requests.index', ['history' => true]) }}" id="sidebar-pharmacist-queue-history-old">History</a>
                        </li>
                    </ul>
                </div>
            </li>
            -->
            <li class="nav-item {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-pharmacist-products" data-bs-target="#sidebar-pharmacist-products" aria-expanded="{{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'true' : 'false' }}" aria-controls="sidebar-pharmacist-products" id="sidebar-pharmacist-products-toggle">
                    <i class="mdi mdi-package-variant menu-icon"></i>
                    <span class="menu-title">Product Management</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'show' : '' }}" id="sidebar-pharmacist-products">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('product-category.*') ? 'active' : '' }}" href="{{ route('product-category.index') }}" id="sidebar-pharmacist-product-categories">Categories</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('stores.*') ? 'active' : '' }}" href="{{ route('stores.index') }}" id="sidebar-pharmacist-stores">Stores</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}" id="sidebar-pharmacist-products-list">Products</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-pharmacist-patients" data-bs-target="#sidebar-pharmacist-patients" aria-expanded="false" aria-controls="sidebar-pharmacist-patients" id="sidebar-pharmacist-patients-toggle">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="sidebar-pharmacist-patients">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}" id="sidebar-pharmacist-patient-all">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endhasanyrole

            {{-- ========================================
                 STORE / INVENTORY SECTION
                 ======================================== --}}
            @hasanyrole('SUPERADMIN|ADMIN|STORE')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Store / Inventory</span>
            </li>
            <li class="nav-item {{ request()->routeIs('inventory.store-workbench.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('inventory.store-workbench.*') ? 'active' : '' }}" href="{{ route('inventory.store-workbench.index') }}" id="sidebar-store-workbench">
                    <i class="mdi mdi-store menu-icon"></i>
                    <span class="menu-title">Store Workbench</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-store-products" data-bs-target="#sidebar-store-products" aria-expanded="{{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'true' : 'false' }}" aria-controls="sidebar-store-products" id="sidebar-store-products-toggle">
                    <i class="mdi mdi-package-variant menu-icon"></i>
                    <span class="menu-title">Product Management</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'show' : '' }}" id="sidebar-store-products">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('product-category.*') ? 'active' : '' }}" href="{{ route('product-category.index') }}" id="sidebar-store-product-categories">Categories</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('stores.*') ? 'active' : '' }}" href="{{ route('stores.index') }}" id="sidebar-store-stores">Stores</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}" id="sidebar-store-products-list">Products</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-store-services" data-bs-target="#sidebar-store-services" aria-expanded="{{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'true' : 'false' }}" aria-controls="sidebar-store-services" id="sidebar-store-services-toggle">
                    <i class="mdi mdi-cog-outline menu-icon"></i>
                    <span class="menu-title">Services Management</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'show' : '' }}" id="sidebar-store-services">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('services-category.index') }}" id="sidebar-store-service-categories">Categories</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('services.index', ['category' => appsettings('investigation_category_id', 2)]) }}" id="sidebar-store-medlab-services">Med Lab Services</a>
                        </li>
                        @if(appsettings('procedure_category_id'))
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('services.index', ['category' => appsettings('procedure_category_id')]) }}" id="sidebar-store-procedures">Procedures</a>
                        </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('procedure-categories.index') }}" id="sidebar-store-procedure-categories">Procedure Categories</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('inventory.purchase-orders.*', 'inventory.requisitions.*', 'inventory.expenses.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('inventory.purchase-orders.*', 'inventory.requisitions.*', 'inventory.expenses.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-store-inventory" data-bs-target="#sidebar-store-inventory" aria-expanded="{{ request()->routeIs('inventory.purchase-orders.*', 'inventory.requisitions.*', 'inventory.expenses.*') ? 'true' : 'false' }}" aria-controls="sidebar-store-inventory" id="sidebar-store-inventory-toggle">
                    <i class="mdi mdi-clipboard-list-outline menu-icon"></i>
                    <span class="menu-title">Inventory Operations</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('inventory.purchase-orders.*', 'inventory.requisitions.*', 'inventory.expenses.*') ? 'show' : '' }}" id="sidebar-store-inventory">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('inventory.purchase-orders.*') ? 'active' : '' }}" href="{{ route('inventory.purchase-orders.index') }}" id="sidebar-store-purchase-orders">
                                <i class="mdi mdi-cart-arrow-down"></i> Purchase Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('inventory.requisitions.*') ? 'active' : '' }}" href="{{ route('inventory.requisitions.index') }}" id="sidebar-store-requisitions">
                                <i class="mdi mdi-swap-horizontal"></i> Requisitions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('inventory.expenses.*') ? 'active' : '' }}" href="{{ route('inventory.expenses.index') }}" id="sidebar-store-expenses">
                                <i class="mdi mdi-cash-minus"></i> Expenses
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-store-patients" data-bs-target="#sidebar-store-patients" aria-expanded="false" aria-controls="sidebar-store-patients" id="sidebar-store-patients-toggle">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="sidebar-store-patients">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}" id="sidebar-store-patient-all">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endhasanyrole

            {{-- ========================================
                 NURSING SECTION
                 ======================================== --}}
            @hasanyrole('SUPERADMIN|ADMIN|NURSE')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Nursing</span>
            </li>
            <li class="nav-item {{ request()->routeIs('nursing-workbench.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('nursing-workbench.*') ? 'active' : '' }}" href="{{ route('nursing-workbench.index') }}" id="sidebar-nurse-workbench">
                    <i class="mdi mdi-heart-pulse menu-icon"></i>
                    <span class="menu-title">Nursing Workbench</span>
                </a>
            </li>
            <!-- Nursing Queue - Old DataTable Approach
            <li class="nav-item {{ request()->routeIs('vitals.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('vitals.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-nurse-queue-old" data-bs-target="#sidebar-nurse-queue-old" aria-expanded="{{ request()->routeIs('vitals.*') ? 'true' : 'false' }}" aria-controls="sidebar-nurse-queue-old" id="sidebar-nurse-queue-old-toggle">
                    <i class="mdi mdi-clipboard-pulse-outline menu-icon"></i>
                    <span class="menu-title">Queue</span>
                    <span class="badge badge-warning ml-2" style="font-size: 9px;">Old</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('vitals.*') ? 'show' : '' }}" id="sidebar-nurse-queue-old">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('vitals.index') ? 'active' : '' }}" href="{{ route('vitals.index') }}" id="sidebar-nurse-queue-current-old">My Current Queue</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('vitals.index', ['history' => true]) }}" id="sidebar-nurse-queue-history-old">History</a>
                        </li>
                    </ul>
                </div>
            </li>
            -->
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-nurse-admissions" data-bs-target="#sidebar-nurse-admissions" aria-expanded="false" aria-controls="sidebar-nurse-admissions" id="sidebar-nurse-admissions-toggle">
                    <i class="mdi mdi-hotel menu-icon"></i>
                    <span class="menu-title">Admissions</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="sidebar-nurse-admissions">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('admission-requests.index') }}" id="sidebar-nurse-bed-requests">Bed Requests</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('beds.index') }}" id="sidebar-nurse-manage-beds">Manage Beds</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-nurse-patients" data-bs-target="#sidebar-nurse-patients" aria-expanded="false" aria-controls="sidebar-nurse-patients" id="sidebar-nurse-patients-toggle">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="sidebar-nurse-patients">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}" id="sidebar-nurse-patient-all">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            <!-- Patient Lookup - Old DataTable Approach
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-nurse-patient-lookup-old" data-bs-target="#sidebar-nurse-patient-lookup-old" aria-expanded="false" aria-controls="sidebar-nurse-patient-lookup-old" id="sidebar-nurse-patient-lookup-old-toggle">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patient Lookup</span>
                    <span class="badge badge-warning ml-2" style="font-size: 9px;">Old</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="sidebar-nurse-patient-lookup-old">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('add-to-queue') }}" id="sidebar-nurse-patient-search-old">Search</a>
                        </li>
                    </ul>
                </div>
            </li>
            -->
            @endhasanyrole

            {{-- ========================================
                 LAB SCIENTIST SECTION
                 ======================================== --}}
            @hasanyrole('SUPERADMIN|ADMIN|LAB SCIENTIST')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Medical Laboratory</span>
            </li>
            <li class="nav-item {{ request()->routeIs('lab.workbench') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('lab.workbench') ? 'active' : '' }}" href="{{ route('lab.workbench') }}" id="sidebar-lab-workbench">
                    <i class="mdi mdi-flask-outline menu-icon"></i>
                    <span class="menu-title">Lab Workbench</span>
                </a>
            </li>
            <!-- Med Lab Queue - Old DataTable Approach
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-lab-queue-old" data-bs-target="#sidebar-lab-queue-old" aria-expanded="false" aria-controls="sidebar-lab-queue-old" id="sidebar-lab-queue-old-toggle">
                    <i class="mdi mdi-test-tube menu-icon"></i>
                    <span class="menu-title">Med Lab Queue</span>
                    <span class="badge badge-warning ml-2" style="font-size: 9px;">Old</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="sidebar-lab-queue-old">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('service-requests.index') }}" id="sidebar-lab-queue-current-old">My Current Queue</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('service-requests.index', ['history' => true]) }}" id="sidebar-lab-queue-history-old">History</a>
                        </li>
                    </ul>
                </div>
            </li>
            -->
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-lab-patients" data-bs-target="#sidebar-lab-patients" aria-expanded="false" aria-controls="sidebar-lab-patients" id="sidebar-lab-patients-toggle">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="sidebar-lab-patients">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}" id="sidebar-lab-patient-all">All Patients</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.create') }}" id="sidebar-lab-patient-new">New Registration</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endhasanyrole

            {{-- ========================================
                 RADIOLOGIST SECTION
                 ======================================== --}}
            @hasanyrole('SUPERADMIN|ADMIN|RADIOLOGIST')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Imaging / Radiology</span>
            </li>
            <li class="nav-item {{ request()->routeIs('imaging.workbench') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('imaging.workbench') ? 'active' : '' }}" href="{{ route('imaging.workbench') }}" id="sidebar-radiology-workbench">
                    <i class="mdi mdi-radioactive menu-icon"></i>
                    <span class="menu-title">Imaging Workbench</span>
                </a>
            </li>
            <!-- Imaging Queue - Old DataTable Approach
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-radiology-queue-old" data-bs-target="#sidebar-radiology-queue-old" aria-expanded="false" aria-controls="sidebar-radiology-queue-old" id="sidebar-radiology-queue-old-toggle">
                    <i class="mdi mdi-image-multiple menu-icon"></i>
                    <span class="menu-title">Imaging Queue</span>
                    <span class="badge badge-warning ml-2" style="font-size: 9px;">Old</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="sidebar-radiology-queue-old">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('imaging-requests.index') }}" id="sidebar-radiology-queue-current-old">My Current Queue</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('imaging-requests.index', ['history' => true]) }}" id="sidebar-radiology-queue-history-old">History</a>
                        </li>
                    </ul>
                </div>
            </li>
            -->
            <li class="nav-item {{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-radiology-services" data-bs-target="#sidebar-radiology-services" aria-expanded="{{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'true' : 'false' }}" aria-controls="sidebar-radiology-services" id="sidebar-radiology-services-toggle">
                    <i class="mdi mdi-cog-outline menu-icon"></i>
                    <span class="menu-title">Services Management</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'show' : '' }}" id="sidebar-radiology-services">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('services-category.index') }}" id="sidebar-radiology-service-categories">Categories</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('services.index', ['category' => appsettings('imaging_category_id', 6)]) }}" id="sidebar-radiology-imaging-services">Imaging Services</a>
                        </li>
                        @if(appsettings('procedure_category_id'))
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('services.index', ['category' => appsettings('procedure_category_id')]) }}" id="sidebar-radiology-procedures">Procedures</a>
                        </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('procedure-categories.index') }}" id="sidebar-radiology-procedure-categories">Procedure Categories</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-radiology-patients" data-bs-target="#sidebar-radiology-patients" aria-expanded="false" aria-controls="sidebar-radiology-patients" id="sidebar-radiology-patients-toggle">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="sidebar-radiology-patients">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}" id="sidebar-radiology-patient-all">All Patients</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.create') }}" id="sidebar-radiology-patient-new">New Registration</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endhasanyrole

            {{-- ========================================
                 DOCTOR SECTION
                 ======================================== --}}
            @hasanyrole('SUPERADMIN|ADMIN|DOCTOR')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Doctors</span>
            </li>
            <li class="nav-item {{ request()->routeIs('encounters.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('encounters.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-doctor-consultations" data-bs-target="#sidebar-doctor-consultations" aria-expanded="{{ request()->routeIs('encounters.*') ? 'true' : 'false' }}" aria-controls="sidebar-doctor-consultations" id="sidebar-doctor-consultations-toggle">
                    <i class="mdi mdi-doctor menu-icon"></i>
                    <span class="menu-title">Consultations</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('encounters.*') ? 'show' : '' }}" id="sidebar-doctor-consultations">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('encounters.index') }}" id="sidebar-doctor-encounters">All Encounters</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-doctor-patients" data-bs-target="#sidebar-doctor-patients" aria-expanded="false" aria-controls="sidebar-doctor-patients" id="sidebar-doctor-patients-toggle">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="sidebar-doctor-patients">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}" id="sidebar-doctor-patient-all">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-doctor-patient-lookup" data-bs-target="#sidebar-doctor-patient-lookup" aria-expanded="false" aria-controls="sidebar-doctor-patient-lookup" id="sidebar-doctor-patient-lookup-toggle">
                    <i class="mdi mdi-account-search-outline menu-icon"></i>
                    <span class="menu-title">Patient Lookup</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="sidebar-doctor-patient-lookup">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('add-to-queue') }}" id="sidebar-doctor-patient-search">Search</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endhasanyrole

            {{-- ========================================
                 HMO EXECUTIVE SECTION
                 ======================================== --}}
            @hasanyrole('SUPERADMIN|ADMIN|HMO Executive')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">HMO Executive</span>
            </li>
            <li class="nav-item {{ request()->routeIs('hmo.workbench') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hmo.workbench') ? 'active' : '' }}" href="{{ route('hmo.workbench') }}" id="sidebar-hmo-workbench">
                    <i class="mdi mdi-hospital-building menu-icon"></i>
                    <span class="menu-title">HMO Workbench</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('hmo.reports') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hmo.reports') ? 'active' : '' }}" href="{{ route('hmo.reports') }}" id="sidebar-hmo-reports">
                    <i class="mdi mdi-file-chart menu-icon"></i>
                    <span class="menu-title">HMO Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hmo-patients" data-bs-target="#sidebar-hmo-patients" aria-expanded="false" aria-controls="sidebar-hmo-patients" id="sidebar-hmo-patients-toggle">
                    <i class="mdi mdi-account-group menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="sidebar-hmo-patients">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}" id="sidebar-hmo-patient-all">All Patients</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index', ['hmo_only' => 1]) }}" id="sidebar-hmo-patient-hmo">HMO Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endhasanyrole

            {{-- ========================================
                 ADMINISTRATION SECTION (SUPERADMIN/ADMIN Only)
                 ======================================== --}}
            @hasanyrole('SUPERADMIN|ADMIN')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Administration</span>
            </li>
            <li class="nav-item {{ request()->routeIs('roles.*', 'permissions.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('roles.*', 'permissions.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-admin-access-control" data-bs-target="#sidebar-admin-access-control" aria-expanded="{{ request()->routeIs('roles.*', 'permissions.*') ? 'true' : 'false' }}" aria-controls="sidebar-admin-access-control" id="sidebar-admin-access-control-toggle">
                    <i class="mdi mdi-shield-account menu-icon"></i>
                    <span class="menu-title">Access Control</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('roles.*', 'permissions.*') ? 'show' : '' }}" id="sidebar-admin-access-control">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}" id="sidebar-admin-roles">Roles</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('permissions.*') ? 'active' : '' }}" href="{{ route('permissions.index') }}" id="sidebar-admin-permissions">Permissions</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('hospital-config.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hospital-config.*') ? 'active' : '' }}" href="{{ route('hospital-config.index') }}" id="sidebar-admin-hospital-config">
                    <i class="mdi mdi-cogs menu-icon"></i>
                    <span class="menu-title">Hospital Config</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('wards.*', 'beds.*', 'checklist-templates.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('wards.*', 'beds.*', 'checklist-templates.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-admin-ward-bed-setup" data-bs-target="#sidebar-admin-ward-bed-setup" aria-expanded="{{ request()->routeIs('wards.*', 'beds.*', 'checklist-templates.*') ? 'true' : 'false' }}" aria-controls="sidebar-admin-ward-bed-setup" id="sidebar-admin-ward-bed-setup-toggle">
                    <i class="mdi mdi-hospital-building menu-icon"></i>
                    <span class="menu-title">Ward & Bed Setup</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('wards.*', 'beds.*', 'checklist-templates.*') ? 'show' : '' }}" id="sidebar-admin-ward-bed-setup">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('wards.*') ? 'active' : '' }}" href="{{ route('wards.index') }}" id="sidebar-admin-wards">
                                <i class="mdi mdi-hospital-marker"></i> Wards
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('beds.*') ? 'active' : '' }}" href="{{ route('beds.index') }}" id="sidebar-admin-beds">
                                <i class="mdi mdi-bed"></i> Beds
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('checklist-templates.*') ? 'active' : '' }}" href="{{ route('checklist-templates.index') }}" id="sidebar-admin-checklist-templates">
                                <i class="mdi mdi-format-list-checks"></i> Checklist Templates
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('vaccine-schedule.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('vaccine-schedule.*') ? 'active' : '' }}" href="{{ route('vaccine-schedule.index') }}" id="sidebar-admin-vaccine-schedule">
                    <i class="mdi mdi-needle menu-icon"></i>
                    <span class="menu-title">Vaccine Schedule</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('banks.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('banks.*') ? 'active' : '' }}" href="{{ route('banks.index') }}" id="sidebar-admin-bank-config">
                    <i class="mdi mdi-bank menu-icon"></i>
                    <span class="menu-title">Bank Config</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('hmo-tariffs.*', 'hmo.index') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hmo-tariffs.*', 'hmo.index') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-admin-hmo-management" data-bs-target="#sidebar-admin-hmo-management" aria-expanded="{{ request()->routeIs('hmo-tariffs.*', 'hmo.index') ? 'true' : 'false' }}" aria-controls="sidebar-admin-hmo-management" id="sidebar-admin-hmo-management-toggle">
                    <i class="mdi mdi-medical-bag menu-icon"></i>
                    <span class="menu-title">HMO Management</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('hmo-tariffs.*', 'hmo.index') ? 'show' : '' }}" id="sidebar-admin-hmo-management">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hmo-tariffs.*') ? 'active' : '' }}" href="{{ route('hmo-tariffs.index') }}" id="sidebar-admin-hmo-tariffs">Tariff Management</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hmo.index') ? 'active' : '' }}" href="{{ route('hmo.index') }}" id="sidebar-admin-hmo-settings">HMO Settings</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}" href="{{ route('audit-logs.index') }}" id="sidebar-admin-audit-logs">
                    <i class="mdi mdi-history menu-icon"></i>
                    <span class="menu-title">Audit Logs</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('import-export.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('import-export.*') ? 'active' : '' }}" href="{{ route('import-export.index') }}" id="sidebar-admin-import-export">
                    <i class="mdi mdi-database-import-outline menu-icon"></i>
                    <span class="menu-title">Data Import/Export</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('staff.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('staff.*') ? 'active' : '' }}" href="{{ route('staff.index') }}" id="sidebar-admin-staff-management">
                    <i class="mdi mdi-account-group menu-icon"></i>
                    <span class="menu-title">Staff Management</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('specializations.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('specializations.*') ? 'active' : '' }}" href="{{ route('specializations.index') }}" id="sidebar-admin-specializations">
                    <i class="mdi mdi-briefcase-outline menu-icon"></i>
                    <span class="menu-title">Specializations</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('clinics.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('clinics.*') ? 'active' : '' }}" href="{{ route('clinics.index') }}" id="sidebar-admin-clinics">
                    <i class="mdi mdi-hospital-building menu-icon"></i>
                    <span class="menu-title">Clinics Management</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('departments.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}" href="{{ route('departments.index') }}" id="sidebar-admin-departments">
                    <i class="mdi mdi-office-building-outline menu-icon"></i>
                    <span class="menu-title">Departments</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-admin-patients" data-bs-target="#sidebar-admin-patients" aria-expanded="false" aria-controls="sidebar-admin-patients" id="sidebar-admin-patients-toggle">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="sidebar-admin-patients">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}" id="sidebar-admin-patient-all">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('transactions') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('transactions') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-admin-finance" data-bs-target="#sidebar-admin-finance" aria-expanded="{{ request()->routeIs('transactions') ? 'true' : 'false' }}" aria-controls="sidebar-admin-finance" id="sidebar-admin-finance-toggle">
                    <i class="mdi mdi-chart-line menu-icon"></i>
                    <span class="menu-title">Finance</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('transactions') ? 'show' : '' }}" id="sidebar-admin-finance">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('transactions') ? 'active' : '' }}" href="{{ route('transactions') }}" id="sidebar-admin-all-transactions">All Transactions</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('inventory.purchase-orders.accounts-payable') ? 'active' : '' }}" href="{{ route('inventory.purchase-orders.accounts-payable') }}">
                                <i class="mdi mdi-currency-ngn"></i> Accounts Payable
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            {{-- Accountant Section --}}
            @hasanyrole('SUPERADMIN|ADMIN|ACCOUNTS')
            <li class="nav-item {{ request()->routeIs('accounting.*', 'inventory.purchase-orders.accounts-payable', 'inventory.expenses.*', 'banks.*') ? 'active' : '' }}">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-accountant" data-bs-target="#sidebar-accountant" aria-expanded="{{ request()->routeIs('accounting.*', 'inventory.purchase-orders.accounts-payable', 'inventory.expenses.*', 'banks.*') ? 'true' : 'false' }}" aria-controls="sidebar-accountant">
                    <i class="mdi mdi-calculator menu-icon"></i>
                    <span class="menu-title">Accounting</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('accounting.*', 'inventory.purchase-orders.accounts-payable', 'inventory.expenses.*', 'banks.*') ? 'show' : '' }}" id="sidebar-accountant">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.dashboard') ? 'active' : '' }}" href="{{ route('accounting.dashboard') }}">
                                <i class="mdi mdi-view-dashboard-outline"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.journal-entries.*') ? 'active' : '' }}" href="{{ route('accounting.journal-entries.index') }}">
                                <i class="mdi mdi-book-open-page-variant"></i> Journal Entries
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.chart-of-accounts.*') ? 'active' : '' }}" href="{{ route('accounting.chart-of-accounts.index') }}">
                                <i class="mdi mdi-file-tree"></i> Chart of Accounts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.reports.*') ? 'active' : '' }}" href="{{ route('accounting.reports.index') }}">
                                <i class="mdi mdi-chart-bar"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.credit-notes.*') ? 'active' : '' }}" href="{{ route('accounting.credit-notes.index') }}">
                                <i class="mdi mdi-file-document-outline"></i> Credit Notes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.periods') ? 'active' : '' }}" href="{{ route('accounting.periods') }}">
                                <i class="mdi mdi-calendar-range"></i> Fiscal Periods
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('inventory.purchase-orders.accounts-payable') ? 'active' : '' }}" href="{{ route('inventory.purchase-orders.accounts-payable') }}">
                                <i class="mdi mdi-currency-ngn"></i> Accounts Payable
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('inventory.expenses.*') ? 'active' : '' }}" href="{{ route('inventory.expenses.index') }}">
                                <i class="mdi mdi-cash-minus"></i> Expenses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('banks.*') ? 'active' : '' }}" href="{{ route('banks.index') }}">
                                <i class="mdi mdi-bank"></i> Banks
                            </a>
                        </li>
                        {{-- NEW ACCOUNTING MODULES --}}
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.petty-cash.*') ? 'active' : '' }}" href="{{ route('accounting.petty-cash.index') }}">
                                <i class="mdi mdi-cash-register"></i> Petty Cash
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.transfers.*') ? 'active' : '' }}" href="{{ route('accounting.transfers.index') }}">
                                <i class="mdi mdi-bank-transfer"></i> Bank Transfers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.bank-reconciliation.*') ? 'active' : '' }}" href="{{ route('accounting.bank-reconciliation.index') }}">
                                <i class="mdi mdi-bank-check"></i> Bank Reconciliation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.patient-deposits.*') ? 'active' : '' }}" href="{{ route('accounting.patient-deposits.index') }}">
                                <i class="mdi mdi-account-cash"></i> Patient Deposits
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.fixed-assets.*') ? 'active' : '' }}" href="{{ route('accounting.fixed-assets.index') }}">
                                <i class="mdi mdi-package-variant-closed"></i> Fixed Assets
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.liabilities.*') ? 'active' : '' }}" href="{{ route('accounting.liabilities.index') }}">
                                <i class="mdi mdi-credit-card-clock"></i> Liabilities
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.leases.*') ? 'active' : '' }}" href="{{ route('accounting.leases.index') }}">
                                <i class="mdi mdi-file-document-edit"></i> Leases (IFRS 16)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.cost-centers.*') ? 'active' : '' }}" href="{{ route('accounting.cost-centers.index') }}">
                                <i class="mdi mdi-sitemap"></i> Cost Centers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.capex.*') ? 'active' : '' }}" href="{{ route('accounting.capex.index') }}">
                                <i class="mdi mdi-office-building"></i> CAPEX Projects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.budgets.*') ? 'active' : '' }}" href="{{ route('accounting.budgets.index') }}">
                                <i class="mdi mdi-calculator-variant"></i> Budgets
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.cash-flow-forecast.*') ? 'active' : '' }}" href="{{ route('accounting.cash-flow-forecast.index') }}">
                                <i class="mdi mdi-chart-timeline-variant"></i> Cash Flow Forecast
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.kpi.*') ? 'active' : '' }}" href="{{ route('accounting.kpi.dashboard') }}">
                                <i class="mdi mdi-gauge"></i> Financial KPIs
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            @endhasanyrole
            @endhasanyrole

            {{-- ========================================
                 HUMAN RESOURCES SECTION
                 ======================================== --}}
            @hasanyrole('SUPERADMIN|ADMIN|HR MANAGER')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Human Resources</span>
            </li>
            @can('hr-workbench.access')
            <li class="nav-item {{ request()->routeIs('hr.workbench.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hr.workbench.*') ? 'active' : '' }}" href="{{ route('hr.workbench.index') }}" id="sidebar-hr-workbench">
                    <i class="mdi mdi-view-dashboard-outline menu-icon"></i>
                    <span class="menu-title">HR Workbench</span>
                </a>
            </li>
            @endcan
            @canany(['leave-type.view', 'leave-request.view', 'leave-balance.view'])
            <li class="nav-item {{ request()->routeIs('hr.leave-types.*', 'hr.leave-requests.*', 'hr.leave-balances.*', 'hr.leave-calendar.*') ? 'active' : '' }}">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hr-leave" data-bs-target="#sidebar-hr-leave" aria-expanded="{{ request()->routeIs('hr.leave-types.*', 'hr.leave-requests.*', 'hr.leave-balances.*', 'hr.leave-calendar.*') ? 'true' : 'false' }}" aria-controls="sidebar-hr-leave" id="sidebar-hr-leave-toggle">
                    <i class="mdi mdi-calendar-clock menu-icon"></i>
                    <span class="menu-title">Leave Management</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('hr.leave-types.*', 'hr.leave-requests.*', 'hr.leave-balances.*', 'hr.leave-calendar.*') ? 'show' : '' }}" id="sidebar-hr-leave">
                    <ul class="nav flex-column sub-menu">
                        @can('leave-request.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.leave-calendar.*') ? 'active' : '' }}" href="{{ route('hr.leave-calendar.index') }}" id="sidebar-hr-leave-calendar">
                                <i class="mdi mdi-calendar-month mr-1"></i> Leave Calendar
                            </a>
                        </li>
                        @endcan
                        @can('leave-type.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.leave-types.*') ? 'active' : '' }}" href="{{ route('hr.leave-types.index') }}" id="sidebar-hr-leave-types">
                                Leave Types
                            </a>
                        </li>
                        @endcan
                        @can('leave-request.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.leave-requests.*') ? 'active' : '' }}" href="{{ route('hr.leave-requests.index') }}" id="sidebar-hr-leave-requests">
                                Leave Requests
                            </a>
                        </li>
                        @endcan
                        @can('leave-balance.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.leave-balances.*') ? 'active' : '' }}" href="{{ route('hr.leave-balances.index') }}" id="sidebar-hr-leave-balances">
                                Leave Balances
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcanany
            @canany(['disciplinary.view', 'suspension.view', 'termination.view'])
            <li class="nav-item {{ request()->routeIs('hr.disciplinary.*', 'hr.suspensions.*', 'hr.terminations.*') ? 'active' : '' }}">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hr-disciplinary" data-bs-target="#sidebar-hr-disciplinary" aria-expanded="{{ request()->routeIs('hr.disciplinary.*', 'hr.suspensions.*', 'hr.terminations.*') ? 'true' : 'false' }}" aria-controls="sidebar-hr-disciplinary" id="sidebar-hr-disciplinary-toggle">
                    <i class="mdi mdi-gavel menu-icon"></i>
                    <span class="menu-title">Disciplinary</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('hr.disciplinary.*', 'hr.suspensions.*', 'hr.terminations.*') ? 'show' : '' }}" id="sidebar-hr-disciplinary">
                    <ul class="nav flex-column sub-menu">
                        @can('disciplinary.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.disciplinary.*') ? 'active' : '' }}" href="{{ route('hr.disciplinary.index') }}" id="sidebar-hr-disciplinary-queries">
                                Queries
                            </a>
                        </li>
                        @endcan
                        @can('suspension.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.suspensions.*') ? 'active' : '' }}" href="{{ route('hr.suspensions.index') }}" id="sidebar-hr-suspensions">
                                Suspensions
                            </a>
                        </li>
                        @endcan
                        @can('termination.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.terminations.*') ? 'active' : '' }}" href="{{ route('hr.terminations.index') }}" id="sidebar-hr-terminations">
                                Terminations
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcanany
            @canany(['pay-head.view', 'salary-profile.view', 'payroll-batch.view'])
            <li class="nav-item {{ request()->routeIs('hr.pay-heads.*', 'hr.salary-profiles.*', 'hr.payroll.*') ? 'active' : '' }}">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hr-payroll" data-bs-target="#sidebar-hr-payroll" aria-expanded="{{ request()->routeIs('hr.pay-heads.*', 'hr.salary-profiles.*', 'hr.payroll.*') ? 'true' : 'false' }}" aria-controls="sidebar-hr-payroll" id="sidebar-hr-payroll-toggle">
                    <i class="mdi mdi-cash-multiple menu-icon"></i>
                    <span class="menu-title">Payroll</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('hr.pay-heads.*', 'hr.salary-profiles.*', 'hr.payroll.*') ? 'show' : '' }}" id="sidebar-hr-payroll">
                    <ul class="nav flex-column sub-menu">
                        @can('pay-head.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.pay-heads.*') ? 'active' : '' }}" href="{{ route('hr.pay-heads.index') }}" id="sidebar-hr-pay-heads">
                                Pay Heads
                            </a>
                        </li>
                        @endcan
                        @can('salary-profile.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.salary-profiles.*') ? 'active' : '' }}" href="{{ route('hr.salary-profiles.index') }}" id="sidebar-hr-salary-profiles">
                                Salary Profiles
                            </a>
                        </li>
                        @endcan
                        @can('payroll-batch.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.payroll.*') ? 'active' : '' }}" href="{{ route('hr.payroll.index') }}" id="sidebar-hr-payroll-batches">
                                Payroll Batches
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcanany
            @endhasanyrole

        </div>

        <!-- Bottom User Profile Section -->
        <li class="nav-item nav-profile pt-3 mt-auto border-top" style="border-color: rgba(255,255,255,0.1) !important;">
            <div class="px-3 pb-3">
                <div class="d-flex align-items-center mb-3">
                    <x-user-avatar :user="Auth::user()" width="40px" height="40px" class="border border-light" />
                    <div class="ml-3 overflow-hidden">
                        <div class="font-weight-bold text-white text-truncate" style="font-size: 0.95rem;">
                            {{ Auth::user()->firstname }} {{ Auth::user()->surname }}
                        </div>
                        <div class="text-muted small text-truncate" style="opacity: 0.7;">{{ Auth::user()->category->name ?? '' }}</div>
                    </div>
                </div>

                <!-- Leadership Role Badges -->
                @php
                    $staffProfile = Auth::user()->staff_profile;
                @endphp
                @if($staffProfile && ($staffProfile->is_unit_head || $staffProfile->is_dept_head))
                <div class="mb-3">
                    <div class="d-flex flex-wrap justify-content-center" style="gap: 0.5rem;">
                        @if($staffProfile->is_dept_head)
                            <span class="badge d-flex align-items-center" style="background: linear-gradient(135deg, #f6ad55, #ed8936); color: white; padding: 0.4rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">
                                <i class="mdi mdi-shield-crown mr-1"></i> Dept Head
                            </span>
                        @endif
                        @if($staffProfile->is_unit_head)
                            <span class="badge d-flex align-items-center" style="background: linear-gradient(135deg, #63b3ed, #4299e1); color: white; padding: 0.4rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">
                                <i class="mdi mdi-shield-account mr-1"></i> Unit Head
                            </span>
                        @endif
                    </div>
                </div>
                @endif

                <a href="{{ route('logout') }}"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                   class="btn btn-block d-flex align-items-center justify-content-center py-2"
                   style="background: rgba(255, 50, 50, 0.15); border: 1px solid rgba(255, 50, 50, 0.2); color: #ff6b6b; transition: all 0.3s;"
                   id="sidebar-bottom-logout">
                    <i class="mdi mdi-logout mr-2"></i>
                    <span style="font-weight: 500;">Logout</span>
                </a>
            </div>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                @csrf
            </form>
        </li>
    </ul>
</nav>
