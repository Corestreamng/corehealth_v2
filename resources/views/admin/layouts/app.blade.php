<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name='csrf-token' content='{{ csrf_token() }}'>
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
    <link rel="shortcut icon" href="data:image/png;base64,{{ appsettings()->favicon ?? '' }}" />
    <link rel="icon" type="image/png" href="data:image/png;base64,{{ appsettings()->favicon ?? '' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}">
    <script src="{{ asset('js/app.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('/plugins/dataT/datatables.min.css') }}">
    <script src="{{ asset('plugins/chartjs/Chart.js') }}"></script>

    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- End Toastr CSS -->

    <style>
        .ck-editor__editable_inline {
            min-height: 200px;
        }

        .tab-content .tab-pane {
            display: none;
        }

        .tab-content .active {
            display: block;
        }

        .loading-overlay {
            /* Set the overlay to cover the entire viewport */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            /* Make sure the overlay appears on top of other elements */
            opacity: 1;
            /* Start with full opacity */
            /* pointer-events: none;
            Allow click-through while loading */
        }



        .loading-overlay svg {
            width: 50px;
            /* Adjust the size of your icon/animation as needed */
            height: 50px;
            /* Adjust the size of your icon/animation as needed */
            background-color: #fff;
            /* Replace this with the desired color of your icon/animation */
            border-radius: 50%;
            /* Make sure the icon/animation is a circle */
            animation: pulse 2s infinite;
            /* Use the 'pulse' animation for 2 seconds, and repeat infinitely */
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                /* Start with the original size */
                opacity: 0.8;
                /* You can adjust the opacity for the pulsating effect */
            }

            50% {
                transform: scale(1.2);
                /* Scale up to 120% */
                opacity: 0.5;
                /* Lower opacity in the middle of the animation */
            }

            100% {
                transform: scale(1);
                /* Return to the original size */
                opacity: 0.8;
                /* Restore the opacity */
            }
        }
    </style>
    <script>
        window.onload = function() {
            // Hide the preloader once all page content is fully loaded
            document.getElementById("preloader").style.display = "none";
        };
    </script>
    <script>
        // Get the URL parameter value using JavaScript
        function getParameterByName(name, url) {
            if (!url) url = window.location.href;
            name = name.replace(/[\[\]]/g, '\\$&');
            var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
                results = regex.exec(url);
            if (!results) return null;
            if (!results[2]) return '';
            return decodeURIComponent(results[2].replace(/\+/g, ' '));
        }

        // Scroll to the section based on the URL parameter
        function scrollToSection() {
            var sectionToScroll = getParameterByName('section');
            if (sectionToScroll) {
                var element = document.getElementById(sectionToScroll);
                if (element) {
                    element.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            }
        }

        // Call the scrollToSection function on page load
        window.addEventListener('load', scrollToSection);
    </script>

    <script>
        function popMessengerWindow() {
            var mywindow = window.open("{{ route('messages') }}", 'Messenger', 'height=800,width=800');
            mywindow.focus(); // IE >= 10
        }
    </script>

    <!-- Styles -->
    {{-- <link href="{{ asset('css/app.css') }}" rel="stylesheet"> --}}
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #a7c7e7;
            overflow: hidden;
        }

        .cloud {
            position: absolute;
            background-color: #fff;
            border-radius: 50%;
            opacity: 0.7;
            animation: float 10s linear infinite;
        }

        .cloud::before,
        .cloud::after {
            content: "";
            position: absolute;
            background-color: #fff;
            border-radius: 50%;
            opacity: 0.7;
        }

        .cloud::before {
            width: 50px;
            height: 50px;
            top: -20px;
            left: 10px;
        }

        .cloud::after {
            width: 80px;
            height: 80px;
            top: -10px;
            right: 10px;
        }

        .cloud:nth-child(odd) {
            width: 120px;
            height: 120px;
            top: 100px;
            left: -60px;
        }

        .cloud:nth-child(even) {
            width: 150px;
            height: 150px;
            top: 250px;
            right: -60px;
        }

        /* Add more clouds as needed */
        .cloud:nth-child(3) {
            width: 100px;
            height: 100px;
            top: 50px;
            left: 50px;
        }

        .cloud:nth-child(4) {
            width: 180px;
            height: 180px;
            top: 350px;
            right: 150px;
        }

        /* Keyframe animation */
        @keyframes float {
            0% {
                transform: translateY(0) translateX(0);
            }

            50% {
                transform: translateY(-20px) translateX(20px);
            }

            100% {
                transform: translateY(0) translateX(0);
            }
        }
    </style>
</head>

<body id='app'>
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
                    <!-- Loading Icon -->
                    <div class="loading-overlay" id="preloader">
                        <svg xmlns="http://www.w3.org/2000/svg" height="5em" viewBox="0 0 512 512">
                            <!--! Font Awesome Free 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. -->
                            <path
                                d="M228.3 469.1L47.6 300.4c-4.2-3.9-8.2-8.1-11.9-12.4h87c22.6 0 43-13.6 51.7-34.5l10.5-25.2 49.3 109.5c3.8 8.5 12.1 14 21.4 14.1s17.8-5 22-13.3L320 253.7l1.7 3.4c9.5 19 28.9 31 50.1 31H476.3c-3.7 4.3-7.7 8.5-11.9 12.4L283.7 469.1c-7.5 7-17.4 10.9-27.7 10.9s-20.2-3.9-27.7-10.9zM503.7 240h-132c-3 0-5.8-1.7-7.2-4.4l-23.2-46.3c-4.1-8.1-12.4-13.3-21.5-13.3s-17.4 5.1-21.5 13.3l-41.4 82.8L205.9 158.2c-3.9-8.7-12.7-14.3-22.2-14.1s-18.1 5.9-21.8 14.8l-31.8 76.3c-1.2 3-4.2 4.9-7.4 4.9H16c-2.6 0-5 .4-7.3 1.1C3 225.2 0 208.2 0 190.9v-5.8c0-69.9 50.5-129.5 119.4-141C165 36.5 211.4 51.4 244 84l12 12 12-12c32.6-32.6 79-47.5 124.6-39.9C461.5 55.6 512 115.2 512 185.1v5.8c0 16.9-2.8 33.5-8.3 49.1z" />
                        </svg>

                    </div>
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
                                <div class="col-md-6">
                                    <h1>@yield('page_name')</h1>
                                </div>
                                <div class="col-md-6">
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
                    <div class="cloud"></div>
                    <div class="cloud"></div>
                    <div class="cloud"></div>
                    <div class="cloud"></div>
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
    <script src="{{ asset('assets/js/select2.min.js') }}"></script>
    <script>
        $('.select2').select2();
    </script>

    {{-- <script src="admin/assets/vendors/chart.js/Chart.min.js"></script> --}}
    @yield('scripts')
    <!-- endinject -->
    <!-- Plugin js for this page -->
    {{-- <script src="admin/assets/vendors/jquery-bar-rating/jquery.barrating.min.js"></script> --}}
    {{-- <script src="admin/assets/vendors/flot/jquery.flot.js"></script>
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
    {{-- <script src="{{ asset('admin/assets/js/dashboard.js') }}"></script> --}}

    <!-- End custom js for this page -->
    @if (env('ENABLE_TWAKTO') == 1)
        <!--Start of Tawk.to Script-->
        <script type="text/javascript">
            var Tawk_API = Tawk_API || {},
                Tawk_LoadStart = new Date();
            (function() {
                var s1 = document.createElement("script"),
                    s0 = document.getElementsByTagName("script")[0];
                s1.async = true;
                s1.src = 'https://embed.tawk.to/66cf52e250c10f7a00a161f2/1i6ctnie6';
                s1.charset = 'UTF-8';
                s1.setAttribute('crossorigin', '*');
                s0.parentNode.insertBefore(s1, s0);
            })();
        </script>
        <!--End of Tawk.to Script-->
    @endif

    <script>
        setInterval(function() {
            $.get('/csrf-token').done(function(data) {
                $('meta[name="csrf-token"]').attr('content', data.token);
                $('input[name="_token"]').val(data.token);
            });
        }, 1800000); // Refresh csrf token every 30 minutes
    </script>

    <!-- Toastr JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- End Toastr JS -->

</body>

</html>
