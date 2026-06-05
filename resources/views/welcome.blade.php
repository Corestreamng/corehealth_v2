<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>CoreHealth - Home : Hospital Automation</title>
    <!-- Montserrat font -->
    <link href="{{ asset('assets/css/montserrat-font.css') }}" rel="stylesheet">
    <!-- Template CSS Style link -->
    <link rel="stylesheet" href="{{ asset('assets/css/style-starter.css') }}">
</head>

<body>
    <header id="site-header" class="fixed-top">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light">
                <a class="navbar-brand" href="index.html">
                    <i class="fas fa-user-md"></i>Corehealth
                </a>
                <button class="navbar-toggler collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#navbarScroll" aria-controls="navbarScroll" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon fa icon-expand fa-bars"></span>
                    <span class="navbar-toggler-icon fa icon-close fa-times"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarScroll">
                    <ul class="navbar-nav ms-auto me-2 my-2 my-lg-0 navbar-nav-scroll">

                        {{-- <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="index.html"><i
                                    class="fa fa-user-md"></i> Login</a>
                        </li> --}}
                        @auth
                            <li class="nav-item">
                                <a class="nav-link active" aria-current="page" href="{{ route('home') }}">{{ __('front.home') }}</a>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link active" aria-current="page" href="{{ route('login') }}"><i
                                        class="fa fa-user-md"></i> {{ __('front.login') }}</a>
                            </li>

                            {{-- @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link active" aria-current="page" href="{{ route('register') }}">Home</a>
                                </li>
                            @endif --}}
                        @endauth
                        <!--  <li class="nav-item">
                            <a class="nav-link" href="about.html">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="services.html">Services</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="contact.html">Contact</a>
                        </li>
                    </ul>
                    <form action="#error" method="GET" class="d-flex search-header">
                        <input class="form-control" type="search" placeholder="Enter Keyword..." aria-label="Search"
                            required>
                        <button class="btn btn-style" type="submit"><i class="fas fa-search"></i></button>
                    </form>-->
                </div>
                <!-- toggle switch for light and dark theme -->
                <div class="cont-ser-position">
                    <nav class="navigation">
                        <div class="theme-switch-wrapper">
                            <label class="theme-switch" for="checkbox">
                                <input type="checkbox" id="checkbox">
                                <div class="mode-container">
                                    <i class="gg-sun"></i>
                                    <i class="gg-moon"></i>
                                </div>
                            </label>
                        </div>
                    </nav>
                </div>
                <!-- //toggle switch for light and dark theme -->
            </nav>
        </div>
    </header>
    <!-- banner section -->
    <section class="w3l-main-slider" id="home">
        <div class="banner-content">
            <div id="demo-1">
                <div class="demo-inner-content">
                    <div class="container">
                        <div class="banner-info">
                            <p class="mb-1">{{ __('front.only_one_kind_of_automation') }}</p>
                            <h3>{{ __('front.your_new_smile') }}</h3>
                            <!-- <a class="btn btn-style btn-style-2 mt-sm-5 mt-4" href="appointment.html">Book Now</a>-->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- //banner section -->


    <!-- call section -->
    <section class="w3l-call-to-action-6">
        <div class="container py-md-5 py-sm-4 py-5">
            <div class="d-sm-flex align-items-center justify-content-between">
                <div class="left-content-call">
                    <p class="text-white mt-1">{{ __('front.hospital_management') }}</p>
                    <h3 class="title-big">{{ __('front.begin_here') }}</h3>

                </div>
                <div class="right-content-call mt-sm-0 mt-4">
                    <ul class="buttons">
                        <li class="phone-sec me-lg-4"><i class="fas fa-phone-volume"></i>
                            <a class="call-style-w3" href="tel:+2348160876560">+2347050737402</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
    <!-- //call section -->

    <!-- footer -->
    <footer class="w3l-footer-29-main">
        <div class="footer-29 pt-5 pb-4">
            <div class="container pt-md-4">
                <div class="row footer-top-29">
                    <div class="col-md-5 footer-list-29 pe-xl-5">
                        <h6 class="footer-title-29">{{ __('front.contact_info') }}</h6>
                        <p class="mb-2 pe-xl-5">{{ __('front.address') }} : Suite 7b, Korinjoh House, Yakubu Gowon way, Jos, Plateau
                            State.
                        </p>
                        <p class="mb-2">{{ __('front.phone_number') }} : <a href="tel:+2348160876560">+2348160876560</a></p>
                        <p class="mb-2">{{ __('front.email') }} : <a href="mailto:info@corestream.ng">info@corestream.ng</a></p>
                    </div>
                    <div class="col-md-2 col-4 footer-list-29 mt-md-0 mt-4">
                        <ul>
                            <h6 class="footer-title-29">{{ __('front.about') }}</h6>
                            <li><a href="#">{{ __('front.services') }}</a></li>
                            <li><a href="#">{{ __('front.special_offers') }}</a></li>
                            <li><a href="#">{{ __('front.orthodontics') }}</a></li>
                            <li><a href="#">{{ __('front.about_us') }}</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-3 col-md-3 col-4 ps-lg-5 ps-md-4 footer-list-29 mt-md-0 mt-4">
                        <ul>
                            <h6 class="footer-title-29">{{ __('front.explore') }}</h6>
                            <li><a href="#blog">{{ __('front.blog_posts') }}</a></li>
                            <li><a href="#privacy">{{ __('front.privacy_policy') }}</a></li>
                            <li><a href="#">{{ __('front.contact_us') }}</a></li>
                            <li><a href="#license">{{ __('front.license_and_uses') }}</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-2 col-md-2 col-4 footer-list-29 mt-md-0 mt-4">
                        <ul>
                            <h6 class="footer-title-29">{{ __('front.hospital') }}</h6>
                            <!--<li><a href="#doctor">Dr. John Doe</a></li>
                            <li><a href="#doctor">Dr. Martin Ker</a></li>
                            <li><a href="#doctor">Dr. Alexander</a></li>
                            <li><a href="#doctor">Dr. Eliz Wilson</a></li>-->
                        </ul>
                    </div>
                </div>
                <!-- copyright -->
                <p class="copy-footer-29 text-center pt-lg-2 mt-5 pb-2">© {{ date('Y') }} Corestream Nigeria. {{ __('front.all_rights_reserved') }}
                    {{ __('front.a_product_of') }}
                    <a href="https://corestream.ng/" target="_blank">
                        CorestreamNG</a>
                </p>
                <!-- //copyright -->
            </div>
        </div>
    </footer>
    <!-- //footer -->

    <!-- Js scripts -->
    <!-- move top -->
    <button onclick="topFunction()" id="movetop" title="Go to top">
        <span class="fas fa-level-up-alt" aria-hidden="true"></span>
    </button>
    <script>
        // When the user scrolls down 20px from the top of the document, show the button
        window.onscroll = function() {
            scrollFunction()
        };

        function scrollFunction() {
            if (document.body.scrollTop> 20 || document.documentElement.scrollTop> 20) {
                document.getElementById("movetop").style.display = "block";
            } else {
                document.getElementById("movetop").style.display = "none";
            }
        }

        // When the user clicks on the button, scroll to the top of the document
        function topFunction() {
            document.body.scrollTop = 0;
            document.documentElement.scrollTop = 0;
        }
    </script>
    <!-- //move top -->

    <!-- common jquery plugin -->
    <script src="{{asset('assets/js/jquery-3.3.1.min.js')}}"></script>
    <!-- //common jquery plugin -->

    <!-- for services carousel slider -->
    <script src="{{asset('assets/js/owl.carousel.js')}}"></script>
    <script>
        $(document).ready(function() {
            $('.owl-three').owlCarousel({
                loop: true,
                stagePadding: 20,
                margin: 20,
                autoplay: true,
                autoplayTimeout: 5000,
                autoplaySpeed: 1000,
                autoplayHoverPause: false,
                nav: false,
                responsive: {
                    0: {
                        items: 1
                    },
                    600: {
                        items: 2
                    },
                    991: {
                        items: 3
                    },
                    1200: {
                        items: 4
                    }
                }
            })
        })
    </script>
    <!-- //for services carousel slider -->

    <!-- theme switch js (light and dark)-->
    <script src="{{asset('assets/js/theme-change.js')}}"></script>
    <!-- //theme switch js (light and dark)-->

    <!-- MENU-JS -->
    <script>
        $(window).on("scroll", function() {
            var scroll = $(window).scrollTop();

            if (scroll>= 80) {
                $("#site-header").addClass("nav-fixed");
            } else {
                $("#site-header").removeClass("nav-fixed");
            }
        });

        //Main navigation Active Class Add Remove
        $(".navbar-toggler").on("click", function() {
            $("header").toggleClass("active");
        });
        $(document).on("ready", function() {
            if ($(window).width()> 991) {
                $("header").removeClass("active");
            }
            $(window).on("resize", function() {
                if ($(window).width()> 991) {
                    $("header").removeClass("active");
                }
            });
        });
    </script>
    <!-- //MENU-JS -->

    <!-- disable body scroll which navbar is in active -->
    <script>
        $(function() {
            $('.navbar-toggler').click(function() {
                $('body').toggleClass('noscroll');
            })
        });
    </script>
    <!-- //disable body scroll which navbar is in active -->

    <!-- bootstrap -->
    <script src="{{asset('assets/js/bootstrap.min.js')}}"></script>
    <!-- //bootstrap -->
    <!-- //Js scripts -->
</body>

</html>
