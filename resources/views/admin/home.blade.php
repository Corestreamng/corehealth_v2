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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function () {
    // Load all charts via Ajax
    function renderChart(endpoint, canvasId, type, label, mapFn) {
        $.get(endpoint, function (data) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            new Chart(ctx, {
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

    renderChart("{{ route('api.chart.clinic.timeline') }}", 'appointmentsOverTime', 'line', 'Appointments Per Day', {
        label: d => d.date,
        value: d => d.total
    });

    renderChart("{{ route('api.chart.clinic.by') }}", 'appointmentsByClinic', 'bar', 'Appointments by Clinic', {
        label: d => d.clinic,
        value: d => d.total
    });

    renderChart("{{ route('api.chart.clinic.services') }}", 'topClinicServices', 'doughnut', 'Top Clinic Services', {
        label: d => d.service,
        value: d => d.total
    });

    renderChart("{{ route('api.chart.clinic.status') }}", 'queueStatusChart', 'pie', 'Queue Status Breakdown', {
        label: d => d.status,
        value: d => d.total
    });
});
</script>

@endsection
