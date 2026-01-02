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
        text-align: center;
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

    .btn-auth-primary {
        display: inline-block;
        padding: 0.875rem 2rem;
        font-size: 1rem;
        font-weight: 600;
        background: {{ $primaryColor }};
        border: none;
        border-radius: 8px;
        color: white;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .btn-auth-primary:hover {
        background: {{ $hoverColor }};
        transform: translateY(-1px);
        box-shadow: 0 4px 12px {{ $hoverShadow }};
        color: white;
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

    .alert-success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
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

    .verify-icon {
        width: 64px;
        height: 64px;
        background: {{ $primaryColor }};
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2rem;
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
            <h1>Verify Your Email</h1>
            <p>We've sent a verification link to your email address. Click the link to verify your account and get started.</p>
        </div>
    </div>

    <div class="auth-right">
        <div class="auth-form-container">
            <div class="auth-form-header">
                <div class="verify-icon">
                    ✉️
                </div>
                <h2>Check Your Email</h2>
                <p>{{ appsettings()->site_abbreviation ?? 'CoreHealth' }}</p>
            </div>

            @if (session('resent'))
                <div class="alert alert-success" role="alert">
                    {{ __('A fresh verification link has been sent to your email address.') }}
                </div>
            @endif

            <div class="text-center mb-4" style="color: #6c757d; line-height: 1.6;">
                {{ __('Before proceeding, please check your email for a verification link.') }}<br>
                {{ __('If you did not receive the email') }}, you can request another one below.
            </div>

            <form method="POST" action="{{ route('verification.resend') }}" class="text-center">
                @csrf
                <button type="submit" class="btn-auth-primary">
                    {{ __('Resend Verification Email') }}
                </button>
            </form>

            <div class="text-center mt-4">
                <a class="auth-link" href="{{ route('login') }}">
                    ← {{ __('Back to Login') }}
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
