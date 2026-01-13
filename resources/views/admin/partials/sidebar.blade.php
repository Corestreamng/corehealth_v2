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
            <!-- Main Navigation -->
            <li class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                    <i class="mdi mdi-view-dashboard-outline menu-icon"></i>
                    <span class="menu-title">Dashboard</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('my-profile') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('my-profile') ? 'active' : '' }}" href="{{ route('my-profile') }}">
                    <i class="mdi mdi-account-circle menu-icon"></i>
                    <span class="menu-title">Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="popMessengerWindow()">
                    <i class="mdi mdi-message-text-outline menu-icon"></i>
                    <span class="menu-title">Messenger</span>
                </a>
        </li>

            @hasanyrole('SUPERADMIN|ADMIN|RECEPTIONIST')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Receptionist</span>
            </li>            <li class="nav-item {{ request()->routeIs('reception.workbench') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('reception.workbench') ? 'active' : '' }}" href="{{ route('reception.workbench') }}">
                    <i class="mdi mdi-desktop-mac-dashboard menu-icon"></i>
                    <span class="menu-title">Reception Workbench</span>
                </a>
            </li>            <li class="nav-item {{ request()->routeIs('patient.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('patient.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#new_patient_receptionist" data-bs-target="#new_patient_receptionist" aria-expanded="{{ request()->routeIs('patient.*') ? 'true' : 'false' }}"
                    aria-controls="new_patient_receptionist">
                    <i class="mdi mdi-account-multiple-outline menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('patient.*') ? 'show' : '' }}" id="new_patient_receptionist">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('patient.create') ? 'active' : '' }}" href="{{ route('patient.create') }}">New Registration</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('patient.index') ? 'active' : '' }}" href="{{ route('patient.index') }}">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('add-to-queue') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('add-to-queue') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#returning_patient_receptionist" data-bs-target="#returning_patient_receptionist" aria-expanded="{{ request()->routeIs('add-to-queue') ? 'true' : 'false' }}"
                    aria-controls="returning_patient_receptionist">
                    <i class="mdi mdi-account-search-outline menu-icon"></i>
                    <span class="menu-title">Patient Lookup</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('add-to-queue') ? 'show' : '' }}" id="returning_patient_receptionist">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('add-to-queue') ? 'active' : '' }}" href="{{ route('add-to-queue') }}">Search</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('admission-requests.*', 'beds.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('admission-requests.*', 'beds.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#admissions_receptionist" data-bs-target="#admissions_receptionist" aria-expanded="{{ request()->routeIs('admission-requests.*', 'beds.*') ? 'true' : 'false' }}"
                    aria-controls="admissions_receptionist">
                    <i class="mdi mdi-hotel menu-icon"></i>
                    <span class="menu-title">Admissions</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('admission-requests.*', 'beds.*') ? 'show' : '' }}" id="admissions_receptionist">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admission-requests.index') ? 'active' : '' }}" href="{{ route('admission-requests.index') }}">Bed requests</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('beds.index') ? 'active' : '' }}" href="{{ route('beds.index') }}">Manage Beds</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#bookings" data-bs-target="#bookings" aria-expanded="false"
                    aria-controls="bookings">
                    <i class="mdi mdi-calendar-check menu-icon"></i>
                    <span class="menu-title">Bookings</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="bookings">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="#">New Booking</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">View calender</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('product-or-service-request.*', 'my-transactions', 'billing.workbench') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('product-or-service-request.*', 'my-transactions', 'billing.workbench') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#acc_patient" data-bs-target="#acc_patient" aria-expanded="{{ request()->routeIs('product-or-service-request.*', 'my-transactions', 'billing.workbench') ? 'true' : 'false' }}"
                    aria-controls="acc_patient">
                    <i class="mdi mdi-cash-multiple menu-icon"></i>
                    <span class="menu-title">Accounts</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('product-or-service-request.*', 'my-transactions', 'billing.workbench') ? 'show' : '' }}" id="acc_patient">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('billing.workbench') ? 'active' : '' }}" href="{{ route('billing.workbench') }}">
                                <i class="mdi mdi-view-dashboard-outline"></i> Billing Workbench
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('product-or-service-request.index') ? 'active' : '' }}" href="{{ route('product-or-service-request.index') }}">All Payment
                                Requests</a>
                        </li>
                    </ul>
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('my-transactions') ? 'active' : '' }}" href="{{ route('my-transactions') }}">All My Transactions</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#prev_consult" data-bs-target="#prev_consult" aria-expanded="{{ request()->routeIs('allPrevEncounters') ? 'true' : 'false' }}"
                    aria-controls="prev_consult">
                    <i class="mdi mdi-stethoscope menu-icon"></i>
                    <span class="menu-title">Consultations</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('allPrevEncounters') ? 'show' : '' }}" id="prev_consult">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}" href="{{ route('allPrevEncounters') }}">All Previous Consultations</a>
                        </li>
                    </ul>
                </div>
            </li>
        @endhasanyrole
        @hasanyrole('SUPERADMIN|ADMIN')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Administration</span>
            </li>
            <li class="nav-item {{ request()->routeIs('roles.*', 'permissions.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('roles.*', 'permissions.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#access" data-bs-target="#access" aria-expanded="{{ request()->routeIs('roles.*', 'permissions.*') ? 'true' : 'false' }}" aria-controls="access">
                    <i class="mdi mdi-shield-account menu-icon"></i>
                    <span class="menu-title">Access Control</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('roles.*', 'permissions.*') ? 'show' : '' }}" id="access">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}">Roles</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('permissions.*') ? 'active' : '' }}" href="{{ route('permissions.index') }}">Permissions</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('hospital-config.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hospital-config.*') ? 'active' : '' }}" href="{{ route('hospital-config.index') }}">
                    <i class="mdi mdi-cogs menu-icon"></i>
                    <span class="menu-title">Hospital Config</span>
                </a>
            </li>
            @hasanyrole('SUPERADMIN|ADMIN')
            <!-- Ward & Bed Management -->
            <li class="nav-item {{ request()->routeIs('wards.*', 'beds.*', 'checklist-templates.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('wards.*', 'beds.*', 'checklist-templates.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#ward_bed_management" data-bs-target="#ward_bed_management" aria-expanded="{{ request()->routeIs('wards.*', 'beds.*', 'checklist-templates.*') ? 'true' : 'false' }}"
                    aria-controls="ward_bed_management">
                    <i class="mdi mdi-hospital-building menu-icon"></i>
                    <span class="menu-title">Ward & Bed Setup</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('wards.*', 'beds.*', 'checklist-templates.*') ? 'show' : '' }}" id="ward_bed_management">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('wards.*') ? 'active' : '' }}" href="{{ route('wards.index') }}">
                                <i class="mdi mdi-hospital-marker"></i> Wards
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('beds.*') ? 'active' : '' }}" href="{{ route('beds.index') }}">
                                <i class="mdi mdi-bed"></i> Beds
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('checklist-templates.*') ? 'active' : '' }}" href="{{ route('checklist-templates.index') }}">
                                <i class="mdi mdi-format-list-checks"></i> Checklist Templates
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('vaccine-schedule.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('vaccine-schedule.*') ? 'active' : '' }}" href="{{ route('vaccine-schedule.index') }}">
                    <i class="mdi mdi-needle menu-icon"></i>
                    <span class="menu-title">Vaccine Schedule</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('banks.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('banks.*') ? 'active' : '' }}" href="{{ route('banks.index') }}">
                    <i class="mdi mdi-bank menu-icon"></i>
                    <span class="menu-title">Bank Config</span>
                </a>
            </li>
            @endhasanyrole
            @hasanyrole('SUPERADMIN|ADMIN|HMO Executive')
            <li class="nav-item {{ request()->routeIs('hmo-tariffs.*', 'hmo.workbench', 'hmo.reports', 'hmo.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hmo-tariffs.*', 'hmo.workbench', 'hmo.reports', 'hmo.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#hmo_management" data-bs-target="#hmo_management" aria-expanded="{{ request()->routeIs('hmo-tariffs.*', 'hmo.workbench', 'hmo.reports', 'hmo.*') ? 'true' : 'false' }}"
                    aria-controls="hmo_management">
                    <i class="mdi mdi-medical-bag menu-icon"></i>
                    <span class="menu-title">HMO Management</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('hmo-tariffs.*', 'hmo.workbench', 'hmo.reports', 'hmo.*') ? 'show' : '' }}" id="hmo_management">
                    <ul class="nav flex-column sub-menu">
                        @hasanyrole('SUPERADMIN|ADMIN|HMO Executive')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hmo.workbench') ? 'active' : '' }}" href="{{ route('hmo.workbench') }}">HMO Workbench</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hmo.reports') ? 'active' : '' }}" href="{{ route('hmo.reports') }}">
                                <i class="mdi mdi-file-chart"></i> HMO Reports
                            </a>
                        </li>
                        @endhasanyrole
                        @hasanyrole('SUPERADMIN|ADMIN')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hmo-tariffs.*') ? 'active' : '' }}" href="{{ route('hmo-tariffs.index') }}">Tariff Management</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hmo.index') ? 'active' : '' }}" href="{{ route('hmo.index') }}">HMO Settings</a>
                        </li>
                        @endhasanyrole
                    </ul>
                </div>
            </li>
            @endhasanyrole
            <li class="nav-item {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}" href="{{ route('audit-logs.index') }}">
                    <i class="mdi mdi-history menu-icon"></i>
                    <span class="menu-title">Audit Logs</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('staff.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('staff.*') ? 'active' : '' }}" href="{{ route('staff.index') }}">
                    <i class="mdi mdi-account-group menu-icon"></i>
                    <span class="menu-title">Staff Management</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('specializations.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('specializations.*') ? 'active' : '' }}" href="{{ route('specializations.index') }}">
                    <i class="mdi mdi-briefcase-outline menu-icon"></i>
                    <span class="menu-title">Specializations Management</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('clinics.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('clinics.*') ? 'active' : '' }}" href="{{ route('clinics.index') }}">
                    <i class="mdi mdi-hospital-building menu-icon"></i>
                    <span class="menu-title">Clinics Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#new_patient_admin" data-bs-target="#new_patient_admin" aria-expanded="false"
                    aria-controls="new_patient_admin">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="new_patient_admin">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('transactions') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('transactions') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#all-finances" data-bs-target="#all-finances" aria-expanded="{{ request()->routeIs('transactions') ? 'true' : 'false' }}"
                    aria-controls="finances">
                    <i class="mdi mdi-chart-line menu-icon"></i>
                    <span class="menu-title">Finance</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('transactions') ? 'show' : '' }}" id="all-finances">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('transactions') ? 'active' : '' }}" href="{{ route('transactions') }}">All Transactions</a>
                        </li>
                    </ul>
                </div>
            </li>
        @endhasanyrole
        @hasanyrole('SUPERADMIN|ADMIN|STORE|PHARMACIST')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Store/ Pharmacy</span>
            </li>
            <li class="nav-item {{ request()->routeIs('product-requests.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('product-requests.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#pharm_queue" data-bs-target="#pharm_queue" aria-expanded="{{ request()->routeIs('product-requests.*') ? 'true' : 'false' }}"
                    aria-controls="pharm_queue">
                    <i class="mdi mdi-format-list-checks menu-icon"></i>
                    <span class="menu-title">Queue</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('product-requests.*') ? 'show' : '' }}" id="pharm_queue">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('product-requests.index') && !request()->has('history') ? 'active' : '' }}" href="{{ route('product-requests.index') }}">My Current Queue</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('product-requests.index') && request()->has('history') ? 'active' : '' }}"
                                href="{{ route('product-requests.index', ['history' => true]) }}">History</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#products" data-bs-target="#products" aria-expanded="{{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'true' : 'false' }}"
                    aria-controls="products">
                    <i class="mdi mdi-package-variant menu-icon"></i>
                    <span class="menu-title">Product Management</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'show' : '' }}" id="products">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('product-category.*') ? 'active' : '' }}" href="{{ route('product-category.index') }}">Categories</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('stores.*') ? 'active' : '' }}" href="{{ route('stores.index') }}">Stores</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">Products</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#new_patient_store" data-bs-target="#new_patient_store" aria-expanded="false"
                    aria-controls="new_patient_store">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="new_patient_store">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
        @endhasallroles
        @hasanyrole('SUPERADMIN|ADMIN|NURSE')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Nursing</span>
            </li>
            <li class="nav-item {{ request()->routeIs('nursing-workbench.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('nursing-workbench.*') ? 'active' : '' }}" href="{{ route('nursing-workbench.index') }}">
                    <i class="mdi mdi-hospital-box menu-icon"></i>
                    <span class="menu-title">Nursing Workbench</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('vitals.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('vitals.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#nursing_" data-bs-target="#nursing_" aria-expanded="{{ request()->routeIs('vitals.*') ? 'true' : 'false' }}"
                    aria-controls="nursing_">
                    <i class="mdi mdi-clipboard-pulse-outline menu-icon"></i>
                    <span class="menu-title">Queue</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('vitals.*') ? 'show' : '' }}" id="nursing_">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('vitals.index') ? 'active' : '' }}" href="{{ route('vitals.index') }}">My Current Queue</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('vitals.index', ['history' => true]) }}">History</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#admissions_nursing" data-bs-target="#admissions_nursing" aria-expanded="false"
                    aria-controls="admissions_nursing">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Admissions</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="admissions_nursing">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('admission-requests.index') }}">Bed requests</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('beds.index') }}">Manage Beds</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#new_patient_nursing" data-bs-target="#new_patient_nursing" aria-expanded="false"
                    aria-controls="new_patient_nursing">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="new_patient_nursing">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#returning_patient_nursing" data-bs-target="#returning_patient_nursing" aria-expanded="false"
                    aria-controls="returning_patient_nursing">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patient Lookup</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="returning_patient_nursing">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('add-to-queue') }}">Search</a>
                        </li>
                    </ul>
                </div>
            </li>
        @endhasallroles
        @hasanyrole('SUPERADMIN|ADMIN|LAB SCIENTIST|RADIOLOGIST')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">MED LAB</span>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#services" data-bs-target="#services" aria-expanded="false"
                    aria-controls="services">
                    <i class="mdi mdi-flask-outline menu-icon"></i>
                    <span class="menu-title">Services Management</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="services">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('services-category.index') }}">Categories</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('services.index', ['category' => appsettings('investigation_category_id', 2)]) }}">Med Lab Services</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('services.index', ['category' => appsettings('imaging_category_id', 6)]) }}">Imaging Services</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('lab.workbench') }}">
                    <i class="mdi mdi-monitor-dashboard menu-icon"></i>
                    <span class="menu-title">Lab Workbench</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#lab_queue" data-bs-target="#lab_queue" aria-expanded="false"
                    aria-controls="lab_queue">
                    <i class="mdi mdi-test-tube menu-icon"></i>
                    <span class="menu-title">Med Lab Queue</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="lab_queue">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('service-requests.index') }}">My Current Queue</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link"
                                href="{{ route('service-requests.index', ['history' => true]) }}">History</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="pt-2 pb-1">
                <span class="nav-item-head">IMAGING</span>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('imaging.workbench') ? 'active' : '' }}" href="{{ route('imaging.workbench') }}">
                    <i class="mdi mdi-monitor-dashboard menu-icon"></i>
                    <span class="menu-title">Imaging Workbench</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#imaging_queue" data-bs-target="#imaging_queue" aria-expanded="false"
                    aria-controls="imaging_queue">
                    <i class="mdi mdi-image-multiple menu-icon"></i>
                    <span class="menu-title">Imaging Queue</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="imaging_queue">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('imaging-requests.index') }}">My Current Queue</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link"
                                href="{{ route('imaging-requests.index', ['history' => true]) }}">History</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#new_patient_lab" data-bs-target="#new_patient_lab" aria-expanded="false"
                    aria-controls="new_patient_lab">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="new_patient_lab">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}">All Patients</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.create') }}">New Registration</a>
                        </li>
                    </ul>
                </div>
            </li>
        @endhasallroles
        @hasanyrole('SUPERADMIN|ADMIN|DOCTOR')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Doctors</span>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#consultations" data-bs-target="#consultations" aria-expanded="false"
                    aria-controls="consultations">
                    <i class="mdi mdi-doctor menu-icon"></i>
                    <span class="menu-title">Consultations</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="consultations">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('encounters.index') }}">All</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#new_patient_doctor" data-bs-target="#new_patient_doctor" aria-expanded="false"
                    aria-controls="new_patient_doctor">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="new_patient_doctor">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#returning_patient_doctor" data-bs-target="#returning_patient_doctor" aria-expanded="false"
                    aria-controls="returning_patient_doctor">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patient Lookup</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="returning_patient_doctor">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('add-to-queue') }}">Search</a>
                        </li>
                    </ul>
                </div>
            </li>
        @endhasanyrole
        @hasanyrole('SUPERADMIN|ADMIN|HMO Executive')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">HMO EXECUTIVE</span>
            </li>
            <li class="nav-item {{ request()->routeIs('hmo.workbench') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hmo.workbench') ? 'active' : '' }}" href="{{ route('hmo.workbench') }}">
                    <i class="mdi mdi-hospital-building menu-icon"></i>
                    <span class="menu-title">HMO Workbench</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('patient.index') ? 'active' : '' }}">
                <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#hmo_exec_patients" data-bs-target="#hmo_exec_patients" aria-expanded="false"
                    aria-controls="hmo_exec_patients">
                    <i class="mdi mdi-account-group menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="hmo_exec_patients">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}">All Patients</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index', ['hmo_only' => 1]) }}">HMO Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
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
                   style="background: rgba(255, 50, 50, 0.15); border: 1px solid rgba(255, 50, 50, 0.2); color: #ff6b6b; transition: all 0.3s;">
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
