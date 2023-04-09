@extends('admin.layouts.app')
@section('title', 'Hompage')
@section('page_name', 'Home')
@section('subpage_name', 'Edit Patient')
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
        <form action="{{ route('patient.update', $patient->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="biodata" role="tabpanel" aria-labelledby="biodata_tab">
                    <div class="container-fluid mt-4">
                        <small class="text-danger">*Required fields</small>
                        <div class="row">
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label for="">Surname <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="surname"><i
                                                class="mdi mdi-account-multiple"></i></span>
                                        <input type="text" class="form-control" placeholder="Surname"
                                            aria-label="surname" aria-describedby="surname" name="surname"
                                            value="{{ !empty($patient->user->surname) ? $patient->user->surname : old('surname') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label for="">Firstname <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="firstname"><i class="mdi mdi-account"></i></span>
                                        <input type="text" class="form-control" placeholder="firstname"
                                            aria-label="firstname" aria-describedby="firstname" name="firstname"
                                            value="{{ !empty($patient->user->firstname) ? $patient->user->firstname : old('firstname') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label for="">Other names</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="surname"><i
                                                class="mdi mdi-account-check"></i></span>
                                        <input type="text" class="form-control" placeholder="Surname"
                                            aria-label="othename" aria-describedby="othename" name="othername"
                                            value="{{ !empty($patient->user->othername) ? $patient->user->othername : old('othername') }}">
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="">Gender <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="gender"><i
                                                class="mdi mdi-gender-male-female"></i></span>
                                        <select class="form-control" placeholder="gender" aria-label="gender"
                                            aria-describedby="gender" name="gender">
                                            <option value="">Select gender</option>
                                            <option value="Male"
                                                {{ !empty($patient->gender && $patient->gender == 'Male') ? 'selected' : (old('gender') == 'Male' ? 'selected' : '') }}>
                                                Male
                                            </option>
                                            <option value="Female"
                                                {{ !empty($patient->gender && $patient->gender == 'Feale') ? 'selected' : (old('gender') == 'Feale' ? 'selected' : '') }}>
                                                Female</option>
                                            <option value="Others"
                                                {{ !empty($patient->gender && $patient->gender == 'Others') ? 'selected' : (old('gender') == 'Others' ? 'selected' : '') }}>
                                                Others</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="">Date of Birth <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="dob"><i
                                                class="mdi mdi-calendar"></i></span>
                                        <input type="datetime-local" class="form-control" placeholder="dob" aria-label="dob"
                                            aria-describedby="dob" name="dob" value="{{$patient->dob }}">
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="row">
                            <div class="col-sm-6">
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
                            <div class="col-sm-6">
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
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label for="">Blood group </label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="blood_group"><i
                                                class="mdi mdi-water"></i></span>
                                        <select name="blood_group" id="" class="form-control">
                                            <option value="">Select blood group</option>
                                            <option value="A+"
                                                {{ !empty($patient->blood_group && $patient->blood_group == 'A+') ? 'selected' : (old('blood_group') == 'A+' ? 'selected' : '') }}>
                                                A+</option>
                                            <option value="B+"
                                                {{ !empty($patient->blood_group && $patient->blood_group == 'B+') ? 'selected' : (old('blood_group') == 'B+' ? 'selected' : '') }}>
                                                B+</option>
                                            <option value="AB+"
                                                {{ !empty($patient->blood_group && $patient->blood_group == 'AB+') ? 'selected' : (old('blood_group') == 'AB+' ? 'selected' : '') }}>
                                                AB+</option>
                                            <option value="A-"
                                                {{ !empty($patient->blood_group && $patient->blood_group == 'A-') ? 'selected' : (old('blood_group') == 'A-' ? 'selected' : '') }}>
                                                A-</option>
                                            <option value="B-"
                                                {{ !empty($patient->blood_group && $patient->blood_group == 'B-') ? 'selected' : (old('blood_group') == 'B-' ? 'selected' : '') }}>
                                                B-</option>
                                            <option value="AB-"
                                                {{ !empty($patient->blood_group && $patient->blood_group == 'AB-') ? 'selected' : (old('blood_group') == 'AB-' ? 'selected' : '') }}>
                                                AB-</option>
                                            <option value="O+"
                                                {{ !empty($patient->blood_group && $patient->blood_group == 'O+') ? 'selected' : (old('blood_group') == 'O+' ? 'selected' : '') }}>
                                                O+</option>
                                            <option value="O-"
                                                {{ !empty($patient->blood_group && $patient->blood_group == 'O-') ? 'selected' : (old('blood_group') == 'O-' ? 'selected' : '') }}>
                                                O-</option>
                                            <option value="Others"
                                                {{ !empty($patient->blood_group && $patient->blood_group == 'Others') ? 'selected' : (old('blood_group') == 'Others' ? 'selected' : '') }}>
                                                Others</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label for="">Genotype </label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="genotype"><i
                                                class="mdi mdi-flask"></i></span>
                                        <select name="genotype" id="genotype" class="form-control">
                                            <option value="">select genotype</option>
                                            <option value="AA"
                                                {{ !empty($patient->genotype && $patient->genotype == 'AA') ? 'selected' : (old('genotype') == 'AA' ? 'selected' : '') }}>
                                                AA
                                            </option>
                                            <option value="AS"
                                                {{ !empty($patient->genotype && $patient->genotype == 'AS') ? 'selected' : (old('genotype') == 'AS' ? 'selected' : '') }}>
                                                AS
                                            </option>
                                            <option value="AC"
                                                {{ !empty($patient->genotype && $patient->genotype == 'AC') ? 'selected' : (old('genotype') == 'AC' ? 'selected' : '') }}>
                                                AC
                                            </option>
                                            <option value="SS"
                                                {{ !empty($patient->genotype && $patient->genotype == 'SS') ? 'selected' : (old('genotype') == 'SS' ? 'selected' : '') }}>
                                                SS
                                            </option>
                                            <option value="SC"
                                                {{ !empty($patient->genotype && $patient->genotype == 'SC') ? 'selected' : (old('genotype') == 'SC' ? 'selected' : '') }}>
                                                SC
                                            </option>
                                            <option value="Others"
                                                {{ !empty($patient->genotype && $patient->genotype == 'Others') ? 'selected' : (old('genotype') == 'Others' ? 'selected' : '') }}>
                                                Others</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label for="">Disablity status</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="disability"><i
                                                class="mdi mdi-walk"></i></span>
                                        <select name="disability" id="disability" class="form-control">
                                            <option value="0"
                                                {{ !empty($patient->disability && $patient->disability == '0') ? 'selected' : (old('disability') == '0' ? 'selected' : '') }}>
                                                No
                                                Disability</option>
                                            <option value="1"
                                                {{ !empty($patient->disability && $patient->disability == '1') ? 'selected' : (old('disability') == '1' ? 'selected' : '') }}>
                                                Disabled</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="">Nationality </label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="nationality"><i
                                                class="mdi mdi-map"></i></span>
                                        <input type="text" class="form-control" placeholder="nationality"
                                            aria-label="nationality" aria-describedby="nationality" name="nationality"
                                            value="{{ !empty($patient->nationality) ? $patient->nationality : old('nationality') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="">Ethnicity </label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="ethnicity"><i
                                                class="mdi mdi-crown"></i></span>
                                        <input type="text" class="form-control" placeholder="ethnicity"
                                            aria-label="ethnicity" aria-describedby="ethnicity" name="ethnicity"
                                            value="{{ !empty($patient->ethnicity) ? $patient->ethnicity : old('ethnicity') }}">
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="">Residential Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="address"><i
                                                class="mdi mdi-map-marker-radius"></i></span>
                                        <textarea name="address" id="address" class="form-control">{{ !empty($patient->address) ? $patient->address : old('address') }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="">Mics. data</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="misc"><i
                                                class="mdi mdi-library-books"></i></span>
                                        <textarea name="misc" id="misc" class="form-control">{{ !empty($patient->misc) ? $patient->misc : old('misc') }}</textarea>
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
                                    aria-describedby="file_no" name="file_no" value="{{ !empty($patient->file_no) ? $patient->file_no : old('file_no') }}">
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
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="">Select HMO </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="mdi mdi-account"></i></span>
                                <select name="hmo_id" id="hmo_id" class="form-control">
                                    <option value="">Select HMO</option>
                                    @foreach ($hmos as $hmo)
                                        <option value="{{ $hmo->id }}"
                                            {{ !empty($patient->hmo_id && $patient->hmo_id == $hmo->id) ? 'selected' : (old('hmo_id') == $hmo->id ? 'selected' : '') }}>
                                            {{ $hmo->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="">HMO ID/No</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="mdi mdi-link-variant"></i></span>
                                <input type="text" name="hmo_no" class="form-control" placeholder="HMO ID/No"
                                    value="{{ !empty($patient->hmo_no) ? $patient->hmo_no : old('hmo_no') }}">
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="switch_tab(event,'family_tab')" class="btn btn-secondary mr-2"> Prev
                </button>
                <button type="submit" class="btn btn-primary mr-2"> Update </button>
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