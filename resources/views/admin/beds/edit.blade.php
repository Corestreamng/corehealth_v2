@extends('admin.layouts.app')
@section('title', 'Update Bed')
@section('page_name', 'Hospital Setup')
@section('subpage_name', 'Update Bed')

@section('content')

    <section class="content">

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Update Bed: {{ $bed->name }}</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    {!! Form::open(['method' => 'POST', 'route' => ['beds.update',$bed->id], 'class' => 'form-horizontal', 'role' => 'form']) !!}
                    {{ csrf_field() }}
                    @method('PUT')
                    <div class="form-group">
                        <label for="name" class=" control-label">Name/ Number <i class = "text-danger">*</i> </label>
                        <div class="">
                            <input type="text" class="form-control" id="name" name="name"
                                value="{{ $bed->name ?? old('name') }}" required autofocus placeholder="Example: Private bed 1">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="ward_id" class="control-label">Ward <i class="text-danger">*</i></label>
                        <div class="">
                            @if(isset($wards) && $wards->count() > 0)
                                <select class="form-control select2" id="ward_id" name="ward_id">
                                    <option value="">-- Select Ward --</option>
                                    @foreach($wards as $ward)
                                        <option value="{{ $ward->id }}" {{ old('ward_id', $bed->ward_id) == $ward->id ? 'selected' : '' }}>
                                            {{ $ward->name }} ({{ ucfirst($ward->type) }}) - {{ $ward->location }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Select a ward from the list, or type a custom ward name below</small>
                            @endif
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="ward" class="control-label">Ward Name (Custom) @if(!isset($wards) || $wards->count() == 0)<i class="text-danger">*</i>@endif</label>
                        <div class="">
                            <input type="text" class="form-control" id="ward" name="ward"
                                value="{{ $bed->ward ?? old('ward') }}" placeholder="Pediatrics (only if ward not selected above)">
                            <small class="form-text text-muted">Use this only if the ward is not in the list above</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="unit" class=" control-label">Unit(Optional)</label>
                        <div class="">
                            <input type="text" class="form-control" id="unit" name="unit"
                                value="{{ $bed->unit ?? old('unit') }}" placeholder="Block 2">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="bed_status" class="control-label">Bed Status</label>
                        <div class="">
                            <select class="form-control" id="bed_status" name="bed_status">
                                <option value="available" {{ old('bed_status', $bed->bed_status) == 'available' ? 'selected' : '' }}>Available</option>
                                <option value="occupied" {{ old('bed_status', $bed->bed_status) == 'occupied' ? 'selected' : '' }}>Occupied</option>
                                <option value="maintenance" {{ old('bed_status', $bed->bed_status) == 'maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                                <option value="reserved" {{ old('bed_status', $bed->bed_status) == 'reserved' ? 'selected' : '' }}>Reserved</option>
                            </select>
                            @if($bed->bed_status == 'occupied')
                                <small class="form-text text-warning">
                                    <i class="fa fa-warning"></i> This bed is currently occupied. Changing status may affect current patient assignment.
                                </small>
                            @endif
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="price" class="control-label">Price <i class = "text-danger">*</i></label>
                        <div>
                            <input type="number" class="form-control" id="price" name="price" min = '0' step='0.01'
                                value="{{ $bed->price ?? old('price') }}" required placeholder="Enter price">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="col-md-offset-1 col-md-6">
                                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i>
                                        Update</button>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="col-md-6">
                                    <a href="{{ route('beds.index') }}" class="pull-right btn btn-danger"><i
                                            class="fa fa-close"></i> Back </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    {!! Form::close() !!}
                </div>
            </div>

        </div>

    </section>

@endsection

@section('scripts')
<script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
<script>
    $(document).ready(function() {
        $(".select2").select2({
            width: '100%'
        });
        
        // When ward is selected from dropdown, update the ward text field
        $('#ward_id').on('change', function() {
            var selectedText = $(this).find('option:selected').text();
            if ($(this).val()) {
                // Extract just the ward name (before the parenthesis)
                var wardName = selectedText.split('(')[0].trim();
                $('#ward').val(wardName);
            }
        });
    });
</script>
@endsection
