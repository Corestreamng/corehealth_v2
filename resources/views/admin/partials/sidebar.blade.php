<nav class="sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">
        <li class="nav-item nav-profile border-bottom">
            <a href="#" class="nav-link flex-column">
                <div class="nav-profile-image">
                    <img src="{!! url('storage/image/user/'.$user->filename) !!}" alt="profile" />
                    <!--change to offline or busy as needed-->
                </div>
                <div class="nav-profile-text d-flex ml-0 mb-3 flex-column">
                    <span class="font-weight-semibold mb-1 mt-2 text-center">{{ Auth::user()->firstname }}
                        {{ Auth::user()->othername }} {{ Auth::user()->surname }}</span>
                    <span class="text-secondary icon-sm text-center">Role: {{ Auth::user()->is_admin }}</span>
                </div>
            </a>
        </li>
        <li class="nav-item pt-3">
            <a class="nav-link d-block" href="/">
                <img class="sidebar-brand-logo" src="data:image/jpeg;base64,{{(appsettings()->logo) ?? '' }}" alt="Image" style="width: 80%" />
                <h4>{{ env('APP_NAME') }}</h4>
                <div class="small font-weight-light pt-1">ver. {{ appsettings()->version ?? env('APP_VER') }}</div>
                <hr>
                <div class="small font-weight-light pt-1">{{ appsettings()->site_abbreviation ?? '' }}</div>
                <hr>
            </a>
            <form class="d-flex align-items-center" action="#">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <i class="input-group-text border-0 mdi mdi-magnify"></i>
                    </div>
                    <input type="text" class="form-control border-0" placeholder="Search" />
                </div>
            </form>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('home') }}">
                <i class="mdi mdi-compass-outline menu-icon"></i>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('my-profile', Auth::id()) }}">
                <i class="fa fa-user menu-icon"></i>
                <span class="menu-title"> Profile</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" onclick="popMessengerWindow()">
                <i class="mdi mdi-email-outline menu-icon"></i>
                <span class="menu-title">Messenger @include('messenger.unread-count')</span>
            </a>
        </li>

        <li class="nav-item">
            <hr>
            <a class="nav-link" href="{{ route('logout') }}"
                onclick="event.preventDefault();
                     document.getElementById('logout-form').submit();">
                <i class="fa fa-sign-out"></i>
                <span class="menu-title text-danger">Logout</span>
            </a>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                @csrf
            </form>
            <hr>
        </li>

        @hasanyrole('SUPERADMIN|ADMIN|RECEPTIONIST')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Receptionist</span>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#new_patient" aria-expanded="false"
                    aria-controls="new_patient">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="menu-arrow"></i>
                </a>
                <div class="collapse" id="new_patient">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.create') }}">New Registration</a>
                        </li>
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
                    <span class="menu-title">Returning Patient</span>
                    <i class="menu-arrow"></i>
                </a>
                <div class="collapse" id="returning_patient">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('add-to-queue') }}">Search</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#admissions" aria-expanded="false"
                    aria-controls="admissions">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Admissions</span>
                    <i class="menu-arrow"></i>
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
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#bookings" aria-expanded="false"
                    aria-controls="bookings">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Bookings</span>
                    <i class="menu-arrow"></i>
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
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#acc_patient" aria-expanded="false"
                    aria-controls="acc_patient">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Accounts</span>
                    <i class="menu-arrow"></i>
                </a>
                <div class="collapse" id="acc_patient">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('product-or-service-request.index') }}">All Payment
                                Requests</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#prev_consult" aria-expanded="false"
                    aria-controls="prev_consult">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Consultations</span>
                    <i class="menu-arrow"></i>
                </a>
                <div class="collapse" id="prev_consult">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('allPrevEncounters') }}">All Previous Consultations</a>
                        </li>
                    </ul>
                </div>
            </li>
        @endhasanyrole
        @hasanyrole('SUPERADMIN|ADMIN')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Admin</span>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#access" aria-expanded="false" aria-controls="access">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Access Control</span>
                    <i class="menu-arrow"></i>
                </a>
                <div class="collapse" id="access">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('roles.index') }}">Roles</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('permissions.index') }}">Permissions</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('staff.index') }}">
                    <i class="mdi mdi-compass-outline menu-icon"></i>
                    <span class="menu-title">Staff Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('hmo.index') }}">
                    <i class="mdi mdi-compass-outline menu-icon"></i>
                    <span class="menu-title">HMO Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#new_patient" aria-expanded="false"
                    aria-controls="new_patient">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="menu-arrow"></i>
                </a>
                <div class="collapse" id="new_patient">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('patient.index') }}">All Patients</a>
                        </li>
                    </ul>
                </div>
            </li>
        @endhasanyrole
        @hasanyrole('SUPERADMIN|ADMIN|STORE|PHARMACIST')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Store/ Pharmacy</span>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#pharm_queue" aria-expanded="false"
                    aria-controls="pharm_queue">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Queue</span>
                    <i class="menu-arrow"></i>
                </a>
                <div class="collapse" id="pharm_queue">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('product-requests.index') }}">My Current Queue</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link"
                                href="{{ route('product-requests.index', ['history' => true]) }}">History</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#products" aria-expanded="false"
                    aria-controls="products">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Product Management</span>
                    <i class="menu-arrow"></i>
                </a>
                <div class="collapse" id="products">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('product-category.index') }}">Categories</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('stores.index') }}">Stores</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('products.index') }}">Products</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#services" aria-expanded="false"
                    aria-controls="services">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Services Management</span>
                    <i class="menu-arrow"></i>
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
                <a class="nav-link" data-toggle="collapse" href="#new_patient" aria-expanded="false"
                    aria-controls="new_patient">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Patients</span>
                    <i class="menu-arrow"></i>
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
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#nursing_" aria-expanded="false"
                    aria-controls="nursing_">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Queue</span>
                    <i class="menu-arrow"></i>
                </a>
                <div class="collapse" id="nursing_">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('vitals.index') }}">My Current Queue</a>
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
                    <i class="menu-arrow"></i>
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
                    <i class="menu-arrow"></i>
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
                    <span class="menu-title">Returning Patient</span>
                    <i class="menu-arrow"></i>
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
                <a class="nav-link" data-toggle="collapse" href="#lab_queue" aria-expanded="false"
                    aria-controls="lab_queue">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Queue</span>
                    <i class="menu-arrow"></i>
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
                    <i class="menu-arrow"></i>
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
        @hasanyrole('SUPERADMIN|ADMIN|DOCTOR')
            <li class="pt-2 pb-1">
                <span class="nav-item-head">Doctors</span>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#consultations" aria-expanded="false"
                    aria-controls="consultations">
                    <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                    <span class="menu-title">Consultations</span>
                    <i class="menu-arrow"></i>
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
                    <i class="menu-arrow"></i>
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
                    <span class="menu-title">Returning Patient</span>
                    <i class="menu-arrow"></i>
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
    </ul>
</nav>
