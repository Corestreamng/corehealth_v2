@extends('admin.layouts.admin')

@section('style')
<link href="{{ asset('plugins/daterangepicker/daterangepicker.css') }}" rel="stylesheet">
<style>
    /* Paystack-inspired Profile Page Styles */
    .profile-header {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 2rem;
        color: white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid rgba(255, 255, 255, 0.3);
        object-fit: cover;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .profile-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border: 1px solid #e9ecef;
    }

    .profile-card-header {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1a1a1a;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #f8f9fa;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    .info-item {
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 3px solid var(--primary-color);
    }

    .info-label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #6c757d;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    .info-value {
        font-size: 1rem;
        font-weight: 500;
        color: #1a1a1a;
    }

    .badge-role {
        display: inline-block;
        padding: 0.375rem 0.75rem;
        margin: 0.25rem;
        background: var(--primary-light);
        color: var(--primary-color);
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .nav-tabs-modern {
        border-bottom: 2px solid #e9ecef;
        margin-bottom: 2rem;
    }

    .nav-tabs-modern .nav-link {
        border: none;
        color: #6c757d;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s ease;
    }

    .nav-tabs-modern .nav-link:hover {
        color: var(--primary-color);
        border-bottom-color: var(--primary-light);
    }

    .nav-tabs-modern .nav-link.active {
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
        background: transparent;
    }

    .form-group-modern {
        margin-bottom: 1.5rem;
    }

    .form-group-modern label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }

    .form-control-modern {
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        font-size: 0.9375rem;
        transition: all 0.2s ease;
    }

    .form-control-modern:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px var(--primary-light);
    }

    .btn-primary-modern {
        background: var(--primary-color);
        border: none;
        border-radius: 8px;
        padding: 0.75rem 2rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .btn-primary-modern:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .upload-area {
        border: 2px dashed #e9ecef;
        border-radius: 12px;
        padding: 2rem;
        text-align: center;
        background: #f8f9fa;
        transition: all 0.2s ease;
    }

    .upload-area:hover {
        border-color: var(--primary-color);
        background: var(--primary-light);
    }

    .current-image-preview {
        max-width: 200px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .breadcrumb-modern {
        background: transparent;
        padding: 0;
        margin: 0;
    }

    .breadcrumb-modern .breadcrumb-item a {
        color: #6c757d;
        text-decoration: none;
    }

    .breadcrumb-modern .breadcrumb-item.active {
        color: var(--primary-color);
    }
</style>
@endsection

@section('main-content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1" style="font-weight: 700; color: #1a1a1a;">My Profile</h2>
            <ol class="breadcrumb-modern breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item active">Profile</li>
            </ol>
        </div>
    </div>

    <!-- Profile Header Card -->
    <div class="profile-header">
        <div class="row align-items-center">
            <div class="col-auto">
                <img class="profile-avatar" src="{!! url('storage/image/user/'.$user->filename) !!}" alt="{{ $user->getNameAttribute() }}">
            </div>
            <div class="col">
                <h3 class="mb-2" style="font-weight: 700;">{{ $user->getNameAttribute() }}</h3>
                <p class="mb-1" style="font-size: 1rem; opacity: 0.9;">{{ $user->statuscategory->name }}</p>
                <div class="d-flex gap-3 flex-wrap" style="gap: 1rem;">
                    <span style="opacity: 0.9;"><i class="mdi mdi-email-outline mr-1"></i> {{ $user->email }}</span>
                    <span style="opacity: 0.9;"><i class="mdi mdi-phone mr-1"></i> {{ $user->phone_number }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column - Quick Info -->
        <div class="col-lg-4">
            <!-- About Card -->
            <div class="profile-card">
                <div class="profile-card-header">
                    <i class="mdi mdi-information-outline mr-2"></i>About Me
                </div>
                <div class="info-item" style="border-left: none; background: white; padding: 0;">
                    <div style="color: #495057; line-height: 1.6;">
                        {!! $user->content !!}
                    </div>
                </div>
            </div>

            <!-- Roles & Permissions Card -->
            <div class="profile-card">
                <div class="profile-card-header">
                    <i class="mdi mdi-shield-account mr-2"></i>Access Control
                </div>
                <div class="mb-3">
                    <div class="info-label">Roles Assigned</div>
                    <div>
                        @if(!empty($user->getRoleNames()))
                            @foreach($user->getRoleNames() as $role)
                                <span class="badge-role">{{ $role }}</span>
                            @endforeach
                        @else
                            <span class="text-muted">No roles assigned</span>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="info-label">Permissions</div>
                    <div>
                        @if(!empty($user->getPermissionNames()))
                            @foreach($user->getPermissionNames() as $permission)
                                <span class="badge-role">{{ $permission }}</span>
                            @endforeach
                        @else
                            <span class="text-muted">No permissions assigned</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Tabs -->
        <div class="col-lg-8">
            <div class="profile-card">
                <!-- Tabs -->
                <ul class="nav nav-tabs-modern" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#information">
                            <i class="mdi mdi-information mr-1"></i> Information
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#avatar">
                            <i class="mdi mdi-image mr-1"></i> Avatar Upload
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#settings">
                            <i class="mdi mdi-cog mr-1"></i> Settings
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Information Tab -->
                    <div class="tab-pane fade show active" id="information">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Full Name</div>
                                <div class="info-value">{{ $user->getNameAttribute() }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Status Category</div>
                                <div class="info-value">{{ $user->statuscategory->name }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email Address</div>
                                <div class="info-value">{{ $user->email }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value">{{ $user->phone_number }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Designation</div>
                                <div class="info-value">{{ $user->designation }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Account Status</div>
                                <div class="info-value">
                                    <span class="badge" style="background: {{ $user->status->name == 'Active' ? '#28a745' : '#dc3545' }}; color: white; padding: 0.375rem 0.75rem; border-radius: 6px;">
                                        {{ $user->status->name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Avatar Upload Tab -->
                    <div class="tab-pane fade" id="avatar">
                        {!! Form::model($user, ['method' => 'PATCH', 'route'=> ['users.update', $user->id], 'enctype' => 'multipart/form-data']) !!}
                        @csrf
                        <input type="hidden" name="_method" value="PUT">

                        <div class="mb-4">
                            <div class="info-label mb-3">Current Profile Picture</div>
                            <img src="{!! url('storage/image/user/'.$user->filename) !!}" class="current-image-preview" alt="Current avatar" />
                        </div>

                        <div class="upload-area">
                            <i class="mdi mdi-cloud-upload" style="font-size: 3rem; color: var(--primary-color); opacity: 0.5;"></i>
                            <h5 class="mt-3 mb-2">Upload New Avatar</h5>
                            <p class="text-muted mb-3">Click below to select a new profile picture</p>

                            <div class="form-group-modern">
                                {{ Form::file('filename', ['class' => 'form-control-file', 'id' => 'filename', 'accept' => 'image/*']) }}
                            </div>

                            <div class="mt-3" id="preview-container" style="display: none;">
                                <p class="text-muted mb-2">Preview:</p>
                                <img src="" id="myimg" style="max-width: 200px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);" />
                            </div>
                        </div>

                        <div class="text-right mt-4">
                            <button type="submit" class="btn btn-primary-modern">
                                <i class="mdi mdi-upload mr-2"></i> Upload Avatar
                            </button>
                        </div>
                        {!! Form::close() !!}
                    </div>

                    <!-- Settings Tab -->
                    <div class="tab-pane fade" id="settings">
                        <form class="form-horizontal" method="POST" action="{{ route('update-my-profile') }}">
                            @csrf

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-modern">
                                        <label for="surname">Surname</label>
                                        <input type="text" class="form-control form-control-modern" id="surname" name="surname" value="{{ $user->surname ?? old('surname') }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-modern">
                                        <label for="firstname">Firstname</label>
                                        <input type="text" class="form-control form-control-modern" id="firstname" name="firstname" value="{{ $user->firstname ?? old('firstname') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group-modern">
                                <label for="othername">Othername</label>
                                <input type="text" class="form-control form-control-modern" id="othername" name="othername" value="{{ $user->othername ?? old('othername') }}">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-modern">
                                        <label for="email">Email Address</label>
                                        <input type="email" class="form-control form-control-modern" id="email" name="email" value="{{ $user->email ?? old('email') }}" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-modern">
                                        <label for="phone_number">Phone Number</label>
                                        <input type="text" class="form-control form-control-modern" id="phone_number" name="phone_number" value="{{ $user->phone_number ?? old('phone_number') }}" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group-modern">
                                <label for="content">About Me</label>
                                <textarea id="content" name="content" rows="5" class="form-control form-control-modern" placeholder="Tell us about yourself...">{{ $user->content ?? old('content') }}</textarea>
                            </div>

                            <div class="form-group-modern">
                                <label for="password">New Password</label>
                                <input type="password" class="form-control form-control-modern" id="password" name="password" placeholder="Leave blank to keep current password">
                                <small class="text-muted">Only fill this if you want to change your password</small>
                            </div>

                            <div class="form-group-modern">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="terms" required>
                                    <label class="custom-control-label" for="terms">
                                        I agree to the <a href="#" style="color: var(--primary-color);">terms and conditions</a>
                                    </label>
                                </div>
                            </div>

                            <div class="text-right">
                                <button type="submit" class="btn btn-primary-modern">
                                    <i class="mdi mdi-content-save mr-2"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>
<script src="{{ asset('vendor/unisharp/laravel-ckeditor/ckeditor.js') }}"></script>

<script>
    // Initialize CKEditor
    CKEDITOR.replace('content', {
        height: 200,
        toolbar: [
            { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline'] },
            { name: 'paragraph', items: ['NumberedList', 'BulletedList'] },
            { name: 'links', items: ['Link'] }
        ]
    });

    // Image preview for avatar upload
    function readURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#myimg').attr('src', e.target.result);
                $('#preview-container').show();
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    $("#filename").change(function() {
        readURL(this);
    });
</script>
@endsection

