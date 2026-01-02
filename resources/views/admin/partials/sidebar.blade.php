<nav class="ch-sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">
        <!-- Top Brand Section - Fixed -->
        <li class="nav-item nav-brand">
            <div class="text-center w-100">
                @if(appsettings()->logo)
                    <img src="data:image/jpeg;base64,{{ appsettings()->logo }}" alt="logo" style="max-width: 80px; height: auto; border-radius: 12px; margin-bottom: 0.5rem;" />
                @else
                    <div style="display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 12px; background: rgba(255, 255, 255, 0.2); font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 0.5rem;">
                        {{ strtoupper(substr(appsettings()->site_abbreviation ?? 'CH', 0, 2)) }}
                    </div>
                @endif
                <h6 class="mb-1" style="color: white; font-weight: 600; font-size: 0.9rem;">{{ env('APP_NAME') }}</h6>
                <p class="mb-0" style="color: rgba(255, 255, 255, 0.7); font-size: 0.75rem;">v{{ appsettings()->version ?? env('APP_VER') }}</p>
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
                    <span class="menu-title">Messenger @include('messenger.unread-count')</span>
                </a>
        </li>

            @hasanyrole('SUPERADMIN|ADMIN|RECEPTIONIST')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Receptionist</span>
            </li>
            <li class="nav-item {{ request()->routeIs('patient.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('patient.*') ? 'active' : '' }}" data-toggle="collapse" href="#new_patient" aria-expanded="{{ request()->routeIs('patient.*') ? 'true' : 'false' }}"
                    aria-controls="new_patient">
                    <i class="mdi mdi-account-multiple-outline menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('patient.*') ? 'show' : '' }}" id="new_patient">
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
                <a class="nav-link {{ request()->routeIs('add-to-queue') ? 'active' : '' }}" data-toggle="collapse" href="#returning_patient" aria-expanded="{{ request()->routeIs('add-to-queue') ? 'true' : 'false' }}"
                    aria-controls="returning_patient">
                    <i class="mdi mdi-account-search-outline menu-icon"></i>
                    <span class="menu-title">Patient Lookup</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('add-to-queue') ? 'show' : '' }}" id="returning_patient">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('add-to-queue') ? 'active' : '' }}" href="{{ route('add-to-queue') }}">Search</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('admission-requests.*', 'beds.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('admission-requests.*', 'beds.*') ? 'active' : '' }}" data-toggle="collapse" href="#admissions" aria-expanded="{{ request()->routeIs('admission-requests.*', 'beds.*') ? 'true' : 'false' }}"
                    aria-controls="admissions">
                    <i class="mdi mdi-bed-outline menu-icon"></i>
                    <span class="menu-title">Admissions</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('admission-requests.*', 'beds.*') ? 'show' : '' }}" id="admissions">
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
                <a class="nav-link" data-toggle="collapse" href="#bookings" aria-expanded="false"
                    aria-controls="bookings">
                    <i class="mdi mdi-calendar-clock-outline menu-icon"></i>
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
            <li class="nav-item {{ request()->routeIs('product-or-service-request.*', 'my-transactions') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('product-or-service-request.*', 'my-transactions') ? 'active' : '' }}" data-toggle="collapse" href="#acc_patient" aria-expanded="{{ request()->routeIs('product-or-service-request.*', 'my-transactions') ? 'true' : 'false' }}"
                    aria-controls="acc_patient">
                    <i class="mdi mdi-cash-multiple menu-icon"></i>
                    <span class="menu-title">Accounts</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('product-or-service-request.*', 'my-transactions') ? 'show' : '' }}" id="acc_patient">
                    <ul class="nav flex-column sub-menu">
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
                <a class="nav-link {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}" data-toggle="collapse" href="#prev_consult" aria-expanded="{{ request()->routeIs('allPrevEncounters') ? 'true' : 'false' }}"
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
                <a class="nav-link {{ request()->routeIs('roles.*', 'permissions.*') ? 'active' : '' }}" data-toggle="collapse" href="#access" aria-expanded="{{ request()->routeIs('roles.*', 'permissions.*') ? 'true' : 'false' }}" aria-controls="access">
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
                    <i class="mdi mdi-office-building-cog-outline menu-icon"></i>
                    <span class="menu-title">Hospital Config</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('staff.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('staff.*') ? 'active' : '' }}" href="{{ route('staff.index') }}">
                    <i class="mdi mdi-badge-account-outline menu-icon"></i>
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
            <li class="nav-item {{ request()->routeIs('hmo.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('hmo.*') ? 'active' : '' }}" href="{{ route('hmo.index') }}">
                    <i class="mdi mdi-shield-heart-outline menu-icon"></i>
                    <span class="menu-title">HMO Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#new_patient" aria-expanded="false"
                    aria-controls="new_patient">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="new_patient">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item {{ request()->routeIs('transactions') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('transactions') ? 'active' : '' }}" data-toggle="collapse" href="#all-finances" aria-expanded="{{ request()->routeIs('transactions') ? 'true' : 'false' }}"
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
                <a class="nav-link {{ request()->routeIs('product-requests.*') ? 'active' : '' }}" data-toggle="collapse" href="#pharm_queue" aria-expanded="{{ request()->routeIs('product-requests.*') ? 'true' : 'false' }}"
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
                <a class="nav-link {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'active' : '' }}" data-toggle="collapse" href="#products" aria-expanded="{{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'true' : 'false' }}"
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
                <a class="nav-link" data-toggle="collapse" href="#new_patient" aria-expanded="false"
                    aria-controls="new_patient">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="new_patient">
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
            <li class="nav-item {{ request()->routeIs('vitals.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('vitals.*') ? 'active' : '' }}" data-toggle="collapse" href="#nursing_" aria-expanded="{{ request()->routeIs('vitals.*') ? 'true' : 'false' }}"
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
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#admissions" aria-expanded="false"
                    aria-controls="admissions">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Admissions</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="admissions">
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
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#new_patient" aria-expanded="false"
                    aria-controls="new_patient">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="new_patient">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#returning_patient" aria-expanded="false"
                    aria-controls="returning_patient">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patient Lookup</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="returning_patient">
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
                <span class="nav-item-head">LAB/ INVESTIGATIONS</span>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#services" aria-expanded="false"
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
                            <a class="nav-link" href="{{ route('services.index') }}">Services</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#lab_queue" aria-expanded="false"
                    aria-controls="lab_queue">
                    <i class="mdi mdi-test-tube menu-icon"></i>
                    <span class="menu-title">Queue</span>
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
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#new_patient" aria-expanded="false"
                    aria-controls="new_patient">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="new_patient">
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
                <a class="nav-link" data-toggle="collapse" href="#consultations" aria-expanded="false"
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
                <a class="nav-link" data-toggle="collapse" href="#new_patient" aria-expanded="false"
                    aria-controls="new_patient">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="new_patient">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#returning_patient" aria-expanded="false"
                    aria-controls="returning_patient">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patient Lookup</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse" id="returning_patient">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('add-to-queue') }}">Search</a>
                        </li>
                    </ul>
                </div>
            </li>
        @endhasanyrole
        </div>

        <!-- Bottom User Profile Section - Fixed like Paystack Settings -->
        <li class="nav-item nav-profile">
            <a href="#" class="d-flex align-items-center text-decoration-none" style="color: white;">
                <img src="{!! url('storage/image/user/'.Auth::user()->filename) !!}" alt="profile" />
                <div class="ml-3 flex-grow-1" style="min-width: 0;">
                    <div class="nav-profile-text" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ Auth::user()->firstname }} {{ Auth::user()->surname }}
                    </div>
                    <span style="display: block;">{{ Auth::user()->is_admin }}</span>
                </div>
                <a href="{{ route('logout') }}"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                    class="ml-2"
                    style="color: rgba(255, 255, 255, 0.7); font-size: 1.2rem;"
                    title="Logout">
                    <i class="mdi mdi-logout"></i>
                </a>
            </a>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                @csrf
            </form>
        </li>
    </ul>
</nav>
