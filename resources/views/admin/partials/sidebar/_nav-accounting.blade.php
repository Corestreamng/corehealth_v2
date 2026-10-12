{{-- ========================================
     ACCOUNTANT / AUDIT SECTION
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|super-admin|ACCOUNTS')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.accountant_audit') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('accounting.*', 'inventory.purchase-orders.accounts-payable', 'inventory.expenses.*', 'banks.*') ? 'active' : '' }}">
    <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-accountant" data-bs-target="#sidebar-accountant" aria-expanded="{{ request()->routeIs('accounting.*', 'inventory.purchase-orders.accounts-payable', 'inventory.expenses.*', 'banks.*') ? 'true' : 'false' }}" aria-controls="sidebar-accountant">
        <i class="mdi mdi-calculator menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.accounting') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('accounting.*', 'inventory.purchase-orders.accounts-payable', 'inventory.expenses.*', 'banks.*') ? 'show' : '' }}" id="sidebar-accountant">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.dashboard') ? 'active' : '' }}" href="{{ route('accounting.dashboard') }}">
                    <i class="mdi mdi-view-dashboard-outline"></i> {{ __('sidebar.dashboard') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.journal-entries.*') ? 'active' : '' }}" href="{{ route('accounting.journal-entries.index') }}">
                    <i class="mdi mdi-book-open-page-variant"></i> {{ __('sidebar.journal_entries') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.chart-of-accounts.*') ? 'active' : '' }}" href="{{ route('accounting.chart-of-accounts.index') }}">
                    <i class="mdi mdi-file-tree"></i> {{ __('sidebar.chart_of_accounts') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.reports.*') ? 'active' : '' }}" href="{{ route('accounting.reports.index') }}">
                    <i class="mdi mdi-chart-bar"></i> {{ __('sidebar.reports') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.credit-notes.*') ? 'active' : '' }}" href="{{ route('accounting.credit-notes.index') }}">
                    <i class="mdi mdi-file-document-outline"></i> {{ __('sidebar.credit_notes') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.periods') ? 'active' : '' }}" href="{{ route('accounting.periods') }}">
                    <i class="mdi mdi-calendar-range"></i> {{ __('sidebar.fiscal_periods') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('inventory.purchase-orders.accounts-payable') ? 'active' : '' }}" href="{{ route('inventory.purchase-orders.accounts-payable') }}">
                    <i class="mdi mdi-currency-ngn"></i> {{ __('sidebar.accounts_payable') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('inventory.expenses.*') ? 'active' : '' }}" href="{{ route('inventory.expenses.index') }}">
                    <i class="mdi mdi-cash-minus"></i> {{ __('sidebar.expenses') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('banks.*') ? 'active' : '' }}" href="{{ route('banks.index') }}">
                    <i class="mdi mdi-bank"></i> {{ __('sidebar.banks') }}</a>
            </li>
            {{-- NEW ACCOUNTING MODULES --}}
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.petty-cash.*') ? 'active' : '' }}" href="{{ route('accounting.petty-cash.index') }}">
                    <i class="mdi mdi-cash-register"></i> {{ __('sidebar.petty_cash') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.transfers.*') ? 'active' : '' }}" href="{{ route('accounting.transfers.index') }}">
                    <i class="mdi mdi-bank-transfer"></i> {{ __('sidebar.bank_transfers') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.bank-reconciliation.*') ? 'active' : '' }}" href="{{ route('accounting.bank-reconciliation.index') }}">
                    <i class="mdi mdi-bank-check"></i> {{ __('sidebar.bank_reconciliation') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.patient-deposits.*') ? 'active' : '' }}" href="{{ route('accounting.patient-deposits.index') }}">
                    <i class="mdi mdi-account-cash"></i> {{ __('sidebar.patient_deposits') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.fixed-assets.*') ? 'active' : '' }}" href="{{ route('accounting.fixed-assets.index') }}">
                    <i class="mdi mdi-package-variant-closed"></i> {{ __('sidebar.fixed_assets') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.liabilities.*') ? 'active' : '' }}" href="{{ route('accounting.liabilities.index') }}">
                    <i class="mdi mdi-credit-card-clock"></i> {{ __('sidebar.liabilities') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.leases.*') ? 'active' : '' }}" href="{{ route('accounting.leases.index') }}">
                    <i class="mdi mdi-file-document-edit"></i> {{ __('sidebar.leases_ifrs_16') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.cost-centers.*') ? 'active' : '' }}" href="{{ route('accounting.cost-centers.index') }}">
                    <i class="mdi mdi-sitemap"></i> {{ __('sidebar.cost_centers') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.capex.*') ? 'active' : '' }}" href="{{ route('accounting.capex.index') }}">
                    <i class="mdi mdi-office-building"></i> {{ __('sidebar.capex_projects') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.budgets.*') ? 'active' : '' }}" href="{{ route('accounting.budgets.index') }}">
                    <i class="mdi mdi-calculator-variant"></i> {{ __('sidebar.budgets') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.cash-flow-forecast.*') ? 'active' : '' }}" href="{{ route('accounting.cash-flow-forecast.index') }}">
                    <i class="mdi mdi-chart-timeline-variant"></i> {{ __('sidebar.cash_flow_forecast') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounting.kpi.*') ? 'active' : '' }}" href="{{ route('accounting.kpi.dashboard') }}">
                    <i class="mdi mdi-gauge"></i> {{ __('sidebar.financial_kpis') }}</a>
            </li>
        </ul>
    </div>
</li>
@endhasanyrole
