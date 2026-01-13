@extends('admin.layouts.app')
@section('title', 'New Ward')
@section('page_name', 'Hospital Setup')
@section('subpage_name', 'New Ward')

@section('content')
<section class="content">
    <div class="col-12">
        <div modern">
            <div class="card-header">
                <h3 class="card-title">Create New Ward</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('wards.store') }}" class="form-horizontal">
                    @csrf

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name" class="control-label">Ward Name <i class="text-danger">*</i></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    id="name" name="name" value="{{ old('name') }}"
                                    required autofocus placeholder="e.g., General Ward A">
                                @error('name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="code" class="control-label">Ward Code</label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror"
                                    id="code" name="code" value="{{ old('code') }}"
                                    maxlength="20" placeholder="e.g., GW-A">
                                @error('code')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="form-text text-muted">Short code for quick reference</small>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="type" class="control-label">Ward Type <i class="text-danger">*</i></label>
                                <select class="form-control select2 @error('type') is-invalid @enderror"
                                    id="type" name="type" required>
                                    <option value="">-- Select Type --</option>
                                    @foreach($wardTypes as $value => $label)
                                        <option value="{{ $value }}" {{ old('type') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('type')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="capacity" class="control-label">Capacity</label>
                                <input type="number" class="form-control @error('capacity') is-invalid @enderror"
                                    id="capacity" name="capacity" value="{{ old('capacity') }}"
                                    min="1" placeholder="Max beds">
                                @error('capacity')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="building" class="control-label">Building</label>
                                <input type="text" class="form-control @error('building') is-invalid @enderror"
                                    id="building" name="building" value="{{ old('building') }}"
                                    placeholder="e.g., Block A">
                                @error('building')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="floor" class="control-label">Floor</label>
                                <input type="text" class="form-control @error('floor') is-invalid @enderror"
                                    id="floor" name="floor" value="{{ old('floor') }}"
                                    placeholder="e.g., Ground Floor">
                                @error('floor')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="nurse_station" class="control-label">Nurse Station</label>
                                <input type="text" class="form-control @error('nurse_station') is-invalid @enderror"
                                    id="nurse_station" name="nurse_station" value="{{ old('nurse_station') }}"
                                    placeholder="e.g., NS-A1">
                                @error('nurse_station')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="contact_extension" class="control-label">Contact Extension</label>
                                <input type="text" class="form-control @error('contact_extension') is-invalid @enderror"
                                    id="contact_extension" name="contact_extension" value="{{ old('contact_extension') }}"
                                    maxlength="20" placeholder="e.g., 101">
                                @error('contact_extension')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="nurse_patient_ratio" class="control-label">Nurse:Patient Ratio</label>
                                <input type="number" step="0.1" class="form-control @error('nurse_patient_ratio') is-invalid @enderror"
                                    id="nurse_patient_ratio" name="nurse_patient_ratio" value="{{ old('nurse_patient_ratio') }}"
                                    min="0" max="1" placeholder="e.g., 0.25 for 1:4">
                                @error('nurse_patient_ratio')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="form-text text-muted">0.5 = 1:2, 0.25 = 1:4</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">Status</label>
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="is_active"
                                        name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="is_active">Active</label>
                                </div>
                                <small class="form-text text-muted">Inactive wards won't appear in bed assignment options</small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-save"></i> Save Ward
                            </button>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="{{ route('wards.index') }}" class="btn btn-secondary">
                                <i class="fa fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
<script>
$(document).ready(function() {
    $('.select2').select2({
        width: '100%'
    });
});
</script>
@endsection
