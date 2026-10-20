{{--
    CoreHealth v2 — Universal Workbench Navbar Ribbon
    Provides standardized header navigation, mobile toggle, quick register,
    emergency intake, contacts, and price list triggers across all workbenches.
--}}
<div class="workspace-navbar" id="workspace-navbar">
    <button type="button" class="btn-back-to-search" id="btn-back-to-search" title="Back to Search">
        <i class="fa fa-arrow-left"></i> <span>Back to Search</span>
    </button>
    <div class="workspace-navbar-actions">
        <button type="button" class="btn-toggle-search" id="btn-toggle-search" title="Toggle Search Sidebar">
            <i class="fa fa-bars"></i> <span>Toggle Search</span>
        </button>

        <button type="button" class="btn btn-primary btn-workbench-new-patient" id="btn-new-patient" onclick="showPatientFormModal('create')" title="Register New Patient">
            <i class="mdi mdi-account-plus"></i> <span>New Patient</span>
        </button>

        @php
            $wbPrefix = $walkinPrefix ?? 'WALK-';
        @endphp
        <button type="button" class="btn btn-outline-primary btn-workbench-walkin" id="btn-register-walkin" 
            onclick="if(typeof openWalkInRegistration==='function'){openWalkInRegistration('{{ $wbPrefix }}');}else if(typeof WorkbenchShell!=='undefined'&&WorkbenchShell.openWalkInModal){WorkbenchShell.openWalkInModal('{{ $wbPrefix }}');}else{showPatientFormModal('create');if(typeof enableWalkInMode==='function'){enableWalkInMode('{{ $wbPrefix }}');}}" 
            title="Register Walk-in Patient">
            <i class="mdi mdi-account-plus-outline"></i> <span>Register Walk-in</span>
        </button>

        @if(!empty($enableEmergencyIntake))
        <button type="button" class="btn btn-danger btn-workbench-emergency" id="btn-emergency-intake" onclick="showEmergencyIntakeModal()" title="Emergency Intake">
            <i class="mdi mdi-ambulance"></i> <span>Emergency Intake</span>
        </button>
        @endif

        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#hospital-contacts-modal" title="Hospital Contacts">
            <i class="mdi mdi-contacts"></i> <span>Contacts</span>
        </button>

        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#workbench-price-list-modal" title="Price List">
            <i class="mdi mdi-currency-usd"></i> <span>Price List</span>
        </button>

        @yield('workbench_navbar_actions')
        @if(!empty($extraActions))
            {!! $extraActions !!}
        @endif
    </div>
</div>
