<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>{{ env('APP_NAME') }} | @yield('title')</title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/flag-icon-css/css/flag-icon.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/vendor.bundle.base.css') }}">
    <!-- endinject -->
    <!-- Plugin css for this page -->
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/jquery-bar-rating/css-stars.css') }}" />
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/font-awesome/css/font-awesome.min.css') }}" />
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <!-- endinject -->
    <!-- Layout styles -->
    <link rel="stylesheet" href="{{ asset('admin/assets/css/demo_1/style.css') }}" />
    <!-- End layout styles -->
    <link rel="shortcut icon" href="{{ asset('admin/assets/images/favicon.png') }}" />
    <link rel="stylesheet" href="{{ asset('css/selectisize.css') }}">
    <script src="{{ asset('js/app.js') }}"></script>
    <link rel="stylesheet" href="asset('/plugins/dataT/datatables.min.css')">
    <style>
        .ck-editor__editable_inline {
            min-height: 200px;
        }
    </style>

    <!-- Styles -->
    {{-- <link href="{{ asset('css/app.css') }}" rel="stylesheet"> --}}
</head>

<body>
    <div class="container-scroller">
        <!-- partial:partials/_sidebar.html -->
        @include('admin.partials.sidebar')
        <!-- partial -->
        <div class="container-fluid page-body-wrapper">
            <!-- partial:partials/_settings-panel.html -->
            @include('admin.partials.settings_pannel')
            <!-- partial -->
            <!-- partial:partials/_navbar.html -->
            @include('admin.partials.navbar')
            <!-- partial -->
            <div class="main-panel">
                <div class="content-wrapper pb-0">
                    {{-- <div class="page-header flex-wrap">
                        <div class="header-left">
                            <button class="btn btn-primary mb-2 mb-md-0 mr-2"> Create new document </button>
                            <button class="btn btn-outline-primary bg-white mb-2 mb-md-0"> Import documents </button>
                        </div>
                        <div class="header-right d-flex flex-wrap mt-2 mt-sm-0">
                            <div class="d-flex align-items-center">
                                <a href="#">
                                    <p class="m-0 pr-3">@yield('page_name')</p>
                                </a>
                                <a class="pl-3 mr-4" href="#">
                                    <p class="m-0">@yield('subpage_name')</p>
                                </a>
                            </div>
                        </div>
                    </div> --}}
                    <section class="content-header">
                        <div class="container-fluid">
                            <div class="row mb-2">
                                <div class="col-sm-6">
                                    <h1>@yield('page_name')</h1>
                                </div>
                                <div class="col-sm-6">
                                    <ol class="breadcrumb float-sm-right">
                                        <li class="breadcrumb-item"><a href="#">@yield('page_name')</a></li>
                                        <li class="breadcrumb-item active">@yield('subpage_name')</li>
                                    </ol>
                                </div>
                            </div>
                        </div><!-- /.container-fluid -->
                        <div>
                            @if (count($errors))
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert"
                                        aria-hidden="true">×</button>
                                    <!-- <h5><i class="icon fa fa-info"></i> Alert!</h5> -->
                                    <ul>
                                        @foreach ($errors as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>

                                </div>
                            @endif
                            @include('admin.partials.notification')
                        </div>
                    </section>
                    <!-- first row starts here -->
                    @yield('content')
                </div>
                <!-- content-wrapper ends -->
                <!-- partial:partials/_footer.html -->
                @include('admin.partials.footer')
                <!-- partial -->
            </div>
            <!-- main-panel ends -->
        </div>
        <!-- page-body-wrapper ends -->
    </div>
    <!-- container-scroller -->
    <!-- plugins:js -->
    <script src="{{ asset('admin/assets/vendors/js/vendor.bundle.base.js') }}"></script>
    @yield('scripts')
    <!-- endinject -->
    <!-- Plugin js for this page -->
    {{-- <script src="admin/assets/vendors/jquery-bar-rating/jquery.barrating.min.js"></script>
    <script src="admin/assets/vendors/chart.js/Chart.min.js"></script>
    <script src="admin/assets/vendors/flot/jquery.flot.js"></script>
    <script src="admin/assets/vendors/flot/jquery.flot.resize.js"></script>
    <script src="admin/assets/vendors/flot/jquery.flot.categories.js"></script>
    <script src="admin/assets/vendors/flot/jquery.flot.fillbetween.js"></script>
    <script src="admin/assets/vendors/flot/jquery.flot.stack.js"></script> --}}
    <!-- End plugin js for this page -->
    <!-- inject:js -->
    <script src="{{ asset('admin/assets/js/off-canvas.js') }}"></script>
    <script src="{{ asset('admin/assets/js/hoverable-collapse.js') }}"></script>
    <script src="{{ asset('admin/assets/js/misc.js') }}"></script>
    <script src="{{ asset('admin/assets/js/settings.js') }}"></script>
    {{-- <script src="{{ asset('admin/assets/js/todolist.js') }}"></script> --}}
    <!-- endinject -->
    <!-- Custom js for this page -->
    <script src="{{ asset('admin/assets/js/dashboard.js') }}"></script>
    <script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
    <script>
        ClassicEditor
            .create(document.querySelector('.classic-editor'), {
                toolbar: {
                    items: [
                        'undo', 'redo',
                        '|', 'heading',
                        '|', 'bold', 'italic',
                        '|', 'link', 'uploadImage', 'insertTable', 'mediaEmbed',
                        '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                    ]
                },
                cloudServices: {
                    // All predefined builds include the Easy Image feature.
                    // Provide correct configuration values to use it.
                    // tokenUrl: 'https://example.com/cs-token-endpoint',
                    // uploadUrl: 'https://your-organization-id.cke-cs.com/easyimage/upload/'
                    // Read more about Easy Image - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/easy-image.html.
                    // For other image upload methods see the guide - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/image-upload.html.
                }
            })
            .then(editor => {
                window.editor = editor;
            })
            .catch(err => {
                console.error(err);
            });
    </script>
    <!-- End custom js for this page -->

</body>

</html>
