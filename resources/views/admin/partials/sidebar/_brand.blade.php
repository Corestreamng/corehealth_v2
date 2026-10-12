{{-- Sidebar Brand Section --}}
<li class="nav-item nav-brand">
    <div class="text-center w-100 position-relative">
        <button type="button" class="ch-sidebar-close-btn d-lg-none" id="ch-sidebar-close-btn" aria-label="Close sidebar">
            <i class="mdi mdi-close"></i>
        </button>
        @if(appsettings()->logo)
            <img src="data:image/jpeg;base64,{{ appsettings()->logo }}" alt="logo" class="nav-brand-logo" />
        @else
            <div class="nav-brand-abbr">
                {{ strtoupper(substr(appsettings()->site_abbreviation ?? 'CH', 0, 2)) }}
            </div>
        @endif
        <h6 class="mb-1 nav-brand-title" title="{{ config('app.name', env('APP_NAME', 'Corehealth')) }}">
            {{ config('app.name', env('APP_NAME', 'Corehealth')) }}
        </h6>
        <p class="mb-0 nav-brand-version">v{{ appsettings()->version ?? env('APP_VER', '1.0') }}</p>
    </div>
</li>
