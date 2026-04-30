@extends('admin.layouts.app')
@section('title', 'Master Staff Import')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Master Staff Import')

@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection

@section('content')
<div class="container-fluid">
    @include('admin.hr.partials.hr-subnav')

    <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1 font-weight-bold text-dark">
                    <i class="mdi mdi-file-import text-primary"></i> Master Staff Import
                </h2>
                <p class="text-muted mb-0">Import staff data from your master spreadsheet — maps all columns to staff records, qualifications, trainings, next of kin, medical exams, and follow-ups</p>
            </div>
            <a href="{{ route('hr.master-import.template') }}" class="btn btn-outline-primary btn-sm" style="border-radius: 8px;">
                <i class="mdi mdi-download mr-1"></i> Download Template
            </a>
        </div>

        <div class="card-body" style="padding: 2rem;">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="alert alert-info" style="border-radius: 10px; border: none;">
                        <h6 class="font-weight-bold mb-2"><i class="mdi mdi-information mr-1"></i> Import Guidelines</h6>
                        <ul class="mb-0 pl-3" style="font-size: 0.88rem;">
                            <li>Download the template CSV for the expected column headers</li>
                            <li>Staff are matched by <strong>Employee Number</strong> first, then <strong>Email</strong></li>
                            <li>Multiple qualifications or trainings can be separated by semicolons (<code>;</code>)</li>
                            <li>Dates accept most formats: dd/mm/yyyy, yyyy-mm-dd, dd-MMM-yyyy, etc.</li>
                            <li>Gender accepts: M/F, Male/Female</li>
                            <li>Medical Examination: e.g. "Fit (01/01/2024)" or just "Fit"</li>
                            <li>Max file size: 10MB. Supported: .xlsx, .xls, .csv</li>
                        </ul>
                    </div>

                    <form id="masterImportForm" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Import Mode</label>
                            <select name="mode" class="form-control" style="border-radius: 8px;">
                                <option value="both">Create new & update existing staff</option>
                                <option value="create">Create new staff only (skip existing)</option>
                                <option value="update">Update existing staff only (skip new)</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Spreadsheet File</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="importFile" name="file" accept=".xlsx,.xls,.csv" required>
                                <label class="custom-file-label" for="importFile" style="border-radius: 8px;">Choose file...</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block" id="importBtn" style="border-radius: 8px; padding: 0.6rem;">
                            <i class="mdi mdi-upload mr-1"></i> Import Staff Data
                        </button>
                    </form>

                    <div id="importResult" class="mt-4" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function(){
    $('#importFile').on('change', function(){
        $(this).next('.custom-file-label').html(this.files[0]?.name || 'Choose file...');
    });

    $('#masterImportForm').on('submit', function(e){
        e.preventDefault();
        var btn = $('#importBtn');
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Importing...');
        $('#importResult').hide();

        var formData = new FormData(this);
        $.ajax({
            url: "{{ route('hr.master-import.import') }}",
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) {
                var html = '<div class="alert alert-success" style="border-radius:10px;border:none;">';
                html += '<h6 class="font-weight-bold"><i class="mdi mdi-check-circle mr-1"></i> ' + res.message + '</h6>';
                if (res.stats) {
                    html += '<p class="mb-0" style="font-size:0.88rem;">Created: <strong>' + res.stats.created + '</strong> | Updated: <strong>' + res.stats.updated + '</strong> | Skipped: <strong>' + res.stats.skipped + '</strong></p>';
                }
                if (res.errors_detail && res.errors_detail.length> 0) {
                    html += '<hr class="my-2"><p class="mb-1 font-weight-bold" style="font-size:0.85rem;">Warnings:</p><ul class="mb-0 pl-3" style="font-size:0.82rem;">';
                    res.errors_detail.forEach(function(err){ html += '<li>' + $('<span>').text(err).html() + '</li>'; });
                    html += '</ul>';
                }
                html += '</div>';
                $('#importResult').html(html).show();
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Import failed. Check your file format.';
                var html = '<div class="alert alert-danger" style="border-radius:10px;border:none;">';
                html += '<h6 class="font-weight-bold"><i class="mdi mdi-alert-circle mr-1"></i> Error</h6>';
                html += '<p class="mb-0">' + $('<span>').text(msg).html() + '</p></div>';
                $('#importResult').html(html).show();
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="mdi mdi-upload mr-1"></i> Import Staff Data');
            }
        });
    });
});
</script>
@endsection
