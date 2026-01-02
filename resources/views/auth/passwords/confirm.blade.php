@extends('layouts.app')

@section('content')
@php
    $primaryColor = appsettings()->hos_color ?? '#011b33';
    $hoverColor = adjustBrightness($primaryColor, 30);
    $focusShadow = hexToRgba($primaryColor, 0.1);
    $hoverShadow = hexToRgba($primaryColor, 0.2);

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
    .auth-wrapper {
        min-height: 100vh;
        display: flex;
        background: #ffffff;
    }

    .auth-left {
        flex: 1;
        background: linear-gradient(135deg, {{ $primaryColor }} 0%, {{ $hoverColor }} 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem;
        position: relative;
        overflow: hidden;
    }

    .auth-left::before {
        content: '';
        position: absolute;
        width: 500px;
        height: 500px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        top: -200px;
        right: -200px;
    }

    .auth-left::after {
        content: '';
        position: absolute;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 50%;
        bottom: -100px;
        left: -100px;
    }

    .auth-left-content {
        position: relative;
        z-index: 2;
        color: white;
        max-width: 500px;
    }

    .auth-left-content h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        line-height: 1.2;
    }

    .auth-left-content p {
        font-size: 1.1rem;
        opacity: 0.9;
        line-height: 1.6;
    }

    .auth-right {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem;
        background: #f8f9fa;
    }

    .auth-form-container {
        width: 100%;
        max-width: 450px;
        background: white;
        padding: 3rem;
        border-radius: 12px;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    }

    .auth-form-header {
        margin-bottom: 2rem;
    }

    .auth-form-header h2 {
        font-size: 1.75rem;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 0.5rem;
    }

    .auth-form-header p {
        color: #6c757d;
        margin: 0;
    }

    .form-label {
        font-weight: 500;
        color: #333;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .form-control {
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        border: 1.5px solid #e0e0e0;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        border-color: {{ $primaryColor }};
        box-shadow: 0 0 0 0.2rem {{ $focusShadow }};
    }

    .form-control.is-invalid {
        border-color: #dc3545;
    }

    .btn-auth-primary {
        width: 100%;
        padding: 0.875rem 1rem;
        font-size: 1rem;
        font-weight: 600;
        background: {{ $primaryColor }};
        border: none;
        border-radius: 8px;
        color: white;
        transition: all 0.3s ease;
        margin-top: 1rem;
    }

    .btn-auth-primary:hover {
        background: {{ $hoverColor }};
        transform: translateY(-1px);
        box-shadow: 0 4px 12px {{ $hoverShadow }};
    }

    .btn-auth-primary:active {
        transform: translateY(0);
    }

    .auth-link {
        color: {{ $primaryColor }};
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s ease;
    }

    .auth-link:hover {
        color: {{ $hoverColor }};
        text-decoration: none;
    }

    .brand-logo {
        display: inline-block;
        max-width: 120px;
        max-height: 80px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 0.75rem;
        margin-bottom: 2rem;
    }

    .brand-logo img {
        width: 100%;
        height: auto;
        object-fit: contain;
    }

    .brand-logo .brand-initials {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 80px;
        height: 60px;
        font-size: 1.8rem;
        font-weight: 700;
        color: white;
    }

    @media (max-width: 991px) {
        .auth-left {
            display: none;
        }

        .auth-right {
            flex: 1;
            padding: 2rem 1rem;
        }

        .auth-form-container {
            padding: 2rem 1.5rem;
        }
    }
</style>

<div class="auth-wrapper">
    <div class="auth-left">
        <div class="auth-left-content">
            <div class="brand-logo">
                @if(appsettings()->logo)
                    <img src="data:image/jpeg;base64,{{ appsettings()->logo }}" alt="{{ appsettings()->site_abbreviation ?? 'Logo' }}" />
                @else
                    <span class="brand-initials">{{ substr(appsettings()->site_abbreviation ?? 'CH', 0, 2) }}</span>
                @endif
            </div>
            <h1>Secure Access</h1>
            <p>Your security is important to us. Please confirm your password to continue with this sensitive operation.</p>
        </div>
    </div>

    <div class="auth-right">
        <div class="auth-form-container">
            <div class="auth-form-header">
                <h2>Confirm Password</h2>
                <p>Please confirm your password before continuing</p>
            </div>

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf

                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('Password') }}</label>
                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                           name="password" required autocomplete="current-password"
                           placeholder="Enter your password">

                    @error('password')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-auth-primary">
                    {{ __('Confirm Password') }}
                </button>

                @if (Route::has('password.request'))
                    <div class="text-center mt-4">
                        <a class="auth-link" href="{{ route('password.request') }}">
                            {{ __('Forgot Your Password?') }}
                        </a>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
