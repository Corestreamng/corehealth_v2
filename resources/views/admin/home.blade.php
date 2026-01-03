@extends('admin.layouts.app')
@section('title', 'Hompage')
@section('page_name', 'Home')
@section('content')
    @if (Auth::user()->is_admin == 19)
        @include('admin.dashboards.patient')
    @elseif (Auth::user()->is_admin == 20)
        @include('admin.dashboards.receptionist')
    @elseif (Auth::user()->is_admin == 21)
        @include('admin.dashboards.receptionist')
    @elseif (Auth::user()->is_admin == 22)
        @include('admin.dashboards.doctor')
    @elseif (Auth::user()->is_admin == 23)
        @include('admin.dashboards.receptionist')
    @elseif (Auth::user()->is_admin == 24)
        @include('admin.dashboards.receptionist')

    @else
        @include('admin.dashboards.receptionist')
    @endif
@endsection
@section('scripts')
<!-- Chart.js -->
<script src="{{ asset('plugins/chartjs/Chart.js') }}"></script>
<script>
$(document).ready(function () {
    // Chart instance cache
    const chartInstances = {};

    // Helper to get date range params
    function getDateRangeParams(range, $container) {
        let params = {};
        const today = new Date();
        switch (range) {
            case 'today':
                params.start = params.end = today.toISOString().slice(0, 10);
                break;
            case 'this_month':
                params.start = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0, 10);
                params.end = today.toISOString().slice(0, 10);
                break;
            case 'this_quarter':
                const quarter = Math.floor(today.getMonth() / 3);
                params.start = new Date(today.getFullYear(), quarter * 3, 1).toISOString().slice(0, 10);
                params.end = today.toISOString().slice(0, 10);
                break;
            case 'last_six_months':
                params.start = new Date(today.getFullYear(), today.getMonth() - 5, 1).toISOString().slice(0, 10);
                params.end = today.toISOString().slice(0, 10);
                break;
            case 'this_year':
                params.start = new Date(today.getFullYear(), 0, 1).toISOString().slice(0, 10);
                params.end = today.toISOString().slice(0, 10);
                break;
            case 'custom':
                params.start = $('.custom-date-start').val();
                params.end = $('.custom-date-end').val();
                break;
            case 'all_time':
            default:
                // No params for all time
                break;
        }
        return params;
    }

    function renderChart(endpoint, canvasId, type, label, mapFn, params = {}) {
        // Remove previous chart instance if exists
        if (chartInstances[canvasId]) {
            chartInstances[canvasId].destroy();
        }
        $.get(endpoint, params, function (data) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            chartInstances[canvasId] = new Chart(ctx, {
                type: type,
                data: {
                    labels: data.map(mapFn.label),
                    datasets: [{
                        label: label,
                        data: data.map(mapFn.value),
                        backgroundColor: [
                            '#42A5F5', '#66BB6A', '#FFA726', '#FF7043', '#AB47BC', '#26C6DA'
                        ],
                        borderColor: '#3f51b5',
                        borderWidth: 1,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: true },
                        title: { display: true, text: label }
                    },
                    scales: type === 'line' ? {
                        y: { beginAtZero: true }
                    } : {}
                }
            });
        });
    }

    // Initial chart rendering
    function renderAllCharts() {
        const $select = $('.chart-date-range');
        const range = $select.val();
        const params = getDateRangeParams(range);

        renderChart("{{ route('api.chart.clinic.timeline') }}", 'appointmentsOverTime', 'line', 'Appointments Per Day', {
            label: d => d.date,
            value: d => d.total
        }, params);

        renderChart("{{ route('api.chart.clinic.by') }}", 'appointmentsByClinic', 'bar', 'Appointments by Clinic', {
            label: d => d.clinic,
            value: d => d.total
        }, params);

        renderChart("{{ route('api.chart.clinic.services') }}", 'topClinicServices', 'doughnut', 'Top Clinic Services', {
            label: d => d.service,
            value: d => d.total
        }, params);

        renderChart("{{ route('api.chart.clinic.status') }}", 'queueStatusChart', 'pie', 'Queue Status Breakdown', {
            label: d => d.status,
            value: d => d.total
        }, params);
    }

    // Show/hide custom date pickers
    $('.chart-date-range').on('change', function() {
        const $select = $(this);
        if ($select.val() === 'custom') {
            $('.custom-date-start, .custom-date-end').show();
        } else {
            $('.custom-date-start, .custom-date-end').hide();
        }
        renderAllCharts();
    });

    // Trigger chart update on custom date change
    $('.custom-date-start, .custom-date-end').on('change', function() {
        renderAllCharts();
    });

    // Initial render
    renderAllCharts();
});
</script>

<script>
$(document).ready(function () {
    // Fetch dashboard stats
    $.get("{{ route('dashboard.receptionist-stats') }}", function(data) {
        $('#stat-new-patients').text(data.new_patients);
        $('#stat-returning-patients').text(data.returning_patients);
        $('#stat-admissions').text(data.admissions);
        $('#stat-bookings').text(data.bookings);
        $('#stat-total-patients').text(data.total_patients);
        $('#stat-total-admissions').text(data.total_admissions);
        $('#stat-total-bookings').text(data.total_bookings);
        $('#stat-total-encounters').text(data.total_encounters);
    });
});
</script>


@endsection
