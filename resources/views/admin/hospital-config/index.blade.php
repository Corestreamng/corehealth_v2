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
                        <div class="card mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
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

                        <!-- Contact Information Card -->
                        <div class="card mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                                <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                                    <i class="mdi mdi-phone-outline mr-2" style="color: var(--primary-color);"></i>
                                    Contact Information
                                </h5>
                            </div>
                            <div class="card-body" style="padding: 2rem;">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Address</label>
                                        <textarea class="form-control" name="contact_address" rows="2"
                                                  style="border-radius: 8px; padding: 0.75rem;">{{ old('contact_address', $config->contact_address) }}</textarea>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Phone Numbers</label>
                                        <input type="text" class="form-control" name="contact_phones"
                                               value="{{ old('contact_phones', $config->contact_phones) }}"
                                               placeholder="+234 123 456 7890, +234 987 654 3210"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                        <small class="text-muted">Separate multiple phones with commas</small>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Email Addresses</label>
                                        <input type="text" class="form-control" name="contact_emails"
                                               value="{{ old('contact_emails', $config->contact_emails) }}"
                                               placeholder="info@hospital.com, support@hospital.com"
                                               style="border-radius: 8px; padding: 0.75rem;">
                                        <small class="text-muted">Separate multiple emails with commas</small>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label" style="font-weight: 600; color: #495057;">Social Media Links (JSON)</label>
                                        <textarea class="form-control" name="social_links" rows="3"
                                                  style="border-radius: 8px; padding: 0.75rem; font-family: monospace;">{{ old('social_links', $config->social_links) }}</textarea>
                                        <small class="text-muted">Example: {"facebook": "url", "twitter": "url", "instagram": "url"}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Branding & System Settings -->
                    <div class="col-lg-4">
                        <!-- Branding Card -->
                        <div class="card mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
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
                        <div class="card mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
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
