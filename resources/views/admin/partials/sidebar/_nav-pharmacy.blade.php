{{-- ========================================
     PHARMACIST SECTION
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|PHARMACIST')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.pharmacy') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('pharmacy.workbench') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('pharmacy.workbench') ? 'active' : '' }}" href="{{ route('pharmacy.workbench') }}" id="sidebar-pharmacist-workbench">
        <i class="mdi mdi-pill menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.pharmacy_workbench') }}</span>
    </a>
</li>
<li class="nav-item {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-pharmacist-products" data-bs-target="#sidebar-pharmacist-products" aria-expanded="{{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'true' : 'false' }}" aria-controls="sidebar-pharmacist-products" id="sidebar-pharmacist-products-toggle">
        <i class="mdi mdi-package-variant menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.product_management') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'show' : '' }}" id="sidebar-pharmacist-products">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('product-category.*') ? 'active' : '' }}" href="{{ route('product-category.index') }}" id="sidebar-pharmacist-product-categories">Categories</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('stores.*') ? 'active' : '' }}" href="{{ route('stores.index') }}" id="sidebar-pharmacist-stores">Stores</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}" id="sidebar-pharmacist-products-list">Products</a>
            </li>
        </ul>
    </div>
</li>
@endhasanyrole
