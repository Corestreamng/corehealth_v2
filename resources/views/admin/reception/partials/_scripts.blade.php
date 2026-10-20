@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/select2/select2.full.min.js') }}"></script>
<script src="{{ asset('assets/js/jsbarcode.all.min.js') }}"></script>
<script src="{{ asset('plugins/daterangepicker/moment.js') }}"></script>
<script src="{{ asset('plugins/fullcalendar/fullcalendar.min.js') }}"></script>
@php
    if (!isset($canBillUser)) {
        $user = Auth::user();
        $canBillUser = false;
        if ($user) {
            $billingRoles = ['SUPERADMIN', 'SUPER-ADMIN', 'ADMIN', 'ACCOUNTS', 'ACCOUNTANT', 'BILLER', 'BILLING'];
            $userRoles = $user->roles ? $user->roles->pluck('name')->map(fn($r) => strtoupper(trim($r)))->toArray() : [];
            $canBillUser = !empty(array_intersect($billingRoles, $userRoles))
                || $user->hasAnyRole([
                    'SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'BILLER', 'super-admin',
                    'superadmin', 'admin', 'accounts', 'biller', 'billing', 'accountant',
                    'Biller', 'Billing', 'Accounts', 'Accountant', 'Admin'
                ])
                || ($user->can && ($user->can('see-accounts') || $user->can('bill-consumable-from-store')));
        }
    }
@endphp
<script>
window.WORKBENCH_CONFIG = {
    csrf: '{{ csrf_token() }}',
    baseUrl: '{{ url("/") }}',
    hospitalColor: '{{ appsettings('hos_color') ?? '#0066cc' }}',
    canBill: {{ $canBillUser ? 'true' : 'false' }},
    hmos: @json(\App\Models\Hmo::with('scheme')->orderBy('name')->get()->map(fn($h) => ['id' => $h->id, 'name' => $h->name, 'scheme_name' => $h->scheme->name ?? 'Other'])),
    routes: {
        'reception.patient.quick-register': '{{ route("reception.patient.quick-register") }}',
        'reception.clinics': '{{ route("reception.clinics") }}',
        'reception.hmos': '{{ route("reception.hmos") }}',
        'reception.services.consultation': '{{ route("reception.services.consultation") }}',
        'reception.services.lab': '{{ route("reception.services.lab") }}',
        'reception.services.imaging': '{{ route("reception.services.imaging") }}',
        'reception.products': '{{ route("reception.products") }}',
        'reception.tariff-preview': '{{ route("reception.tariff-preview") }}',
        'reception.book-consultation': '{{ route("reception.book-consultation") }}',
        'reception.book-walkin': '{{ route("reception.book-walkin") }}',
        'reception.queue-counts': '{{ route("reception.queue-counts") }}',
        'referrals.pending': '{{ route("referrals.pending") }}',
        'referrals.pending-count': '{{ route("referrals.pending-count") }}',
        'referrals.book': '{{ route("referrals.book", ["referral" => "__RID__"]) }}',
        'referrals.refer-out': '{{ route("referrals.refer-out", ["referral" => "__RID__"]) }}',
        'referrals.cancel': '{{ route("referrals.cancel", ["referral" => "__RID__"]) }}',
        'referrals.decline': '{{ route("referrals.decline", ["referral" => "__RID__"]) }}',
        'referrals.detail': '{{ route("referrals.detail", ["referral" => "__RID__"]) }}',
        'appointments.check-in': '{{ route("appointments.check-in", ["appointment" => "__AID__"]) }}',
        'appointments.cancel': '{{ route("appointments.cancel", ["appointment" => "__AID__"]) }}',
        'appointments.no-show': '{{ route("appointments.no-show", ["appointment" => "__AID__"]) }}',
        'appointments.reschedule': '{{ route("appointments.reschedule", ["appointment" => "__AID__"]) }}',
        'appointments.reassign': '{{ route("appointments.reassign", ["appointment" => "__AID__"]) }}',
        'appointments.chain': '{{ route("appointments.chain", ["appointment" => "__AID__"]) }}',
        'appointments.slots': '{{ route("appointments.available-slots") }}'
    }
};
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/billing-shared.js') }}"></script>
@include('admin.partials.patient_search_js', ['search_context' => 'reception'])
<script src="{{ asset('js/request-details.js') }}"></script>
<script src="{{ versioned_asset('js/reception-core.js') }}"></script>
<script src="{{ versioned_asset('js/reception-queue.js') }}"></script>
<script src="{{ versioned_asset('js/reception-registration.js') }}"></script>
{{-- Admission Module JS --}}
@include('admin.partials.admissions-module-js')

@php
    $staffProfile = Auth::user() ? Auth::user()->staff_profile : null;
    $canAccessClinicalReports = Auth::user() && (
        Auth::user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']) ||
        ($staffProfile && ($staffProfile->is_unit_head || $staffProfile->is_dept_head))
    );
@endphp
@if($canAccessClinicalReports)
{{-- Clinical Reports JS --}}
@include('admin.partials.clinical-reports-scripts')
@endif

@include('admin.partials.clinical_alerts_modal')
@include('admin.partials.hospital_contacts_modal')
@include('admin.partials.price_list_modal')
<script src="{{ asset('js/clinical-alerts-shared.js') }}"></script>

@if($canBillUser)
    @include('admin.partials.payment_modal')
    @include('admin.partials.payment_scripts')
@endif
@endsection
