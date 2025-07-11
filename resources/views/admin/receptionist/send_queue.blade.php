@extends('admin.layouts.app')
@section('title', 'Reception')
@section('page_name', 'Reception')
@section('subpage_name', 'Configure Services')
@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Configure services</h4>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="biodata_tab" data-bs-toggle="tab" data-bs-target="#consultation"
                        type="button" role="tab" aria-controls="consultation"
                        aria-selected="true">Consultation</button>
                </li>
                {{-- <li class="nav-item" role="presentation">
                    <button class="nav-link" id="private_data_tab" data-bs-toggle="tab" data-bs-target="#other"
                        type="button" role="tab" aria-controls="other" aria-selected="false">Other
                        Services</button>
                </li> --}}

            </ul>
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="consultation" role="tabpanel" aria-labelledby="consultation_tab">
                    <div class="container-fluid mt-4">
                        <small class="text-danger">*Required fields</small>
                        <form action="{{ route('product-or-service-request.store') }}" method="post">
                            @csrf
                            <input type="hidden" name="is_consultation" value="1">
                            <div class="row">
                                @foreach ($family as $f)
                                    <div class="col-md-4">
                                        <h5>{{ userfullname($f->user_id) }}</h5>
                                        <div class="form-group">
                                            <label for="service_id{{ $f->user_id }}">
                                                Service <i class="text-danger">*</i>
                                            </label>

                                            <input type="hidden" name="user_id[]" value="{{ $f->user_id }}">
                                            <select name="service_id[]" id="service_id{{ $f->user_id }}"
                                                class="form-control">
                                                <option value="">--select service--</option>
                                                @foreach ($services as $service)
                                                    <option value="{{ $service->id }}">
                                                        [{{(($service->price) ? $service->price->sale_price : 'N/A')}}]
                                                        {{ $service->service_name }}-{{ $service->service_code }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="clinic_id{{ $f->user_id }}">
                                                Clinic <i class="text-danger">*</i>
                                            </label>
                                            <select name="clinic_id[]" id="clinic_id{{ $f->user_id }}"
                                                class="form-control"
                                                onchange="fetch_doctors(this.value,'doctor_id{{ $f->user_id }}')">
                                                <option value="">--select clinic--</option>
                                                @foreach ($clinics as $clinic)
                                                    <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="doctor_id{{ $f->user_id }}">
                                                Doctor
                                            </label>
                                            <select name="doctor_id[]" id="doctor_id{{ $f->user_id }}"
                                                class="form-control">
                                                <option value="">--select doctor--</option>
                                            </select>
                                        </div>
                                        {{-- <div class="form-group">
                                            <label for="request_vitals{{ $f->user_id }}">
                                                Request Vitals
                                            </label>
                                            <input type="checkbox" value="1" name="request_vitals[]" id="request_vitals{{ $f->user_id }}"
                                                >
                                        </div> --}}
                                    </div>
                                @endforeach
                            </div>
                            <button type="subimt" class="btn btn-primary">Submit</button>
                            <a href="{{route('add-to-queue')}}" class="btn btn-warning">Back</a>
                        </form>
                    </div>
                </div>
                {{-- <div class="tab-pane fade" id="other" role="tabpanel" aria-labelledby="other_tab">
                    <div class="container-fluid mt-4">
                        <small class="text-danger">*Required fields</small>
                        <form action="{{ route('product-or-service-request.store') }}" method="post">
                            @csrf
                            <input type="hidden" name="is_consultation" value="0">
                            <div class="row">
                                @foreach ($family as $f)
                                    <div class="col-md-4">
                                        <h5>{{ userfullname($f->user_id) }}</h5>
                                        <div class="form-group">
                                            <label for="service_id{{ $f->user_id }}">
                                                Service <i class="text-danger">*</i>
                                            </label>

                                            <input type="hidden" name="user_id[]" value="{{ $f->user_id }}">
                                            <select name="service_id[]" id="service_id{{ $f->user_id }}"
                                                class="form-control">
                                                <option value="">--select service--</option>
                                                @foreach ($services as $service)
                                                    <option value="{{ $service->id }}">
                                                        [{{ $service->category->category_name }}]
                                                        {{ $service->service_name }}-{{ $service->service_code }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <button type="subimt" class="btn btn-primary">Submit</button>
                            <a href="{{route('add-to-queue')}}" class="btn btn-warning">Back</a>
                        </form>
                    </div>
                </div> --}}
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        function fetch_doctors(clinic_id, doctor_id_field) {
            $.ajax({
                type: 'GET',
                url: "{{ url('get-doctors') }}/" + clinic_id,
                success: function(data) {
                    console.log(data);
                    // data = JSON.parse(data);
                    $('#' + doctor_id_field).html('');
                    $('#' + doctor_id_field).append(`<option value="">--select doctor--</option>`)
                    for (var i = 0; i < data.length; i++) {
                        $('#' + doctor_id_field).append(`
                            <option value='${data[i].id}'>${data[i].user.surname}, ${data[i].user.firstname} ${data[i].user.othername} - ${data[i].specialization.name}</option>
                        `);
                    }

                },
            });
        }
    </script>
@endsection
