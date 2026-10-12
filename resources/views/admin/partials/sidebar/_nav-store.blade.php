{{-- ========================================
     STORE / INVENTORY SECTION
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|STORE')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.store_inventory') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('inventory.store-workbench.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('inventory.store-workbench.*') ? 'active' : '' }}" href="{{ route('inventory.store-workbench.index') }}" id="sidebar-store-workbench">
        <i class="mdi mdi-store menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.store_workbench') }}</span>
    </a>
</li>
<li class="nav-item {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-store-products" data-bs-target="#sidebar-store-products" aria-expanded="{{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'true' : 'false' }}" aria-controls="sidebar-store-products" id="sidebar-store-products-toggle">
        <i class="mdi mdi-package-variant menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.product_management') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('product-category.*', 'stores.*', 'products.*') ? 'show' : '' }}" id="sidebar-store-products">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('product-category.*') ? 'active' : '' }}" href="{{ route('product-category.index') }}" id="sidebar-store-product-categories">Categories</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('stores.*') ? 'active' : '' }}" href="{{ route('stores.index') }}" id="sidebar-store-stores">Stores</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}" id="sidebar-store-products-list">Products</a>
            </li>
        </ul>
    </div>
</li>

<li class="nav-item {{ request()->routeIs('inventory.purchase-orders.*', 'inventory.requisitions.*', 'inventory.expenses.*', 'inventory.po-returns.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('inventory.purchase-orders.*', 'inventory.requisitions.*', 'inventory.expenses.*', 'inventory.po-returns.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-store-inventory" data-bs-target="#sidebar-store-inventory" aria-expanded="{{ request()->routeIs('inventory.purchase-orders.*', 'inventory.requisitions.*', 'inventory.expenses.*', 'inventory.po-returns.*') ? 'true' : 'false' }}" aria-controls="sidebar-store-inventory" id="sidebar-store-inventory-toggle">
        <i class="mdi mdi-clipboard-list-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.inventory_operations') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('inventory.purchase-orders.*', 'inventory.requisitions.*', 'inventory.expenses.*', 'inventory.po-returns.*') ? 'show' : '' }}" id="sidebar-store-inventory">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('inventory.purchase-orders.*') ? 'active' : '' }}" href="{{ route('inventory.purchase-orders.index') }}" id="sidebar-store-purchase-orders">
                    <i class="mdi mdi-cart-arrow-down"></i> {{ __('sidebar.purchase_orders') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('inventory.po-returns.*') ? 'active' : '' }}" href="{{ route('inventory.po-returns.index') }}" id="sidebar-store-po-returns">
                    <i class="mdi mdi-keyboard-return"></i> PO Returns</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('inventory.requisitions.*') && !request()->routeIs('inventory.requisitions.my-stock') ? 'active' : '' }}" href="{{ route('inventory.requisitions.index') }}" id="sidebar-store-requisitions">
                    <i class="mdi mdi-swap-horizontal"></i> {{ __('sidebar.requisitions') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('inventory.requisitions.my-stock') ? 'active' : '' }}" href="{{ route('inventory.requisitions.my-stock') }}" id="sidebar-store-my-stock">
                    <i class="mdi mdi-package-variant-closed"></i> My Stock</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('inventory.expenses.*') ? 'active' : '' }}" href="{{ route('inventory.expenses.index') }}" id="sidebar-store-expenses">
                    <i class="mdi mdi-cash-minus"></i> {{ __('sidebar.expenses') }}</a>
            </li>
        </ul>
    </div>
</li>
@can('store-governance.view')
@hasrole('STORE')
<li class="nav-item {{ request()->routeIs('inventory.config.store-governance.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('inventory.config.store-governance.*') ? 'active' : '' }}" href="{{ route('inventory.config.store-governance.index') }}" id="sidebar-store-governance-view">
        <i class="mdi mdi-store-cog menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.store_governance') }}</span>
    </a>
</li>
@endhasrole
@endcan
@endhasanyrole
