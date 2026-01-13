@extends('admin.layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">Hospital Configuration</h3>
                    <p class="text-muted mb-0">Manage your hospital's system settings and branding</p>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 8px; border-left: 4px solid #28a745;">
                    <i class="mdi mdi-check-circle mr-2"></i>{{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <form action="{{ route('hospital-config.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row">
                    <!-- General Settings Card -->
                    <div class="col-lg-8">
                        <div class="card-modern mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                                <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                                    <i class="mdi mdi-cog-outline mr-2" style="color: var(--primary-color);"></i>
                                    General Settings
                                </h5>
                            </div>
                            <div class="card-body" style="padding: 2rem;">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Site Name *</label>
                                        <input type="text" class="form-control @error('site_name') is-invalid @enderror"
                                               name="site_name" value="{{ old('site_name', $config->site_name) }}"
                                               required style="border-radius: 8px; padding: 0.75rem;">
                                        @error('site_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Version</label>
                                        <input type="text" class="form-control" name="version"
                                               value="{{ old('version', $config->version) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Notification Sound</label>
                                        <div class="custom-control custom-switch mt-2">
                                            <input type="checkbox" class="custom-control-input" id="notificationSoundSwitch" name="notification_sound" value="1" {{ $config->notification_sound ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="notificationSoundSwitch">Enable chat notification sounds</label>
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Description</label>
                                        <textarea class="form-control" name="description" rows="3"
                                                  style="border-radius: 8px; padding: 0.75rem;">{{ old('description', $config->description) }}</textarea>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Header Text</label>
                                        <input type="text" class="form-control" name="header_text"
                                               value="{{ old('header_text', $config->header_text) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Footer Text</label>
                                        <input type="text" class="form-control" name="footer_text"
                                               value="{{ old('footer_text', $config->footer_text) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Service Categories Card -->
                        <div class="card-modern mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                                <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                                    <i class="mdi mdi-shape-outline mr-2" style="color: var(--primary-color);"></i>
                                    Service Categories
                                </h5>
                            </div>
                            <div class="card-body" style="padding: 2rem;">
                                <p class="text-muted mb-3" style="font-size: 0.9rem;">
                                    <i class="mdi mdi-information-outline"></i> Configure the category IDs for different service types in your system.
                                </p>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Bed Service Category ID</label>
                                        <input type="number" class="form-control" name="bed_service_category_id"
                                               value="{{ old('bed_service_category_id', $config->bed_service_category_id ?? 3) }}"
                                               min="1" style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Investigation Category ID</label>
                                        <input type="number" class="form-control" name="investigation_category_id"
                                               value="{{ old('investigation_category_id', $config->investigation_category_id ?? 2) }}"
                                               min="1" style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Consultation Category ID</label>
                                        <input type="number" class="form-control" name="consultation_category_id"
                                               value="{{ old('consultation_category_id', $config->consultation_category_id ?? 1) }}"
                                               min="1" style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Nursing Service Category</label>
                                        <input type="number" class="form-control" name="nursing_service_category"
                                               value="{{ old('nursing_service_category', $config->nursing_service_category ?? 4) }}"
                                               min="1" style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Misc Service Category ID</label>
                                        <input type="number" class="form-control" name="misc_service_category_id"
                                               value="{{ old('misc_service_category_id', $config->misc_service_category_id ?? 5) }}"
                                               min="1" style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Imaging Category ID</label>
                                        <input type="number" class="form-control" name="imaging_category_id"
                                               value="{{ old('imaging_category_id', $config->imaging_category_id ?? 6) }}"
                                               min="1" style="border-radius: 8px; padding: 0.75rem;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Time Windows Card -->
                        <div class="card-modern mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                                <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                                    <i class="mdi mdi-clock-outline mr-2" style="color: var(--primary-color);"></i>
                                    Time Windows
                                </h5>
                            </div>
                            <div class="card-body" style="padding: 2rem;">
                                <p class="text-muted mb-3" style="font-size: 0.9rem;">
                                    <i class="mdi mdi-information-outline"></i> Configure time limits for various operations.
                                </p>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Consultation Cycle Duration</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="consultation_cycle_duration"
                                                   value="{{ old('consultation_cycle_duration', $config->consultation_cycle_duration ?? 24) }}"
                                                   min="1" style="border-radius: 8px 0 0 8px; padding: 0.75rem;">
                                            <div class="input-group-append">
                                                <span class="input-group-text" style="border-radius: 0 8px 8px 0;">hours</span>
                                            </div>
                                        </div>
                                        <small class="text-muted">Time before consultation expires</small>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Note Edit Window</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="note_edit_window"
                                                   value="{{ old('note_edit_window', $config->note_edit_window ?? 30) }}"
                                                   min="1" style="border-radius: 8px 0 0 8px; padding: 0.75rem;">
                                            <div class="input-group-append">
                                                <span class="input-group-text" style="border-radius: 0 8px 8px 0;">minutes</span>
                                            </div>
                                        </div>
                                        <small class="text-muted">Time to allow note editing</small>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Result Edit Duration</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="result_edit_duration"
                                                   value="{{ old('result_edit_duration', $config->result_edit_duration ?? 60) }}"
                                                   min="1" style="border-radius: 8px 0 0 8px; padding: 0.75rem;">
                                            <div class="input-group-append">
                                                <span class="input-group-text" style="border-radius: 0 8px 8px 0;">minutes</span>
                                            </div>
                                        </div>
                                        <small class="text-muted">Time to allow result editing</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Integration Settings Card -->
                        <div class="card-modern mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                                <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                                    <i class="mdi mdi-api mr-2" style="color: var(--primary-color);"></i>
                                    Integration Settings
                                </h5>
                            </div>
                            <div class="card-body" style="padding: 2rem;">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Client ID</label>
                                        <input type="text" class="form-control" name="client_id"
                                               value="{{ old('client_id', $config->client_id) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Client Secret</label>
                                        <input type="password" class="form-control" name="client_secret"
                                               value="{{ old('client_secret', $config->client_secret) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- DHIS2 Settings Card -->
                        <div class="card-modern mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                                <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                                    <i class="mdi mdi-server-network mr-2" style="color: var(--primary-color);"></i>
                                    DHIS2 Integration
                                </h5>
                            </div>
                            <div class="card-body" style="padding: 2rem;">
                                <p class="text-muted mb-3" style="font-size: 0.9rem;">
                                    <i class="mdi mdi-information-outline"></i> Configure DHIS2 health information system integration.
                                </p>
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">DHIS2 API URL</label>
                                        <input type="text" class="form-control" name="dhis_api_url"
                                               value="{{ old('dhis_api_url', $config->dhis_api_url) }}"
                                               placeholder="http://localhost:8080/api"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                        <small class="text-muted">No trailing slash</small>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Username</label>
                                        <input type="text" class="form-control" name="dhis_username"
                                               value="{{ old('dhis_username', $config->dhis_username) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Password</label>
                                        <input type="password" class="form-control" name="dhis_pass"
                                               value="{{ old('dhis_pass', $config->dhis_pass) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Organization Unit</label>
                                        <input type="text" class="form-control" name="dhis_org_unit"
                                               value="{{ old('dhis_org_unit', $config->dhis_org_unit) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Tracked Entity Type</label>
                                        <input type="text" class="form-control" name="dhis_tracked_entity_type"
                                               value="{{ old('dhis_tracked_entity_type', $config->dhis_tracked_entity_type) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Tracked Entity Program</label>
                                        <input type="text" class="form-control" name="dhis_tracked_entity_program"
                                               value="{{ old('dhis_tracked_entity_program', $config->dhis_tracked_entity_program) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Program Stage 1 (First Visit)</label>
                                        <input type="text" class="form-control" name="dhis_tracked_entity_program_stage1"
                                               value="{{ old('dhis_tracked_entity_program_stage1', $config->dhis_tracked_entity_program_stage1) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Program Stage 2 (Follow Up)</label>
                                        <input type="text" class="form-control" name="dhis_tracked_entity_program_stage2"
                                               value="{{ old('dhis_tracked_entity_program_stage2', $config->dhis_tracked_entity_program_stage2) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Event Data Element</label>
                                        <input type="text" class="form-control" name="dhis_tracked_entity_program_event_dataelement"
                                               value="{{ old('dhis_tracked_entity_program_event_dataelement', $config->dhis_tracked_entity_program_event_dataelement) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                        <small class="text-muted">Data element for event (reason for encounter)</small>
                                    </div>

                                    <!-- Tracked Entity Attributes -->
                                    <div class="col-md-12 mt-2 mb-2">
                                        <h6 style="font-weight: 600; color: #495057;">Tracked Entity Attributes</h6>
                                        <hr>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">First Name Attribute</label>
                                        <input type="text" class="form-control" name="dhis_tracked_entity_attr_fname"
                                               value="{{ old('dhis_tracked_entity_attr_fname', $config->dhis_tracked_entity_attr_fname) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Last Name Attribute</label>
                                        <input type="text" class="form-control" name="dhis_tracked_entity_attr_lname"
                                               value="{{ old('dhis_tracked_entity_attr_lname', $config->dhis_tracked_entity_attr_lname) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Gender Attribute</label>
                                        <input type="text" class="form-control" name="dhis_tracked_entity_attr_gender"
                                               value="{{ old('dhis_tracked_entity_attr_gender', $config->dhis_tracked_entity_attr_gender) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Date of Birth Attribute</label>
                                        <input type="text" class="form-control" name="dhis_tracked_entity_attr_dob"
                                               value="{{ old('dhis_tracked_entity_attr_dob', $config->dhis_tracked_entity_attr_dob) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">City Attribute</label>
                                        <input type="text" class="form-control" name="dhis_tracked_entity_attr_city"
                                               value="{{ old('dhis_tracked_entity_attr_city', $config->dhis_tracked_entity_attr_city) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CoreHMS SuperAdmin Card -->
                        <div class="card-modern mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                                <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                                    <i class="mdi mdi-account-supervisor mr-2" style="color: var(--primary-color);"></i>
                                    CoreHMS SuperAdmin Integration
                                </h5>
                            </div>
                            <div class="card-body" style="padding: 2rem;">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">SuperAdmin URL</label>
                                        <input type="text" class="form-control" name="corehms_superadmin_url"
                                               value="{{ old('corehms_superadmin_url', $config->corehms_superadmin_url) }}"
                                               placeholder="https://corehms.com/api"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">SuperAdmin Username</label>
                                        <input type="text" class="form-control" name="corehms_superadmin_username"
                                               value="{{ old('corehms_superadmin_username', $config->corehms_superadmin_username) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">SuperAdmin Password</label>
                                        <input type="password" class="form-control" name="corehms_superadmin_pass"
                                               value="{{ old('corehms_superadmin_pass', $config->corehms_superadmin_pass) }}"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Branding & System Settings -->
                    <div class="col-lg-4">
                        <!-- Branding Card -->
                        <div class="card-modern mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                                <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                                    <i class="mdi mdi-palette-outline mr-2" style="color: var(--primary-color);"></i>
                                    Branding
                                </h5>
                            </div>
                            <div class="card-body" style="padding: 2rem;">
                                <div class="mb-4">
                                    <label class="form-label" style="font-weight: 600; color: #495057;">Logo</label>
                                    @if($config->logo)
                                        <div class="text-center mb-3 p-3" style="background: #f8f9fa; border-radius: 8px;">
                                            <img src="data:image/jpeg;base64,{{ $config->logo }}" alt="Logo"
                                                 style="max-width: 150px; max-height: 100px; border-radius: 8px;">
                                        </div>
                                    @endif
                                    <input type="file" class="form-control" name="logo" accept="image/*"
                                           style="border-radius: 8px; padding: 0.75rem;">
                                    <small class="text-muted">Recommended: PNG or JPG, max 2MB</small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label" style="font-weight: 600; color: #495057;">Favicon</label>
                                    @if($config->favicon)
                                        <div class="text-center mb-3 p-3" style="background: #f8f9fa; border-radius: 8px;">
                                            <img src="data:image/png;base64,{{ $config->favicon }}" alt="Favicon"
                                                 style="max-width: 32px; max-height: 32px;">
                                        </div>
                                    @endif
                                    <input type="file" class="form-control" name="favicon" accept="image/*"
                                           style="border-radius: 8px; padding: 0.75rem;">
                                    <small class="text-muted">Recommended: 32x32px PNG</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" style="font-weight: 600; color: #495057;">Primary Color</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" name="hos_color"
                                               value="{{ old('hos_color', $config->hos_color ?? '#011b33') }}"
                                               style="border-radius: 8px 0 0 8px; height: 48px; width: 60px;">
                                        <input type="text" class="form-control"
                                               value="{{ old('hos_color', $config->hos_color ?? '#011b33') }}"
                                               readonly style="border-radius: 0 8px 8px 0; padding: 0.75rem;">
                                    </div>
                                    <small class="text-muted">Used for sidebar, buttons, and accents</small>
                                </div>
                            </div>
                        </div>

                        <!-- System Settings Card -->
                        <div class="card-modern mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                                <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                                    <i class="mdi mdi-cogs mr-2" style="color: var(--primary-color);"></i>
                                    System Settings
                                </h5>
                            </div>
                            <div class="card-body" style="padding: 2rem;">
                                <div class="mb-3">
                                    <div class="custom-control custom-switch">
                                        <input type="hidden" name="active" value="0">
                                        <input type="checkbox" class="custom-control-input" id="active"
                                               name="active" value="1" {{ $config->active ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="active" style="font-weight: 600;">
                                            System Active
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Enable/disable the entire system</small>
                                </div>

                                <div class="mb-3">
                                    <div class="custom-control custom-switch">
                                        <input type="hidden" name="debug_mode" value="0">
                                        <input type="checkbox" class="custom-control-input" id="debug_mode"
                                               name="debug_mode" value="1" {{ $config->debug_mode ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="debug_mode" style="font-weight: 600;">
                                            Debug Mode
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Show detailed error messages</small>
                                </div>
                            </div>
                        </div>

                        <!-- Feature Flags Card -->
                        <div class="card-modern mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                                <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                                    <i class="mdi mdi-flag-variant mr-2" style="color: var(--primary-color);"></i>
                                    Feature Flags
                                </h5>
                            </div>
                            <div class="card-body" style="padding: 2rem;">
                                <div class="mb-3">
                                    <div class="feature-toggle-row d-flex align-items-center justify-content-between">
                                        <div>
                                            <label for="goonline" class="mb-0" style="font-weight: 600; cursor: pointer;">
                                                Go Online
                                            </label>
                                            <small class="text-muted d-block">Enable DHIS2 & SuperAdmin sync</small>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="goonline" value="1" {{ $config->goonline ? 'checked' : '' }}>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="feature-toggle-row d-flex align-items-center justify-content-between">
                                        <div>
                                            <label for="requirediagnosis" class="mb-0" style="font-weight: 600; cursor: pointer;">
                                                Require Diagnosis
                                            </label>
                                            <small class="text-muted d-block">Require diagnosis during consultation</small>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="requirediagnosis" value="1" {{ $config->requirediagnosis ? 'checked' : '' }}>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="feature-toggle-row d-flex align-items-center justify-content-between">
                                        <div>
                                            <label for="enable_twakto" class="mb-0" style="font-weight: 600; cursor: pointer;">
                                                Enable Tawk.to
                                            </label>
                                            <small class="text-muted d-block">Enable Tawk.to chat support widget</small>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="enable_twakto" value="1" {{ $config->enable_twakto ? 'checked' : '' }}>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <button type="submit" class="btn btn-block"
                                style="background: var(--primary-color); color: white; border: none; border-radius: 8px; padding: 0.875rem; font-weight: 600; transition: all 0.2s;">
                            <i class="mdi mdi-content-save mr-2"></i>Save Configuration
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<style>
    /* Custom Toggle Switch Styles */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 52px;
        height: 28px;
        margin: 0;
        cursor: pointer;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: 0.3s;
        border-radius: 28px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 22px;
        width: 22px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .toggle-switch input:checked + .toggle-slider {
        background-color: var(--primary-color);
    }

    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(24px);
    }

    .toggle-switch input:focus + .toggle-slider {
        box-shadow: 0 0 0 3px rgba(var(--primary-color), 0.2);
    }

    .feature-toggle-row {
        padding: 0.75rem 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .feature-toggle-row:last-child {
        border-bottom: none;
    }

    .custom-control-input:checked ~ .custom-control-label::before {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
</style>
@endsection
