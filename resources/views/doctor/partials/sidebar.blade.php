<nav class="sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">
        <li class="nav-item nav-profile border-bottom">
            <a href="#" class="nav-link flex-column">
                <div class="nav-profile-image">
                    <img src="{{Auth::user()->filename}}" alt="profile" />
                    <!--change to offline or busy as needed-->
                </div>
                <div class="nav-profile-text d-flex ml-0 mb-3 flex-column">
                    <span class="font-weight-semibold mb-1 mt-2 text-center">{{Auth::user()->firstname}} {{Auth::user()->othername}} {{Auth::user()->surname}}</span>
                    <span class="text-secondary icon-sm text-center">Role: {{Auth::user()->is_admin}}</span>
                </div>
            </a>
        </li>
        <li class="nav-item pt-3">
            <a class="nav-link d-block" href="index.html">
                <img class="sidebar-brand-logo" src="../assets/images/logo.svg" alt="" />
                <h4>{{env('APP_NAME')}}</h4>
                <div class="small font-weight-light pt-1">ver. {{env('APP_VER')}}</div>
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
        <li class="pt-2 pb-1">
            <span class="nav-item-head">Doctor</span>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('doctor.dashboard') }}">
                <i class="mdi mdi-compass-outline menu-icon"></i>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#new_patient" aria-expanded="false"
               aria-controls="new_patient">
                <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                <span class="menu-title">Procedures</span>
                <i class="menu-arrow"></i>
            </a>

        </li>
        <li class="nav-item">
            <a class="nav-link"  href="{{ route('doctor.consultations') }}" aria-expanded="false"
               aria-controls="returning_patient">
                <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                <span class="menu-title">Consultations</span>

            </a>

        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#admissions" aria-expanded="false"
               aria-controls="admissions">
                <i class="mdi mdi-crosshairs-gps menu-icon"></i>
                <span class="menu-title">Ward Rounds</span>
                <i class="menu-arrow"></i>
            </a>

        </li>

    </ul>
</nav>
