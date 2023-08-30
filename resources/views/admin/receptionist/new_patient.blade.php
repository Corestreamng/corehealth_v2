@extends('admin.layouts.app')
@section('title', 'Hompage')
@section('page_name', 'Home')
@section('subpage_name', 'New Patient')
@section('content')
    @if (null == Request::get('user_id'))
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="biodata_tab" data-bs-toggle="tab" data-bs-target="#biodata" type="button"
                    role="tab" aria-controls="biodata" aria-selected="true">Biodata</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="private_data_tab" data-bs-toggle="tab" data-bs-target="#private_data"
                    type="button" role="tab" aria-controls="private_data" aria-selected="false">Private data</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="family_tab" data-bs-toggle="tab" data-bs-target="#family" type="button"
                    role="tab" aria-controls="family" aria-selected="false">Family</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="insurance_tab" data-bs-toggle="tab" data-bs-target="#insurance" type="button"
                    role="tab" aria-controls="insurance" aria-selected="false">Insurance</button>
            </li>
            {{-- <li class="nav-item" role="presentation">
                <button class="nav-link" id="review-tab" data-bs-toggle="tab" data-bs-target="#review" type="button"
                    role="tab" aria-controls="review" aria-selected="false">Review</button>
            </li> --}}
        </ul>
        <form action="{{ route('patient.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="biodata" role="tabpanel" aria-labelledby="biodata_tab">
                    <div class="container-fluid mt-4">
                        <small class="text-danger">*Required fields</small>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="">Surname <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="surname"><i
                                                class="mdi mdi-account-multiple"></i></span>
                                        <input type="text" class="form-control" placeholder="Surname"
                                            aria-label="surname" aria-describedby="surname" name="surname"
                                            value="{{ old('surname') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="">Firstname <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="firstname"><i class="mdi mdi-account"></i></span>
                                        <input type="text" class="form-control" placeholder="firstname"
                                            aria-label="firstname" aria-describedby="firstname" name="firstname"
                                            value="{{ old('firstname') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="">Other names</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="surname"><i
                                                class="mdi mdi-account-check"></i></span>
                                        <input type="text" class="form-control" placeholder="Surname"
                                            aria-label="surname" aria-describedby="surname" name="othername"
                                            value="{{ old('othername') }}">
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="">Gender <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="gender"><i
                                                class="mdi mdi-gender-male-female"></i></span>
                                        <select class="form-control" placeholder="gender" aria-label="gender"
                                            aria-describedby="gender" name="gender">
                                            <option value="">Select gender</option>
                                            <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male
                                            </option>
                                            <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>
                                                Female</option>
                                            <option value="Others" {{ old('gender') == 'Others' ? 'selected' : '' }}>
                                                Others</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="">Date of Birth <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="dob"><i
                                                class="mdi mdi-calendar"></i></span>
                                        <input type="date" class="form-control" placeholder="dob" aria-label="dob"
                                            aria-describedby="dob" name="dob" value="{{ old('dob') }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="">Phone <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="gender"><i
                                                class="mdi mdi-phone"></i></span>
                                        <input type="text" name="phone_no" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="">Passport</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="filename"><i
                                                class="mdi mdi-file-image"></i></span>
                                        <input type="file" class="form-control" aria-label="filename"
                                            aria-describedby="filename" name="filename">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="">Old Records</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="old_records"><i
                                                class="mdi mdi-file-import"></i></span>
                                        <input type="file" class="form-control" aria-label="old_records"
                                            aria-describedby="old_records" name="old_records">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" onclick="switch_tab(event,'private_data_tab')"
                            class="btn btn-primary mr-2">
                            Next </button>
                        <a href="{{ route('home') }}" class="btn btn-light">Exit</a>


                    </div>
                </div>
                <div class="tab-pane fade" id="private_data" role="tabpanel" aria-labelledby="private_data_tab">
                    <div class="container-fluid mt-4">
                        <small class="text-danger">*Required fields</small>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="">Blood group </label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="blood_group"><i
                                                class="mdi mdi-water"></i></span>
                                        <select name="blood_group" id="" class="form-control">
                                            <option value="">Select blood group</option>
                                            <option value="A+" {{ old('blood_group') == 'A+' ? 'selected' : '' }}>
                                                A+</option>
                                            <option value="B+" {{ old('blood_group') == 'B+' ? 'selected' : '' }}>
                                                B+</option>
                                            <option value="AB+"
                                                {{ old('blood_group') == 'AB+' ? 'selected' : '' }}>AB+</option>
                                            <option value="A-" {{ old('blood_group') == 'A-' ? 'selected' : '' }}>
                                                A-</option>
                                            <option value="B-" {{ old('blood_group') == 'B-' ? 'selected' : '' }}>
                                                B-</option>
                                            <option value="AB-"
                                                {{ old('blood_group') == 'AB-' ? 'selected' : '' }}>AB-</option>
                                            <option value="O+" {{ old('blood_group') == 'O+' ? 'selected' : '' }}>
                                                O+</option>
                                            <option value="O-" {{ old('blood_group') == 'A-' ? 'selected' : '' }}>
                                                O-</option>
                                            <option value="Others"
                                                {{ old('blood_group') == 'Others+' ? 'selected' : '' }}>Others</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="">Genotype </label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="genotype"><i
                                                class="mdi mdi-flask"></i></span>
                                        <select name="genotype" id="genotype" class="form-control">
                                            <option value="">select genotype</option>
                                            <option value="AA" {{ old('genotype') == 'AA' ? 'selected' : '' }}>AA
                                            </option>
                                            <option value="AS" {{ old('genotype') == 'AS' ? 'selected' : '' }}>AS
                                            </option>
                                            <option value="AC" {{ old('genotype') == 'AC' ? 'selected' : '' }}>AC
                                            </option>
                                            <option value="SS" {{ old('genotype') == 'SS' ? 'selected' : '' }}>SS
                                            </option>
                                            <option value="SC" {{ old('genotype') == 'SC' ? 'selected' : '' }}>SC
                                            </option>
                                            <option value="Others"
                                                {{ old('genotype') == 'Others' ? 'selected' : '' }}>Others</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="">Disablity status</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="disability"><i
                                                class="mdi mdi-walk"></i></span>
                                        <select name="disability" id="disability" class="form-control">
                                            <option value="0" {{ old('disability') == '0' ? 'selected' : '' }}>No
                                                Disability</option>
                                            <option value="1" {{ old('disability') == '1' ? 'selected' : '' }}>
                                                Disabled</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="">Nationality </label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="nationality"><i
                                                class="mdi mdi-map"></i></span>
                                        <input type="text" class="form-control" placeholder="nationality"
                                            aria-label="nationality" aria-describedby="nationality" name="nationality"
                                            value="{{ old('nationality') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="">Ethnicity </label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="ethnicity"><i
                                                class="mdi mdi-crown"></i></span>
                                        <input type="text" class="form-control" placeholder="ethnicity"
                                            aria-label="ethnicity" aria-describedby="ethnicity" name="ethnicity"
                                            {{ old('ethnicity') }}>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="">Residential Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="address"><i
                                                class="mdi mdi-map-marker-radius"></i></span>
                                        <textarea name="address" id="address" class="form-control">{{ old('address') }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="">Mics. data</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="misc"><i
                                                class="mdi mdi-library-books"></i></span>
                                        <textarea name="misc" id="misc" class="form-control">{{ old('misc') }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" onclick="switch_tab(event,'biodata_tab')" class="btn btn-secondary mr-2">
                            Prev </button>
                        <button type="button" onclick="switch_tab(event,'family_tab')" class="btn btn-primary mr-2">
                            Next </button>
                        <a href="{{ route('home') }}" class="btn btn-light">Exit</a>
        </form>

        </div>
        </div>
        <div class="tab-pane fade" id="family" role="tabpanel" aria-labelledby="family-tab">
            <div class="container-fluid mt-4">
                {{-- <small class="text-danger">*Required fields</small> --}}
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="">File number </label>
                            <div class="input-group">
                                <span class="input-group-text" id="file_no"><i class="mdi mdi-file-find"></i></span>
                                <input type="text" class="form-control" placeholder="file_no" aria-label="file_no"
                                    aria-describedby="file_no" name="file_no" value="{{ old('file_no') }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label for="">Next of kin name </label>
                            <div class="input-group">
                                <span class="input-group-text" id="next_of_kin_name"><i class="mdi mdi-account"></i></span>
                                <input type="text" class="form-control" placeholder="Next of kin name" aria-label="next_of_kin_name"
                                    aria-describedby="next_of_kin_name" name="next_of_kin_name" value="{{ old('next_of_kin_name') }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label for="">Next of kin phone </label>
                            <div class="input-group">
                                <span class="input-group-text" id="next_of_kin_phone"><i class="mdi mdi-phone"></i></span>
                                <input type="text" class="form-control" placeholder="next_of_kin_phone" aria-label="next_of_kin_phone"
                                    aria-describedby="next_of_kin_phone" name="next_of_kin_phone" value="{{ old('next_of_kin_phone') }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label for="">Next of kin address </label>
                            <div class="input-group">
                                <span class="input-group-text" id="next_of_kin_address"><i
                                        class="mdi mdi-library-books"></i></span>
                                <textarea name="next_of_kin_address" id="next_of_kin_address" class="form-control">{{ old('next_of_kin_address') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="switch_tab(event,'private_data_tab')" class="btn btn-secondary mr-2">
                    Prev
                </button>
                <button type="button" onclick="switch_tab(event,'insurance_tab')"
                    class="btn btn-primary mr-2">Next</button>
                <a href="{{ route('home') }}" class="btn btn-light">Exit</a>
                </form>

            </div>
        </div>
        <div class="tab-pane fade" id="insurance" role="tabpanel" aria-labelledby="insurance-tab">
            <div class="container-fluid mt-4">
                {{-- <small class="text-danger">*Required fields</small> --}}
                <div class="row">
                    {{-- <div class="col-12">
                        <div class="form-group">
                            <label for="">Select Scheme </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="mdi mdi-file-find"></i></span>
                                <select name="insurance_scheme" id="insurance_scheme" class="form-control">
                                    <option value="">Select scheme</option>
                                    <option value="1" {{((old('insurance_scheme') == '1') ? 'selected' : '')}}>NHIS</option>
                                    <option value="2" {{((old('insurance_scheme') == '2') ? 'selected' : '')}}>PLASCHEMA</option>
                                    <option value="3" {{((old('insurance_scheme') == '1') ? 'selected' : '')}}>NNPC</option>
                                </select>
                            </div>
                        </div>
                    </div> --}}
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="">Select HMO </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="mdi mdi-account"></i></span>
                                <select name="hmo_id" id="hmo_id" class="form-control">
                                    <option value="">Select HMO</option>
                                    @foreach ($hmos as $hmo)
                                        <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="">HMO ID/No</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="mdi mdi-link-variant"></i></span>
                                <input type="text" name="hmo_no" class="form-control" placeholder="HMO ID/No"
                                    value="{{ old('hmo_no') }}">
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="switch_tab(event,'family_tab')" class="btn btn-secondary mr-2"> Prev
                </button>
                <button type="submit" class="btn btn-primary mr-2"> Save </button>
                <a href="{{ route('home') }}" class="btn btn-light">Exit</a>
                </form>
            </div>
        </div>
        </div>
        </form>
    @else
    @endif
@endsection
@section('scripts')
    <script>
        function switch_tab(e, id_of_next_tab) {
            e.preventDefault();
            $('#' + id_of_next_tab).click();
        }
    </script>
@endsection
