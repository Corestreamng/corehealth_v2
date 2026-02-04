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
    <!-- Bootstrap CSS -->
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" />
    <!-- End Bootstrap CSS -->
    <link rel="shortcut icon" href="data:image/png;base64,{{ appsettings()->favicon ?? '' }}" />
    <link rel="icon" type="image/png" href="data:image/png;base64,{{ appsettings()->favicon ?? '' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}">
    <script src="{{ asset('js/app.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('/plugins/dataT/datatables.min.css') }}">
    <script src="{{ asset('plugins/chartjs/Chart.js') }}"></script>

    <!-- Toastr CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/toastr.min.css') }}" />
    <!-- End Toastr CSS -->

    <!-- Google Fonts: Inter -->
    <link href="{{ asset('assets/css/inter-font.css') }}" rel="stylesheet">

    @php
        $primaryColor = appsettings()->hos_color ?? '#011b33';
        $hoverColor = adjustBrightness($primaryColor, 20);
        $lightColor = hexToRgba($primaryColor, 0.1);

        function adjustBrightness($hex, $percent) {
            $hex = str_replace('#', '', $hex);
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $r = min(255, $r + ($r * $percent / 100));
            $g = min(255, $g + ($g * $percent / 100));
            $b = min(255, $b + ($b * $percent / 100));
            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }

        function hexToRgba($hex, $alpha) {
            $hex = str_replace('#', '', $hex);
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            return "rgba($r, $g, $b, $alpha)";
        }
    @endphp

    <style>
        :root {
            --primary-color: {{ $primaryColor }};
            --primary-hover: {{ $hoverColor }};
            --primary-light: {{ $lightColor }};
        }

        /* Paystack-inspired Clean Design */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
            margin: 0;
            overflow-x: hidden;
        }

        .container-scroller {
            display: flex;
            min-height: 100vh;
        }

        /* Paystack Sidebar Styles */
        .ch-sidebar {
            width: 250px;
            background: var(--primary-color);
            position: fixed;
            height: 100vh;
            overflow: hidden;
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }

        /* Sidebar minimized state */
        .sidebar-icon-only .ch-sidebar {
            width: 70px;
            overflow: visible;
        }

        /* Hide text elements with smooth fade */
        .sidebar-icon-only .ch-sidebar .nav-brand h6,
        .sidebar-icon-only .ch-sidebar .nav-brand p,
        .sidebar-icon-only .ch-sidebar .nav-brand-title,
        .sidebar-icon-only .ch-sidebar .nav-brand-version,
        .sidebar-icon-only .ch-sidebar .menu-title,
        .sidebar-icon-only .ch-sidebar .menu-arrow,
        .sidebar-icon-only .ch-sidebar .nav-item-head {
            opacity: 0;
            visibility: hidden;
            width: 0;
            transition: opacity 0.2s ease, visibility 0.2s ease, width 0.2s ease;
        }

        /* Adjust nav-brand in icon-only mode */
        .sidebar-icon-only .ch-sidebar .nav-brand {
            padding: 15px 0 !important;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
            overflow: hidden;
        }

        .sidebar-icon-only .ch-sidebar .nav-brand > div {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            width: 100% !important;
        }

        .sidebar-icon-only .ch-sidebar .nav-brand img {
            max-width: 35px !important;
            width: 35px !important;
            height: 35px !important;
            margin-bottom: 0 !important;
            object-fit: contain !important;
        }

        .sidebar-icon-only .ch-sidebar .nav-brand > div > div {
            width: 35px !important;
            height: 35px !important;
            font-size: 0.85rem !important;
            margin-bottom: 0 !important;
        }

        /* Center nav links in icon-only mode */
        .sidebar-icon-only .ch-sidebar .nav-link {
            justify-content: center;
            padding: 12px 0 !important;
            position: relative;
            width: 70px;
        }

        /* Fix profile in icon-only mode */
        .sidebar-icon-only .ch-sidebar .nav-profile {
            padding: 15px 0 !important;
            justify-content: center !important;
        }

        .sidebar-icon-only .ch-sidebar .nav-profile a {
            justify-content: center !important;
            width: 100% !important;
        }

        .sidebar-icon-only .ch-sidebar .nav-profile .ml-3,
        .sidebar-icon-only .ch-sidebar .nav-profile .mdi-logout {
            display: none !important;
        }

        .sidebar-icon-only .ch-sidebar .nav-profile img {
            margin: 0 !important;
        }

        /* Keep icons visible and centered */
        .sidebar-icon-only .ch-sidebar .menu-icon {
            margin-right: 0 !important;
            font-size: 20px !important;
            transition: all 0.3s ease;
        }

        /* Hide submenus completely */
        .sidebar-icon-only .ch-sidebar .sub-menu {
            display: none !important;
        }

        /* =============================================
           FLYOUT MENU LOGIC (Desktop Icon-Only Mode)
           ============================================= */
        @media (min-width: 992px) {
            /* When sidebar is collapsed */
            .sidebar-icon-only .ch-sidebar {
                overflow: visible !important; /* Allow flyouts to spill out */
            }

            .sidebar-icon-only .ch-sidebar .nav-item {
                position: relative; /* Anchor for absolute positioning */
            }

            /* Show submenu on hover */
            .sidebar-icon-only .ch-sidebar .nav-item:hover > .collapse,
            .sidebar-icon-only .ch-sidebar .nav-item:hover > .collapsing {
                display: block !important;
                position: absolute;
                left: 70px;
                top: 0;
                width: 240px;
                background: white;
                box-shadow: 4px 4px 15px rgba(0, 0, 0, 0.1);
                border-radius: 0 12px 12px 0;
                z-index: 9999;
                height: auto !important;
                padding: 10px 0;
                visibility: visible !important;
                opacity: 1 !important;
                border: 1px solid #f0f0f0;
            }

            /* Style the flyout content */
            .sidebar-icon-only .ch-sidebar .sub-menu {
                background: transparent !important;
                padding: 0 !important;
                margin: 0 !important;
                border: none !important;
                box-shadow: none !important;
                display: block !important;
                transform: none !important;
                opacity: 1 !important;
            }

            .sidebar-icon-only .ch-sidebar .sub-menu .nav-item {
                opacity: 1 !important;
                transform: none !important;
                display: block !important;
            }

            .sidebar-icon-only .ch-sidebar .sub-menu .nav-link {
                padding: 10px 20px !important;
                color: #4B5563 !important;
                font-size: 13px !important;
                text-align: left !important;
                justify-content: flex-start !important;
            }

            .sidebar-icon-only .ch-sidebar .sub-menu .nav-link:hover {
                background: #F3F4F6 !important;
                color: var(--primary-color) !important;
                padding-left: 25px !important;
            }

            .sidebar-icon-only .ch-sidebar .sub-menu .nav-link::before {
                display: none !important; /* Remove the dot */
            }

            /* Hide the original tooltips since we have the menu now */
            .sidebar-icon-only .ch-sidebar .nav-link:hover::after,
            .sidebar-icon-only .ch-sidebar .nav-link:hover::before {
                display: none !important;
            }
        }

        /* =============================================
           MOBILE RESTORATION (Fixing the broken expansion)
           ============================================= */
        @media (max-width: 991px) {
            /* Reset all "icon-only" overrides for mobile */
            .sidebar-icon-only .ch-sidebar .collapse {
                display: none; /* Default bootstrap behavior */
                position: static !important;
                width: auto !important;
                background: transparent !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }

            .sidebar-icon-only .ch-sidebar .collapse.show {
                display: block; /* Default bootstrap behavior */
            }

            .sidebar-icon-only .ch-sidebar .collapsing {
                display: block;
                position: static !important;
                width: auto !important;
                background: transparent !important;
                height: 0;
                overflow: hidden;
                transition: height 0.35s ease;
            }

            /* Ensure submenus look correct inline */
            .sidebar-icon-only .ch-sidebar .sub-menu {
                background: rgba(0, 0, 0, 0.2) !important;
                box-shadow: none !important;
                padding: 5px 0 !important;
            }

            .sidebar-icon-only .ch-sidebar .sub-menu .nav-link {
                color: rgba(255,255,255,0.7) !important;
                padding-left: 40px !important;
            }
        }

        /* Smooth transitions for all text elements */
        .ch-sidebar .nav-brand h6,
        .ch-sidebar .nav-brand p,
        .ch-sidebar .menu-title,
        .ch-sidebar .menu-arrow,
        .ch-sidebar .nav-item-head {
            transition: opacity 0.3s ease, visibility 0.3s ease, width 0.3s ease;
            opacity: 1;
            visibility: visible;
        }

        .ch-sidebar .nav {
            padding: 0;
            display: flex;
            flex-direction: column;
            height: 100%;
            margin: 0;
        }

        /* Top brand section */
        .ch-sidebar .nav-brand {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
        }

        .ch-sidebar .nav-brand-logo {
            max-width: 80px;
            height: auto;
            border-radius: 12px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .ch-sidebar .nav-brand-abbr {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .ch-sidebar .nav-brand-title {
            font-size: 14px !important;
            font-weight: 600 !important;
            margin-bottom: 3px !important;
            color: #fff !important;
        }

        .ch-sidebar .nav-brand-version {
            font-size: 12px !important;
            color: rgba(255, 255, 255, 0.6) !important;
        }

        .ch-sidebar .nav-brand h6 {
            font-size: 14px !important;
            font-weight: 600 !important;
            margin-bottom: 3px !important;
            color: #fff !important;
        }

        .ch-sidebar .nav-brand p {
            font-size: 12px !important;
            color: rgba(255, 255, 255, 0.6) !important;
        }

        /* Scrollable navigation area */
        .ch-sidebar-nav-scrollable {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 20px 0;
        }

        .ch-sidebar-nav-scrollable::-webkit-scrollbar {
            width: 4px;
        }

        .ch-sidebar-nav-scrollable::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        .ch-sidebar-nav-scrollable::-webkit-scrollbar-track {
            background: transparent;
        }

        /* Section titles - Dynamic lighter shade */
        .nav-item-head {
            font-size: 11px !important;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.6) !important;
            padding: 0 20px;
            margin-bottom: 10px;
            margin-top: 5px;
            font-weight: 600;
            letter-spacing: 0.5px;
            display: block;
        }

        .ch-sidebar .nav-item.pt-2 {
            margin-top: 20px;
            padding-top: 0 !important;
        }

        .ch-sidebar .nav-item.pb-1 {
            padding-bottom: 0 !important;
        }

        /* Menu items - Dynamic colors based on hos_color */
        .ch-sidebar .nav-item {
            margin: 0;
            border: none;
        }

        .ch-sidebar .nav-link {
            padding: 10px 20px !important;
            color: rgba(255, 255, 255, 0.8) !important;
            font-weight: 500;
            font-size: 14px !important;
            border-left: 3px solid transparent;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            text-decoration: none;
            background: transparent;
        }

        .ch-sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.08) !important;
            color: #fff !important;
            text-decoration: none;
            transform: translateX(2px);
        }

        .ch-sidebar .nav-link.active,
        .ch-sidebar .nav-item.active > .nav-link {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
            border-left-color: #fff !important;
            font-weight: 600;
        }

        .ch-sidebar .menu-icon {
            width: 20px;
            margin-right: 12px !important;
            font-size: 16px !important;
            color: inherit;
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .ch-sidebar .nav-link:hover .menu-icon {
            transform: scale(1.1);
        }

        .ch-sidebar .menu-title {
            flex: 1;
            color: inherit;
            font-size: 14px;
        }

        .ch-sidebar .menu-arrow {
            margin-left: auto;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 18px;
            color: rgba(255, 255, 255, 0.8) !important;
        }

        .ch-sidebar .nav-link[aria-expanded="true"] .menu-arrow {
            transform: rotate(90deg);
            color: rgba(255, 255, 255, 0.95) !important;
        }

        .ch-sidebar .nav-link:hover .menu-arrow {
            color: #fff !important;
        }

        /* Submenu - Smooth expansion animation */
        .ch-sidebar .sub-menu {
            background: rgba(0, 0, 0, 0.4);
            padding: 8px 0;
            margin: 0;
            border-left: 2px solid rgba(255, 255, 255, 0.1);
            margin-left: 20px;
        }

        .ch-sidebar .sub-menu .nav-item {
            margin: 0;
        }
rgba(255, 255, 255, 0.7) !important;
            font-size: 11px;
            font-weight: 400;
        }

        /* Override any conflicting styles */
        .ch-sidebar .nav-item:not(.active):not(.nav-profile):not(.nav-brand) {
            background: transparent !important;
        }

        .ch-sidebar .nav-item:not(.active) .nav-link:not(:hover) {
            background: transparent !important;
        }

        /* Enhance collapse animation */
        .ch-sidebar .collapse {
            transition: height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .ch-sidebar .collapsing {
            transition: height 0.4s cubic-bezier(0.4, 0, 0.2, 1)
                transform: translateX(0);
            }
        }

        .ch-sidebar .sub-menu .nav-link {
            padding: 10px 20px 10px 40px !important;
            font-size: 13px !important;
            border-left: none;
            color: rgba(255, 255, 255, 0.85) !important;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .ch-sidebar .sub-menu .nav-link:hover {
            background: rgba(255, 255, 255, 0.12) !important;
            color: #fff !important;
            padding-left: 42px !important;
        }

        .ch-sidebar .sub-menu .nav-link.active {
            color: #fff !important;
            background: rgba(255, 255, 255, 0.15) !important;
            position: relative;
            font-weight: 500;
        }

        .ch-sidebar .sub-menu .nav-link.active::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: #fff;
            border-radius: 0 3px 3px 0;
        }

        /* Bottom user profile section */
        .ch-sidebar .nav-profile {
            padding: 15px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
            margin-top: auto;
            margin-bottom: 0 !important;
        }

        .ch-sidebar .nav-profile img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        .ch-sidebar .nav-profile-text {
            color: white !important;
            font-weight: 600;
            font-size: 13px !important;
        }

        .ch-sidebar .nav-profile span {
            color: #8b9db8 !important;
            font-size: 11px;
            font-weight: 400;
        }

        /* Override any conflicting styles */
        .ch-sidebar .nav-item:not(.active):not(.nav-profile):not(.nav-brand) {
            background: transparent !important;
        }

        .ch-sidebar .nav-item:not(.active) .nav-link:not(:hover) {
            background: transparent !important;
        }
        /* Paystack Navbar Styles - White background, minimal */
        .ch-navbar {
            background: #fff !important;
            border-bottom: 1px solid #e0e0e0;
            padding: 0;
            box-shadow: none;
            z-index: 999;
            position: fixed;
            left: 250px;
            width: calc(100% - 250px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Override any navbar color classes from settings.js */
        .ch-navbar.navbar-primary,
        .ch-navbar.navbar-success,
        .ch-navbar.navbar-warning,
        .ch-navbar.navbar-danger,
        .ch-navbar.navbar-info,
        .ch-navbar.navbar-dark,
        .ch-navbar.navbar-light {
            background: #fff !important;
        }

        .ch-navbar .navbar-menu-wrapper {
            padding: 12px 30px;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Handle sidebar collapse state */
        .sidebar-icon-only .ch-navbar {
            left: 70px;
            width: calc(100% - 70px);
        }

        /* Fresh Hamburger Button Styles */
        .sidebar-toggle-btn,
        .sidebar-mobile-toggle-btn {
            border: none;
            background: transparent;
            color: #666;
            border-radius: 4px;
            padding: 8px 12px;
            transition: all 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-toggle-btn:hover,
        .sidebar-mobile-toggle-btn:hover {
            background: #f8f9fa;
            color: #333;
        }

        .sidebar-toggle-btn i,
        .sidebar-mobile-toggle-btn i {
            font-size: 20px;
        }

        .ch-navbar .nav-link {
            color: #666;
            font-weight: 500;
            padding: 8px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .ch-navbar .nav-link:hover {
            background: #f8f9fa;
            color: #333;
        }

        .ch-navbar .nav-link i {
            font-size: 18px;
        }

        .ch-navbar .dropdown-toggle::after {
            display: none;
        }

        .ch-navbar .dropdown-menu {
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            border-radius: 4px;
            padding: 4px 0;
            background: white;
            margin-top: 8px;
        }

        .ch-navbar .dropdown-item {
            padding: 8px 16px;
            font-size: 14px;
            color: #333;
            transition: all 0.2s;
        }

        .ch-navbar .dropdown-item:hover {
            background: #f8f9fa;
            color: #0c1e35;
        }

        .ch-navbar .dropdown-toggle {
            color: #666 !important;
        }

        .ch-navbar .dropdown-toggle:hover {
            color: #333 !important;
        }

        .ch-navbar .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .ch-navbar .test-mode-badge {
            background-color: #ff6b6b;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .ch-navbar .notification-badge {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
            position: absolute;
            top: -5px;
            right: -8px;
        }

        /* Page Body Wrapper */
        .page-body-wrapper {
            margin-left: 250px;
            width: calc(100% - 250px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding-top: 0;
        }

        .sidebar-icon-only .page-body-wrapper {
            margin-left: 70px;
            width: calc(100% - 70px);
        }

        .main-panel {
            width: 100%;
            margin: 0;
            padding: 0;
            overflow-y: auto;
            height: 100vh;
        }

        /* Content Area - Scrollable */
        .content-wrapper {
            padding: 80px 30px 30px 30px;
            min-height: calc(100vh - 60px);
            background: #f5f5f5;
        }

        /* Container for content */
        .content-wrapper .container,
        .content-wrapper .container-fluid {
            padding-left: 15px;
            padding-right: 15px;
        }

        /* Add padding only to content-header for breadcrumbs */
        .content-wrapper > .content-header {
            padding-left: 0;
            padding-right: 0;
        }

        /* Page Header */
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        /* Cards - Paystack style */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            background: white;
            margin-bottom: 24px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.08);
        }

        .card-body {
            padding: 2rem;
        }

        /* Modern Form Inputs */
        .form-control {
            height: 48px;
            border-radius: 8px;
            border: 1px solid #E2E8F0;
            background-color: #F9FAFB;
            padding: 10px 16px;
            font-size: 14px;
            transition: all 0.2s ease;
            color: #1F2937;
        }

        .form-control:focus {
            background-color: #fff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px var(--primary-light);
            outline: none;
        }

        /* Soft Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 11px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .badge-success {
            background-color: #DCFCE7;
            color: #166534;
        }

        .badge-warning {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .badge-danger {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .badge-info {
            background-color: #DBEAFE;
            color: #1E40AF;
        }

        .badge-primary {
            background-color: var(--primary-light);
            color: var(--primary-color);
        }

        /* Footer */
        .footer {
            background: #f5f5f5;
            padding: 20px 30px;
            border-top: 1px solid #e0e0e0;
            margin: 0;
            position: fixed;
            bottom: 0;
            left: 250px;
            width: calc(100% - 250px);
            z-index: 998;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Footer adjusts when sidebar is minimized */
        .sidebar-icon-only .footer {
            left: 70px;
            width: calc(100% - 70px);
        }

        .footer .text-muted {
            font-size: 13px;
            color: #666 !important;
        }

        body.dark-mode .footer {
            background: #1a1a1a;
            border-top-color: #3d3d3d;
        }

        body.dark-mode .footer .text-muted {
            color: #a0a0a0 !important;
        }

        /* Add bottom padding to content to prevent overlap with fixed footer */
        .content-wrapper {
            padding-bottom: 80px !important;
        }

        /* Remove container-fluid padding to eliminate gaps */
        .container-fluid {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        /* Add padding back to specific containers that need it */
        .content-header .container-fluid {
            padding-left: 15px !important;
            padding-right: 15px !important;
        }

        /* Breadcrumb - Paystack style */
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0;
            font-size: 14px;
        }

        .breadcrumb-item {
            color: #666;
        }

        .breadcrumb-item.active {
            color: #333;
        }

        .breadcrumb-item a {
            color: #666;
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: #0c1e35;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: '/';
            color: #999;
        }

        /* Modern DataTables Styling */
        .dataTables_wrapper {
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        /* Table Header */
        table.dataTable thead th,
        .table thead th {
            background-color: #F9FAFB;
            color: #6B7280;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #E5E7EB !important;
            padding: 16px 24px !important;
        }

        /* Table Body */
        table.dataTable tbody td,
        .table tbody td {
            padding: 16px 24px !important;
            color: #374151;
            font-size: 14px;
            border-bottom: 1px solid #F3F4F6 !important;
            vertical-align: middle !important;
        }

        /* Row Hover */
        table.dataTable tbody tr:hover,
        .table tbody tr:hover {
            background-color: #F9FAFB !important;
        }

        /* Remove default borders */
        .table-bordered {
            border: none !important;
        }

        .table-bordered td,
        .table-bordered th {
            border: none !important;
            border-bottom: 1px solid #F3F4F6 !important;
        }

        /* Search Input */
        .dataTables_filter input {
            height: 42px;
            border-radius: 8px;
            border: 1px solid #E2E8F0;
            padding: 8px 16px;
            font-size: 14px;
            margin-left: 10px;
            outline: none;
            transition: all 0.2s;
        }

        .dataTables_filter input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        /* Buttons (Export, etc) */
        .dt-buttons .dt-button {
            background: #fff !important;
            border: 1px solid #E2E8F0 !important;
            border-radius: 6px !important;
            color: #4B5563 !important;
            font-size: 13px !important;
            padding: 8px 16px !important;
            margin-right: 8px !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
            transition: all 0.2s !important;
            background-image: none !important;
        }

        .dt-buttons .dt-button:hover {
            background: #F9FAFB !important;
            border-color: #D1D5DB !important;
            color: #111827 !important;
        }

        /* Pagination */
        .dataTables_paginate {
            padding-top: 20px !important;
        }

        .dataTables_paginate .paginate_button {
            border: 1px solid #E2E8F0 !important;
            background: #fff !important;
            color: #4B5563 !important;
            border-radius: 6px !important;
            margin: 0 4px !important;
            padding: 6px 12px !important;
            font-size: 13px !important;
            transition: all 0.2s !important;
        }

        .dataTables_paginate .paginate_button:hover {
            background: #F9FAFB !important;
            color: #111827 !important;
            border-color: #D1D5DB !important;
        }

        .dataTables_paginate .paginate_button.current,
        .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary-color) !important;
            color: #fff !important;
            border-color: var(--primary-color) !important;
        }

        .dataTables_paginate .paginate_button.disabled,
        .dataTables_paginate .paginate_button.disabled:hover,
        .dataTables_paginate .paginate_button.disabled:active {
            color: #9CA3AF !important;
            border-color: #E5E7EB !important;
            background: #F9FAFB !important;
            cursor: not-allowed !important;
        }

        /* Action Buttons in Table */
        .table .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            font-weight: 500;
        }

        /* Dark Mode Styles */
        body.dark-mode {
            background: #1a1a1a;
            color: #e0e0e0;
        }

        body.dark-mode .content-wrapper {
            background: #1a1a1a;
        }

        body.dark-mode .profile-card,
        body.dark-mode .card {
            background: #2d2d2d;
            border-color: #3d3d3d;
            color: #e0e0e0;
        }

        body.dark-mode .info-item {
            background: #252525;
            border-left-color: var(--primary-color);
        }

        body.dark-mode .info-label,
        body.dark-mode label,
        body.dark-mode .form-label,
        body.dark-mode .control-label {
            color: #c0c0c0 !important;
        }

        body.dark-mode .info-value,
        body.dark-mode .profile-card-header,
        body.dark-mode h1, body.dark-mode h2, body.dark-mode h3,
        body.dark-mode h4, body.dark-mode h5, body.dark-mode h6 {
            color: #e0e0e0;
        }

        body.dark-mode .form-control-modern,
        body.dark-mode .form-control,
        body.dark-mode input[type="text"],
        body.dark-mode input[type="email"],
        body.dark-mode input[type="password"],
        body.dark-mode input[type="date"],
        body.dark-mode input[type="tel"],
        body.dark-mode select,
        body.dark-mode textarea {
            background: #2d2d2d !important;
            border-color: #4d4d4d !important;
            color: #e0e0e0 !important;
        }

        body.dark-mode .form-control::placeholder,
        body.dark-mode input::placeholder,
        body.dark-mode textarea::placeholder {
            color: #888 !important;
        }

        body.dark-mode .form-label,
        body.dark-mode label {
            color: #c0c0c0 !important;
        }

        body.dark-mode .input-group-text {
            background: #252525 !important;
            border-color: #4d4d4d !important;
            color: #c0c0c0 !important;
        }

        body.dark-mode .custom-file-label {
            background: #2d2d2d !important;
            border-color: #4d4d4d !important;
            color: #c0c0c0 !important;
        }

        body.dark-mode .custom-file-label::after {
            background: #252525 !important;
            color: #c0c0c0 !important;
        }

        body.dark-mode .text-muted,
        body.dark-mode small {
            color: #a0a0a0 !important;
        }

        /* Dark Mode: Card Headers */
        body.dark-mode .card-header.bg-white {
            background: #252525 !important;
            color: #e0e0e0 !important;
        }

        body.dark-mode .card-header h5,
        body.dark-mode .card-header h4 {
            color: #e0e0e0 !important;
        }

        body.dark-mode .card-header i {
            color: var(--primary-color) !important;
        }

        /* Dark Mode: Better contrast for config page */
        body.dark-mode .alert-success {
            background: rgba(40, 167, 69, 0.2) !important;
            border-color: #28a745 !important;
            color: #4ade80 !important;
        }

        body.dark-mode .btn-primary {
            background: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            color: #fff !important;
        }

        body.dark-mode .btn-secondary {
            background: #4d4d4d !important;
            border-color: #5d5d5d !important;
            color: #e0e0e0 !important;
        }

        body.dark-mode .nav-tabs-modern {
            border-bottom-color: #3d3d3d;
        }

        body.dark-mode .nav-tabs-modern .nav-link {
            color: #a0a0a0;
        }

        body.dark-mode .nav-tabs-modern .nav-link.active {
            color: var(--primary-color);
        }

        /* Dark Mode: Standard Bootstrap Tabs */
        body.dark-mode .nav-tabs {
            border-bottom-color: #3d3d3d !important;
        }

        body.dark-mode .nav-tabs .nav-link {
            color: #b0b0b0 !important;
            border-color: transparent !important;
        }

        body.dark-mode .nav-tabs .nav-link:hover {
            color: #e0e0e0 !important;
            border-color: #3d3d3d #3d3d3d transparent !important;
        }

        body.dark-mode .nav-tabs .nav-link.active {
            color: #ffffff !important;
            background-color: #2d2d2d !important;
            border-color: #3d3d3d #3d3d3d transparent !important;
            font-weight: 600;
        }

        body.dark-mode .tab-content {
            background: #1e1e1e !important;
            color: #e0e0e0 !important;
        }

        body.dark-mode .upload-area {
            background: #252525;
            border-color: #3d3d3d;
        }

        /* Dark Mode: DataTables & Table Styling */
        body.dark-mode table,
        body.dark-mode .table {
            color: #e0e0e0 !important;
            border-color: #3d3d3d !important;
        }

        body.dark-mode table thead th,
        body.dark-mode .table thead th,
        body.dark-mode table.dataTable thead th {
            background: #2d2d2d !important;
            color: #e0e0e0 !important;
            border-color: #4d4d4d !important;
        }

        body.dark-mode table tbody td,
        body.dark-mode .table tbody td,
        body.dark-mode table.dataTable tbody td {
            background: #252525 !important;
            color: #e0e0e0 !important;
            border-color: #3d3d3d !important;
        }

        body.dark-mode table tbody tr:hover,
        body.dark-mode .table tbody tr:hover,
        body.dark-mode table.dataTable tbody tr:hover {
            background: #2d2d2d !important;
        }

        /* Dark Mode: DataTables Controls */
        body.dark-mode .dataTables_wrapper .dataTables_length,
        body.dark-mode .dataTables_wrapper .dataTables_filter,
        body.dark-mode .dataTables_wrapper .dataTables_info,
        body.dark-mode .dataTables_wrapper .dataTables_paginate {
            color: #c0c0c0 !important;
        }

        body.dark-mode .dataTables_wrapper .dataTables_filter input,
        body.dark-mode .dataTables_wrapper .dataTables_length select {
            background: #2d2d2d !important;
            border-color: #4d4d4d !important;
            color: #e0e0e0 !important;
        }

        body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button {
            background: #2d2d2d !important;
            color: #c0c0c0 !important;
            border-color: #4d4d4d !important;
        }

        body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #3d3d3d !important;
            color: #e0e0e0 !important;
        }

        body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary-color) !important;
            color: white !important;
            border-color: var(--primary-color) !important;
        }

        /* Dark Mode: Buttons & Toolbar */
        body.dark-mode .dt-buttons .dt-button,
        body.dark-mode .btn-secondary,
        body.dark-mode .btn-default,
        body.dark-mode .btn-light {
            background: #3d3d3d !important;
            color: #c0c0c0 !important;
            border-color: #4d4d4d !important;
        }

        body.dark-mode .dt-buttons .dt-button:hover,
        body.dark-mode .btn-secondary:hover,
        body.dark-mode .btn-default:hover,
        body.dark-mode .btn-light:hover {
            background: #4d4d4d !important;
            color: #e0e0e0 !important;
        }

        /* Dark Mode: Breadcrumb */
        body.dark-mode .breadcrumb,
        body.dark-mode .breadcrumb-item,
        body.dark-mode .breadcrumb-item a {
            color: #c0c0c0 !important;
        }

        body.dark-mode .breadcrumb-item.active {
            color: #e0e0e0 !important;
            font-weight: 600;
        }

        body.dark-mode .breadcrumb-item + .breadcrumb-item::before {
            color: #888 !important;
        }

        /* Dark Mode: Sorting Icons */
        body.dark-mode table.dataTable thead .sorting:before,
        body.dark-mode table.dataTable thead .sorting_asc:before,
        body.dark-mode table.dataTable thead .sorting_desc:before,
        body.dark-mode table.dataTable thead .sorting:after,
        body.dark-mode table.dataTable thead .sorting_asc:after,
        body.dark-mode table.dataTable thead .sorting_desc:after {
            color: #888 !important;
            opacity: 0.5;
        }

        /* Dark Mode: Select2 Dropdown */
        body.dark-mode .select2-container--default .select2-selection--single,
        body.dark-mode .select2-container--default .select2-selection--multiple {
            background: #2d2d2d !important;
            border-color: #4d4d4d !important;
            color: #e0e0e0 !important;
        }

        body.dark-mode .select2-dropdown {
            background: #2d2d2d !important;
            border-color: #4d4d4d !important;
        }

        body.dark-mode .select2-results__option {
            color: #e0e0e0 !important;
        }

        body.dark-mode .select2-results__option--highlighted {
            background: var(--primary-color) !important;
            color: white !important;
        }

        /* Dark Mode: Alerts */
        body.dark-mode .alert {
            background: #2d2d2d !important;
            border-color: #4d4d4d !important;
            color: #e0e0e0 !important;
        }

        body.dark-mode .alert-success {
            background: rgba(40, 167, 69, 0.2) !important;
            border-color: #28a745 !important;
            color: #4ade80 !important;
        }

        body.dark-mode .alert-danger {
            background: rgba(220, 53, 69, 0.2) !important;
            border-color: #dc3545 !important;
            color: #f87171 !important;
        }

        body.dark-mode .alert-warning {
            background: rgba(255, 193, 7, 0.2) !important;
            border-color: #ffc107 !important;
            color: #fbbf24 !important;
        }

        body.dark-mode .alert-info {
            background: rgba(23, 162, 184, 0.2) !important;
            border-color: #17a2b8 !important;
            color: #60a5fa !important;
        }

        /* Dark Mode: Modal */
        body.dark-mode .modal-content {
            background: #2d2d2d !important;
            color: #e0e0e0 !important;
        }

        body.dark-mode .modal-header {
            border-bottom-color: #3d3d3d !important;
        }

        body.dark-mode .modal-footer {
            border-top-color: #3d3d3d !important;
        }

        body.dark-mode .close {
            color: #e0e0e0 !important;
            text-shadow: none !important;
        }

        /* Fix for modals inside overflow containers - ensure proper stacking */
        .modal {
            position: fixed !important;
            z-index: 1055 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
        }
        .modal.show .modal-dialog {
            z-index: 1056 !important;
        }

        /* Mobile responsive */
        @media (max-width: 991px) {
            .ch-navbar {
                left: 0;
                width: 100%;
            }

            .page-body-wrapper {
                margin-left: 0;
                width: 100%;
            }

            .ch-sidebar {
                left: -260px;
            }

            .ch-sidebar.active {
                left: 0;
            }

            .footer {
                left: 0;
                width: 100%;
            }

            /* Hide desktop toggle button on mobile */
            .sidebar-toggle-btn {
                display: none !important;
            }

            /* FORCE FULL SIDEBAR ON MOBILE even if sidebar-icon-only class is present */
            .sidebar-icon-only .ch-sidebar {
                width: 250px !important;
                left: -260px; /* Keep hidden by default */
            }

            .sidebar-icon-only .ch-sidebar.active {
                left: 0; /* Show when active */
            }

            .sidebar-icon-only .ch-navbar {
                left: 0 !important;
                width: 100% !important;
            }

            .sidebar-icon-only .page-body-wrapper {
                margin-left: 0 !important;
                width: 100% !important;
            }

            .sidebar-icon-only .footer {
                left: 0 !important;
                width: 100% !important;
            }

            /* Restore text visibility */
            .sidebar-icon-only .ch-sidebar .nav-brand h6,
            .sidebar-icon-only .ch-sidebar .nav-brand p,
            .sidebar-icon-only .ch-sidebar .nav-brand-title,
            .sidebar-icon-only .ch-sidebar .nav-brand-version,
            .sidebar-icon-only .ch-sidebar .menu-title,
            .sidebar-icon-only .ch-sidebar .menu-arrow,
            .sidebar-icon-only .ch-sidebar .nav-item-head {
                opacity: 1 !important;
                visibility: visible !important;
                width: auto !important;
            }

            /* Restore nav link layout */
            .sidebar-icon-only .ch-sidebar .nav-link {
                justify-content: flex-start !important;
                width: 100% !important;
                padding: 10px 20px !important;
            }

            .sidebar-icon-only .ch-sidebar .nav-link:hover::after,
            .sidebar-icon-only .ch-sidebar .nav-link:hover::before {
                display: none !important; /* Remove tooltips on mobile */
            }

            /* Restore profile section */
            .sidebar-icon-only .ch-sidebar .nav-profile {
                padding: 15px 20px !important;
                justify-content: flex-start !important;
            }

            .sidebar-icon-only .ch-sidebar .nav-profile a {
                justify-content: flex-start !important;
                width: auto !important;
            }

            .sidebar-icon-only .ch-sidebar .nav-profile .ml-3,
            .sidebar-icon-only .ch-sidebar .nav-profile .mdi-logout {
                display: block !important;
            }

            .sidebar-icon-only .ch-sidebar .nav-profile img {
                margin: 0 !important;
            }

            /* Restore submenus */
            .sidebar-icon-only .ch-sidebar .sub-menu {
                display: block !important; /* Let the parent .collapse div control visibility */
            }

            .sidebar-icon-only .ch-sidebar .collapse {
                display: none;
            }

            .sidebar-icon-only .ch-sidebar .collapse.show {
                display: block;
            }

            .sidebar-icon-only .ch-sidebar .collapsing {
                display: block;
                height: 0;
                overflow: hidden;
                transition: height 0.35s ease;
            }
        }

        /* Hide mobile toggle button on desktop */
        @media (min-width: 992px) {
            .sidebar-mobile-toggle-btn {
                display: none !important;
            }
        }

        /* Breadcrumb */
        .content-header {
            background: transparent;
            margin-bottom: 1.5rem;
        }

        .content-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a1a1a;
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0;
        }

        .breadcrumb-item {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .breadcrumb-item.active {
            color: var(--primary-color);
        }

        .breadcrumb-item a {
            color: #6c757d;
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: var(--primary-color);
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: #1a1a1a;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Responsive Sidebar - Already handled above */

        /* Remove old cloud animation */
        .cloud {
            display: none;
        }
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

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
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

        /* Modern Card Styles - Complete Standalone Implementation */
        .card-modern {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background: #fff;
            background-clip: border-box;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .card-modern:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .card-modern > .card-header {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 0.875rem 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0;
        }

        .card-modern > .card-header:first-child {
            border-radius: 11px 11px 0 0;
        }

        .card-modern > .card-body {
            flex: 1 1 auto;
            padding: 1.25rem;
            color: #374151;
        }

        .card-modern > .card-footer {
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 0.75rem 1.25rem;
        }

        .card-modern > .card-footer:last-child {
            border-radius: 0 0 11px 11px;
        }

        /* Table inside card-modern */
        .card-modern .table {
            margin-bottom: 0;
        }

        .card-modern .table-responsive {
            margin: 0;
            border-radius: 0;
        }

        /* Ensure cards don't go fullscreen on mobile */
        @media (max-width: 768px) {
            .card-modern {
                margin-left: 0;
                margin-right: 0;
                border-radius: 8px;
                width: 100%;
                max-width: 100%;
            }

            .card-modern > .card-header:first-child {
                border-radius: 7px 7px 0 0;
            }

            .card-modern > .card-footer:last-child {
                border-radius: 0 0 7px 7px;
            }

            .card-modern > .card-body {
                padding: 1rem;
            }

            .card-modern > .card-header {
                padding: 0.75rem 1rem;
            }
        }

        /* Stat Card Modern Variant */
        .stat-card-modern {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 1.25rem;
            transition: all 0.2s ease;
        }

        .stat-card-modern:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .stat-card-modern .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-card-modern .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            line-height: 1.2;
        }

        .stat-card-modern .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        @media (max-width: 768px) {
            .stat-card-modern {
                padding: 1rem;
            }

            .stat-card-modern .stat-value {
                font-size: 1.5rem;
            }

            .stat-card-modern .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
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
            // Toggle the floating widget instead of opening a new window
            toggleChatWindow();
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

        /* Prevent cards inside dataTables from becoming fullscreen on mobile */
        @media (max-width: 767.98px) {
            .dataTables_wrapper .card,
            .table-responsive .card,
            .clinical-tab-body .card,
            table .card {
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                min-height: 0 !important;
                position: relative !important;
                margin-bottom: 1rem !important;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
                transform: none !important;
                left: auto !important;
                top: auto !important;
            }

            .dataTables_wrapper .card .card-body,
            .table-responsive .card .card-body,
            table .card .card-body {
                padding: 1rem !important;
            }

            /* Ensure the text inside doesn't overflow */
            .dataTables_wrapper .card *,
            .table-responsive .card *,
            table .card * {
                max-width: 100%;
                word-wrap: break-word; /* Deprecated but still useful fallback */
                overflow-wrap: break-word;
            }
        }
    </style>

    <!-- Chat Styles -->
    <link rel="stylesheet" href="{{ asset('css/chat-styles.css') }}">

    <!-- Page-specific styles -->
    @yield('style')
    @stack('styles')
</head>

<body id='app'>
    <div class="container-scroller">
        <!-- partial:partials/_sidebar.html -->
        @include('admin.partials.sidebar')
        <!-- partial -->
        <div class="container-fluid page-body-wrapper">
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
    <script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/js/select2.min.js') }}"></script>
    <script>
        $('.select2').select2();
    </script>

    <!-- Chat Core JS - Must load before page scripts -->
    <script src="{{ asset('js/chat-core.js') }}?v={{ time() }}"></script>

    {{-- <script src="admin/assets/vendors/chart.js/Chart.min.js"></script> --}}
    @yield('scripts')
    @stack('scripts')
    <!-- endinject -->

    <!-- Global Modal Fix: Move modals to body to avoid z-index/overflow issues -->
    <script>
        document.addEventListener('show.bs.modal', function(event) {
            const modal = event.target;
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });
    </script>

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
    {{-- <script src="{{ asset('admin/assets/js/todolist.js') }}"></script> --}}
    <!-- endinject -->
    <!-- Custom js for this page -->
    {{-- <script src="{{ asset('admin/assets/js/dashboard.js') }}"></script> --}}

    <!-- End custom js for this page -->
    @if (appsettings('enable_twakto', 0) == 1)
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

    <!-- Dark Mode Toggle Script -->
    <script>
        // Check for saved dark mode preference or default to light mode
        const darkModeToggle = document.getElementById('darkModeToggle');
        const darkModeIcon = document.getElementById('darkModeIcon');
        const currentMode = localStorage.getItem('darkMode') || 'light';

        // Apply saved mode on page load
        if (currentMode === 'dark') {
            document.body.classList.add('dark-mode');
            darkModeIcon.classList.remove('mdi-weather-night');
            darkModeIcon.classList.add('mdi-weather-sunny');
        }

        // Toggle dark mode
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', function() {
                document.body.classList.toggle('dark-mode');

                if (document.body.classList.contains('dark-mode')) {
                    localStorage.setItem('darkMode', 'dark');
                    darkModeIcon.classList.remove('mdi-weather-night');
                    darkModeIcon.classList.add('mdi-weather-sunny');
                } else {
                    localStorage.setItem('darkMode', 'light');
                    darkModeIcon.classList.remove('mdi-weather-sunny');
                    darkModeIcon.classList.add('mdi-weather-night');
                }
            });
        }
    </script>

    <!-- Fresh Hamburger Menu Implementation -->
    <script>
        $(document).ready(function() {
            // Add data-title attributes to all nav links for tooltips
            $('.ch-sidebar .nav-link').each(function() {
                var title = $(this).find('.menu-title').text().trim();
                if (title) {
                    $(this).attr('data-title', title);
                }
            });

            // Fix: Remove incorrectly added active classes by misc.js
            $('.sidebar .nav-item').removeClass('active');
            $('.sidebar .nav-link').removeClass('active');

            // Re-add active classes only from server-side Blade conditions
            var currentPath = window.location.pathname;
            $('.sidebar .nav-link').each(function() {
                var href = $(this).attr('href');
                if (href && href !== '#') {
                    var linkPath = new URL(href, window.location.origin).pathname;
                    if (linkPath === currentPath) {
                        $(this).addClass('active');
                        $(this).closest('.nav-item').addClass('active');
                        if ($(this).parents('.sub-menu').length) {
                            $(this).closest('.collapse').addClass('show');
                        }
                    }
                }
            });

            // DESKTOP: Toggle sidebar minimize (icon-only mode)
            $('.sidebar-toggle-btn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Desktop toggle clicked');
                $('body').toggleClass('sidebar-icon-only');

                // Collapse all open submenus when switching to icon-only mode
                if ($('body').hasClass('sidebar-icon-only')) {
                    $('.ch-sidebar .collapse.show').removeClass('show');
                }
            });

            // MOBILE: Toggle sidebar slide in/out
            $('.sidebar-mobile-toggle-btn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Mobile toggle clicked');
                $('.ch-sidebar').toggleClass('active');
            });

            // Close mobile sidebar when clicking outside
            $(document).on('click', function(e) {
                if ($(window).width() < 992) {
                    if (!$(e.target).closest('.ch-sidebar, .sidebar-mobile-toggle-btn').length) {
                        $('.ch-sidebar').removeClass('active');
                    }
                }
            });

            // Prevent clicks inside sidebar from closing it
            $('.ch-sidebar').on('click', function(e) {
                e.stopPropagation();
            });

            // Disable submenu clicks in icon-only mode (Desktop only)
            $('body').on('click', '.sidebar-icon-only .ch-sidebar .nav-link[data-toggle="collapse"]', function(e) {
                // Only prevent default if we are on desktop (width >= 992px)
                if ($(window).width() >= 992) {
                    e.preventDefault();
                    return false;
                }
                // On mobile, allow the click to proceed so submenus can expand
            });
        });
    </script>

    <!-- Toastr JS -->
    <script src="{{ asset('assets/js/toastr.min.js') }}"></script>
    <!-- End Toastr JS -->

    @include('admin.partials.chat-widget')

</body>

</html>
