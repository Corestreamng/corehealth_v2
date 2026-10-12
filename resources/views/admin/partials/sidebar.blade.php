{{-- =============================================
     CoreHealth v2 — Modular Sidebar
     Refactored from monolithic 1,487-line file
     into focused partials per role section.
     All role guards preserved character-for-character.
     ============================================= --}}
<nav class="ch-sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">
        {{-- Top Brand Section - Fixed --}}
        @include('admin.partials.sidebar._brand')

        {{-- Search / Filter Bar --}}
        @include('admin.partials.sidebar._search')

        {{-- Scrollable Navigation Area --}}
        <div class="ch-sidebar-nav-scrollable">

            {{-- Global Section (All Roles) --}}
            @include('admin.partials.sidebar._nav-global')

            {{-- Employee Self-Service (ESS) --}}
            @include('admin.partials.sidebar._nav-ess')

            {{-- Receptionist / Health Records --}}
            @include('admin.partials.sidebar._nav-reception')

            {{-- Biller / Accounts --}}
            @include('admin.partials.sidebar._nav-billing')

            {{-- Pharmacy --}}
            @include('admin.partials.sidebar._nav-pharmacy')

            {{-- Store / Inventory --}}
            @include('admin.partials.sidebar._nav-store')

            {{-- Nursing --}}
            @include('admin.partials.sidebar._nav-nursing')

            {{-- Morgue --}}
            @include('admin.partials.sidebar._nav-morgue')

            {{-- Maternity --}}
            @include('admin.partials.sidebar._nav-maternity')

            {{-- Medical Laboratory --}}
            @include('admin.partials.sidebar._nav-lab')

            {{-- Imaging / Radiology --}}
            @include('admin.partials.sidebar._nav-radiology')

            {{-- Doctor --}}
            @include('admin.partials.sidebar._nav-doctor')

            {{-- HMO Executive --}}
            @include('admin.partials.sidebar._nav-hmo')

            {{-- Accountant / Audit --}}
            @include('admin.partials.sidebar._nav-accounting')

            {{-- Human Resources --}}
            @include('admin.partials.sidebar._nav-hr')

            {{-- Internal Audit --}}
            @include('admin.partials.sidebar._nav-audit')

            {{-- Administration (SUPERADMIN/ADMIN) --}}
            @include('admin.partials.sidebar._nav-admin')

        </div>

        {{-- Bottom User Profile Section --}}
        @include('admin.partials.sidebar._user-profile')
    </ul>
</nav>
