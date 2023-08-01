@extends('doctor.layout.app')
@section('title', 'Doctor Consultations')
@section('page_name', 'Consultations')

@section('content')

    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="biodata_tab" data-bs-toggle="tab" data-bs-target="#biodata" type="button"
                    role="tab" aria-controls="biodata" aria-selected="true">Scheduled</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="private_data_tab" data-bs-toggle="tab" data-bs-target="#private_data"
                    type="button" role="tab" aria-controls="private_data" aria-selected="false">New</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="family-tab" data-bs-toggle="tab" data-bs-target="#family" type="button"
                    role="tab" aria-controls="family" aria-selected="false">Continuing</button>
        </li>

    </ul>
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="biodata" role="tabpanel" aria-labelledby="biodata_tab">
            <div class="container-fluid mt-4">
                <small class="text-danger">*Required fields</small>
                <form action="" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="">Surname <span class="text-danger">*</span></label>
                                <div class="input-group">
                                        <span class="input-group-text" id="surname"><i
                                                class="mdi mdi-account-multiple"></i></span>
                                    <input type="text" class="form-control" placeholder="Surname"
                                           aria-label="surname" aria-describedby="surname" name="surname">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="">Firstname <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text" id="firstname"><i class="mdi mdi-account"></i></span>
                                    <input type="text" class="form-control" placeholder="firstname"
                                           aria-label="firstname" aria-describedby="firstname" name="firstname">
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
                                           aria-label="surname" aria-describedby="surname" name="othername">
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
                                    <input type="text" class="form-control" placeholder="gender"
                                           aria-label="gender" aria-describedby="gender" name="gender">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="">Date of Birth <span class="text-danger">*</span></label>
                                <div class="input-group">
                                        <span class="input-group-text" id="dob"><i
                                                class="mdi mdi-calendar"></i></span>
                                    <input type="text" class="form-control" placeholder="dob" aria-label="dob"
                                           aria-describedby="dob" name="dob">
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md-6">
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
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="">Old Records</label>
                                <div class="input-group">
                                        <span class="input-group-text" id="dob"><i
                                                class="mdi mdi-file-import"></i></span>
                                    <input type="file" class="form-control" aria-label="old_records"
                                           aria-describedby="old_records" name="old_records">
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mr-2"> Save </button>
                    <a href="{{ route('home') }}" class="btn btn-light">Exit</a>
                </form>

            </div>
        </div>
        <div class="tab-pane fade" id="private_data" role="tabpanel" aria-labelledby="private_data_tab">
            <div class="container-fluid mt-4">
                <small class="text-danger">*Required fields</small>
                <form action="" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="">Blood group </label>
                                <div class="input-group">
                                        <span class="input-group-text" id="blood_group"><i
                                                class="mdi mdi-water"></i></span>
                                    <select name="blood_group" id="" class="form-control">
                                        <option value="">Select blood group</option>
                                        <option value="A+">A+</option>
                                        <option value="B+">B+</option>
                                        <option value="AB+">AB+</option>
                                        <option value="A-">A-</option>
                                        <option value="B-">B-</option>
                                        <option value="AB-">AB-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                        <option value="Others">Others</option>
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
                                        <option value="AA">AA</option>
                                        <option value="AS">AS</option>
                                        <option value="AC">AC</option>
                                        <option value="SS">SS</option>
                                        <option value="SC">SC</option>
                                        <option value="Others">Others</option>
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
                                        <option value="0">No Disability</option>
                                        <option value="1">Disabled</option>
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
                                           aria-label="nationality" aria-describedby="nationality" name="nationality">
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
                                           aria-label="ethnicity" aria-describedby="ethnicity" name="ethnicity">
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
                                    <textarea name="address" id="address" class="form-control"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="">Mics. data</label>
                                <div class="input-group">
                                        <span class="input-group-text" id="misc"><i
                                                class="mdi mdi-library-books"></i></span>
                                    <textarea name="misc" id="misc" class="form-control"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mr-2"> Save </button>
                    <a href="{{ route('home') }}" class="btn btn-light">Exit</a>
                </form>

            </div>
        </div>
        <div class="tab-pane fade" id="family" role="tabpanel" aria-labelledby="family-tab">
            <div class="container-fluid mt-4">
                {{-- <small class="text-danger">*Required fields</small> --}}
                <form action="" method="POST" id="relatives_">
                    @csrf
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label for="">File number </label>
                                <div class="input-group">
                                        <span class="input-group-text" id="file_no"><i
                                                class="mdi mdi-file-find"></i></span>
                                    <input type="text" class="form-control" placeholder="file_no"
                                           aria-label="file_no" aria-describedby="file_no" name="file_no">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label for="">Select Person </label>
                                <div class="input-group">
                                        <span class="input-group-text" id="nationality"><i
                                                class="mdi mdi-account"></i></span>
                                    <select name="person[]" id="person1" class="form-control">
                                        <option value="">Select person</option>

                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label for="">Select Relationship </label>
                                <div class="input-group">
                                        <span class="input-group-text" id="ethnicity"><i
                                                class="mdi mdi-link-variant"></i></span>
                                    <select name="relationship[]" id="relationship1" class="form-control">
                                        <option value="">select relationship</option>
                                        <option value="Parent">Parent</option>
                                        <option value="Child">Child</option>
                                        <option value="Sibling">Sibling</option>
                                        <option value="Cousin">Cousin</option>
                                        <option value="Uncle">Uncle</option>
                                        <option value="Aunt">Aunt</option>
                                        <option value="Niece">Niece</option>
                                        <option value="Nephew">Nephew</option>
                                        <option value="Others">Others</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 ">
                            <button type="button" class="btn btn-success" style="margin-top: 25px"
                                    onclick="add_row()"><span class="mdi mdi-plus"></span></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mr-2"> Save </button>
                    <a href="{{ route('home') }}" class="btn btn-light">Exit</a>
                </form>

            </div>
        </div>
        <div class="tab-pane fade" id="insurance" role="tabpanel" aria-labelledby="insurance-tab">
            <div class="container-fluid mt-4">
                {{-- <small class="text-danger">*Required fields</small> --}}
                <form action="" method="POST" id="relatives_">
                    @csrf
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label for="">Select Scheme </label>
                                <div class="input-group">
                                        <span class="input-group-text"><i
                                                class="mdi mdi-file-find"></i></span>
                                    <select name="insurance_scheme" id="insurance_scheme" class="form-control">
                                        <option value="">Select scheme</option>
                                        <option value="NHIS">NHIS</option>
                                        <option value="PLASCHEMA">PLASCHEMA</option>
                                        <option value="NNPC">NNPC</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="">Select HMO </label>
                                <div class="input-group">
                                        <span class="input-group-text" ><i
                                                class="mdi mdi-account"></i></span>
                                    <select name="hmo_id" id="hmo_id" class="form-control">
                                        <option value="">Select HMO</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="">HMO ID/No</label>
                                <div class="input-group">
                                        <span class="input-group-text"><i
                                                class="mdi mdi-link-variant"></i></span>
                                    <input type="text" name="hmo_no" class="form-control" placeholder="HMO ID/No">
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mr-2"> Save </button>
                    <a href="{{ route('home') }}" class="btn btn-light">Exit</a>
                </form>
            </div>
        </div>
        <div class="tab-pane fade" id="review" role="tabpanel" aria-labelledby="review-tab">Review</div>
    </div>

@endsection
