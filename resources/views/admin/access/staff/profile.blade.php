@extends('admin.layouts.admin')

@section('style')
{{-- <link href="{{  asset('plugins/bootstrap-datetimepicker/css/bootstrap.min.css" rel="stylesheet') }}" media="screen"> --}}
{{-- <link href="{{  asset('plugins/bootstrap-datetimepicker/css/bootstrap-datetimepicker.min.css') }}" rel="stylesheet"> --}}
<link href="{{  asset('plugins/daterangepicker/daterangepicker.css') }}" rel="stylesheet">
@endsection

@section('main-content')
<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1>Profile Page</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Profile Page</li>
        </ol>
      </div>
    </div>
  </div><!-- /.container-fluid -->
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row">

          <div class="col-md-3">
            <!-- Profile Image -->
            <div class="card card-primary card-outline">
              <div class="card-body box-profile">
                <div class="text-center">
                  <img class="profile-user-img img-fluid img-circle"
                       src="{!! url('storage/image/user/'.$user->filename) !!}"
                       alt="User profile picture">
                </div>

                <h3 class="profile-username text-center">{!! $user->getNameAttribute() !!}</h3>

                <p class="text-muted text-center">{!! $user->statuscategory->name !!}</p>
                {{-- <p class="text-muted text-center">{!! $user->phone_number !!}</p>
                <p class="text-muted text-center">{!! $user->email !!}</p> --}}

    

                <a href="#" class="btn btn-primary btn-block"><b>Follow</b></a>
              </div>
              <!-- /.card-body -->
            </div>
            <!-- /.card -->

            <!-- About Me Box -->
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">About Me</h3>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <strong><i class="fa fa-book mr-1"></i> Education</strong>

                <p class="text-muted">
                  {!! $user->content !!}
                </p>

              </div>
              <!-- /.card-body -->
            </div>
            <!-- /.card -->
          </div>
          <div class="col-md-9">
            <div class="card">
              <div class="card-header p-2">
                <ul class="nav nav-pills">
                  <li class="nav-item"><a class="nav-link active" href="#activity" data-toggle="tab">Information</a></li>
                  <li class="nav-item"><a class="nav-link" href="#timeline" data-toggle="tab">Avatar Upload</a></li>
                  <li class="nav-item"><a class="nav-link" href="#settings" data-toggle="tab">Settings</a></li>
                </ul>
              </div>
              <div class="card-body">
                <div class="tab-content">
                    <div class="active tab-pane" id="activity">
                        <div class="post">
                            <div class="user-block">
                              
                              <img class="img-circle img-bordered-sm" src="{!! url('storage/image/user/'.$user->filename) !!}" alt="{{ $user->getNameAttribute() }}">
                              <span class="username">
                                <a href="#">{{ $user->getNameAttribute() }}</a>
                                <a href="#" class="float-right btn-tool"><i class="fa fa-times"></i></a>
                              </span>
                              <span class="description">Shared publicly - {{ Carbon\Carbon::now() }}</span>
                            </div>
                            <!-- /.user-block -->
                            <p>
                                <h5>About Me</h5>
                                {!! $user->content !!}</p>
      
                            <p>
                                <div class="card-body p-0">
                                    <table class="table">
                                        <thead>
                                        {{-- <tr>
                                            <th style="width: 10px">#</th>
                                            <th>Task</th>
                                            <th>Progress</th>
                                            <th style="width: 40px">Label</th>
                                            
                                        </tr> --}}
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td>Fullname</td>
                                            <td>{!! $user->getNameAttribute() !!}</td>
                                            <td>Status Category</td>
                                            <td><span class="badge bg-dark">{!! $user->statuscategory->name !!}</span></td>
                                        </tr>
                                        <tr>
                                            <td>Email</td>
                                            <td>{!! $user->email !!}</td>
                                            <td>Phone Number</td>
                                            <td><span class="badge bg-dark">{!! $user->phone_number !!}</span></td>
                                        </tr>
                                        <tr>
                                            <td>Designation</td>
                                            <td>{!! $user->designation !!}</td>
                                            <td>Account Status</td>
                                            <td><span class="badge bg-dark">{!! $user->status->name !!}</span></td>
                                        </tr>
                                        <tr>
                                            <td>Role(s) Assigned</td>
                                            <td colspan="3">
                                                @if(!empty($user->getRoleNames()))
                                                    @foreach($user->getRoleNames() as $v)
                                                        <label class="badge badge-success">{{ $v }}</label>
                                                    @endforeach
                                                @endif
                                            </td>
                                           
                                        </tr>
                                        <tr>
                                            <td>Permission(s) Assigned</td>
                                            <td colspan="3">
                                                @if(!empty($user->getPermissionNames()))
                                                    @foreach($user->getPermissionNames() as $v)
                                                        <label class="badge badge-success">{{ $v }}</label>
                                                    @endforeach
                                                @endif
                                            </td>
                                            
                                        </tr>
                                       
                                        </tbody>
                                    </table>
                                </div>
                              <a href="#" class="link-black text-sm mr-2"><i class="fa fa-share mr-1"></i> Share</a>
                              <a href="#" class="link-black text-sm"><i class="fa fa-thumbs-up mr-1"></i> Like</a>
                              <span class="float-right">
                                <a href="#" class="link-black text-sm">
                                  <i class="fa fa-comments mr-1"></i> Comments (5)
                                </a>
                              </span>
                            </p>
      
                            <input class="form-control form-control-sm" type="text" placeholder="Type a comment">
                          </div>
                    </div>
                    
                    <div class="tab-pane" id="timeline">
                        {!! Form::model($user, ['method' => 'PATCH', 'route'=> ['users.update', $user->id], 'enctype' => 'multipart/form-data']) !!}
                        {{ csrf_field() }}
                            <input type="hidden" name="_method" value="PUT">
                            <div class="form-group">
                                <span class="badge badge-sm badge-dark">Current Login User Active Image...</span>
                                <br><br>
                                    <img src="{!! url('storage/image/user/'.$user->filename) !!}"  valign="middle" width="150px" height="120px" />
                                <br>
                                <hr>
                                <span class="badge badge-sm badge-dark">Use the browse... button to pick a new image and click on upload.</span>
                                <hr>
                                <div class="form-group">
                                    <div class="row">
                                    <div class="col-sm-6">
                                        {{ Form::label('filename', 'Passport:') }}
                                        {{ Form::file('filename') }}
                                    </div>
                
                                    <div class="col-sm-4" >
                                        <div class="float-right" style="border: dotted 1px 000000">
                                            <img src="" class="float-right" id="myimg" width=80>
                                        </div>
                                        
                                    </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-12">
                                    <div class="form-group">
                                        <div class="float-right">
                                            <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Upload Passport</button>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                            <hr>
                        {!! Form::close() !!}
                    </div>
                   

                    <div class="tab-pane" id="settings">
                        <form class="form-horizontal">
                            <div class="form-group row">
                                <label class="control-label col-sm-2 col-form-label" for="title">Surname:</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" id="surname" name="surname" value="{!! (!empty($user->surname)) ? $user->surname : old('surname') !!}" autofocus>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="control-label col-sm-2 col-form-label" for="title">Firstname:</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" id="firstname" name="firstname" value="{!! (!empty($user->firstname)) ? $user->firstname : old('firstname') !!}" autofocus>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="control-label col-sm-2 col-form-label" for="title">Othername:</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" id="othername" name="othername" value="{!! (!empty($user->othername)) ? $user->othername : old('othername') !!}" autofocus>
                                </div>
                            </div>
                        
                            <div class="form-group row">
                                <label class="control-label col-sm-2" for="email">Email</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" id="email" name="email" value="{!! (!empty($user->email)) ? $user->email : old('email') !!}" readonly autofocus>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="control-label col-sm-2" for="phone_number">Phone</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" id="phone_number" name="phone_number" value="{!! (!empty($user->phone_number)) ? $user->phone_number : old('phone_number') !!}" readonly autofocus>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="url" class="col-sm-2 control-label">About Me</label>
                                <div class="col-sm-10">
                                    <textarea id="content" name="content" cols="30" rows="10" class="form-control" placeholder="Enter Content">
                                    {!! (!empty($user->content)) ? $user->content : old('content') !!}
                                    </textarea>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="password" class="col-sm-2 control-label">Password</label>
            
                                <div class="col-sm-10">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password">
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="offset-sm-2 col-sm-10">
                                <div class="checkbox">
                                    <label>
                                    <input type="checkbox"> I agree to the <a href="#">terms and conditions</a>
                                    </label>
                                </div>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="offset-sm-2 col-sm-10">
                                <button type="submit" class="btn btn-danger">Submit</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                </div>
              </div>
            </div>
          </div>
          
        </div>
      </div>
</section>

@endsection

@section('scripts')
<script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
<script src="{{ asset('plugins/datepicker/bootstrap-datepicker.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor.js') }}"></script>
{{-- <script  src="{{ asset('plugins/bootstrap-datetimepicker/js/bootstrap-datetimepicker.min.js') }}"></script> --}}
<script  src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>

<script>
    CKEDITOR.replace('content');
    // CKEDITOR.replace('contact_address');
    // CKEDITOR.replace('home_address');
    $('#appStartDate').datepicker({
        format: 'dd/mm/yyyy',
        language: 'en'
    });

    $('#appEndDate').datepicker({
        format: 'dd/MM/yyyy hh:mm',
        language: 'en'
    });

    // $('#timepicker').datetimepicker({
    //   format: 'LT'
    // });
    // $("#consultation_fee");
    // $("#secondary_phone_number").inputmask('9999-999-9999');
</script>
<script type="text/javascript">


    function readURL() {
      var myimg = document.getElementById("myimg");
      var input = document.getElementById("filename");
      if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
        console.log("changed");
          myimg.src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
      }
    }
  
    document.querySelector('#filename').addEventListener('change', function(){
        readURL()
    });
  </script>

@endsection

