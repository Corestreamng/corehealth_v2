            {{-- ========================================
                 ADMINISTRATION SECTION (SUPERADMIN/ADMIN Only)
                 ======================================== --}}
            @hasanyrole('SUPERADMIN|ADMIN|super-admin')
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
            <li class="nav-item {{ request()->routeIs('clinic-note-templates.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('clinic-note-templates.*') ? 'active' : '' }}" href="{{ route('clinic-note-templates.index') }}" id="sidebar-admin-note-templates">
                    <i class="mdi mdi-file-document-edit menu-icon"></i>
                    <span class="menu-title">Note Templates</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('v1-result-templates.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('v1-result-templates.*') ? 'active' : '' }}" href="{{ route('v1-result-templates.index') }}" id="sidebar-admin-result-templates">
                    <i class="mdi mdi-flask-outline menu-icon"></i>
                    <span class="menu-title">Result Templates</span>
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
            @can('store-governance.manage')
            <li class="nav-item {{ request()->routeIs('inventory.config.store-governance.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('inventory.config.store-governance.*') ? 'active' : '' }}" href="{{ route('inventory.config.store-governance.index') }}" id="sidebar-admin-store-governance">
                    <i class="mdi mdi-store-cog menu-icon"></i>
                    <span class="menu-title">Store Governance</span>
                </a>
            </li>
            @endcan
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
            <li class="nav-item {{ request()->routeIs('backups.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('backups.*') ? 'active' : '' }}" href="{{ route('backups.index') }}" id="sidebar-admin-backups">
                    <i class="mdi mdi-database-lock menu-icon"></i>
                    <span class="menu-title">Database Backups</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('admin.slow_queries.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('admin.slow_queries.*') ? 'active' : '' }}" href="{{ route('admin.slow_queries.index') }}" id="sidebar-admin-slow-queries">
                    <i class="mdi mdi-database-search menu-icon"></i>
                    <span class="menu-title">Slow Query Monitor</span>
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
            <li class="nav-item {{ request()->routeIs('clinic-schedules.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('clinic-schedules.*') ? 'active' : '' }}" href="{{ route('clinic-schedules.index') }}" id="sidebar-admin-clinic-schedules">
                    <i class="mdi mdi-calendar-clock menu-icon"></i>
                    <span class="menu-title">Clinic Schedules</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('departments.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}" href="{{ route('departments.index') }}" id="sidebar-admin-departments">
                    <i class="mdi mdi-office-building-outline menu-icon"></i>
                    <span class="menu-title">Departments</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}" href="{{ route('allPrevEncounters') }}" id="sidebar-admin-encounters">
                    <i class="mdi mdi-stethoscope menu-icon"></i>
                    <span class="menu-title">All Encounters</span>
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
            <li class="nav-item {{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'active' : '' }}">
                <a class="nav-link {{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-store-services" data-bs-target="#sidebar-store-services" aria-expanded="{{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'true' : 'false' }}" aria-controls="sidebar-store-services" id="sidebar-store-services-toggle">
                    <i class="mdi mdi-cog-outline menu-icon"></i>
                    <span class="menu-title">Services Management</span>
                    <i class="mdi mdi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'show' : '' }}" id="sidebar-store-services">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('services.index') }}" id="sidebar-store-all-services">All Services</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('services-category.index') }}" id="sidebar-store-service-categories">Categories</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('services.index', ['category' => appsettings('investigation_category_id', 2)]) }}" id="sidebar-store-medlab-services">Med Lab Services</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('services.index', ['category' => appsettings('imaging_category_id', 6)]) }}" id="sidebar-radiology-imaging-services">Imaging Services</a>
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
