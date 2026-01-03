@extends('admin.layouts.app')
@section('title', 'Imaging Report')
@section('page_name', 'Imaging Management')
@section('subpage_name', 'Imaging Report')
@section('content')

    <div id="content-wrapper">
        <div class="container">
            <button onclick="printDiv('result')" class="btn btn-primary mb-3">
                <i class="fa fa-print"></i> Print Report
            </button>
            <div class="card" id="result">
                <div class="card-header">
                    <h4 class="card-title">{{ $req?->service?->service_name ?? 'Imaging Report' }}</h4>
                    <p class="mb-0"><strong>Patient:</strong> {{ userfullname($req?->patient?->user_id) }}</p>
                    <p class="mb-0"><strong>Date:</strong> {{ date('M j, Y h:i A', strtotime($req?->result_date)) }}</p>
                </div>

                <div class="card-body">
                    {!! $req?->result ?? 'No result available' !!}

                    @if($req?->attachments)
                        <hr>
                        <h5><i class="mdi mdi-paperclip"></i> Attachments:</h5>
                        <div class="row">
                            @foreach(is_string($req->attachments) ? json_decode($req->attachments, true) : $req->attachments as $attachment)
                                <div class="col-md-3 mb-2">
                                    <a href="{{ asset('storage/' . $attachment['path']) }}" target="_blank" class="btn btn-outline-info btn-block">
                                        @if(str_contains($attachment['type'], 'pdf'))
                                            <i class="mdi mdi-file-pdf"></i>
                                        @elseif(str_contains($attachment['type'], 'doc'))
                                            <i class="mdi mdi-file-word"></i>
                                        @elseif(str_contains($attachment['type'], 'image') || in_array($attachment['type'], ['jpg', 'jpeg', 'png']))
                                            <i class="mdi mdi-file-image"></i>
                                        @else
                                            <i class="mdi mdi-file"></i>
                                        @endif
                                        {{ $attachment['name'] }}
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
    <script>
        function printDiv(id) {
            var content = document.getElementById(id).innerHTML;
            var popupWindow = window.open('', '_blank', 'width=800,height=600');
            popupWindow.document.open();
            popupWindow.document.write('<html><head><title>' + document.title + '</title>');
            popupWindow.document.write('<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">');
            popupWindow.document.write('<style>@media print { .no-print { display: none; } }</style>');
            popupWindow.document.write('</head><body>');
            popupWindow.document.write(content);
            popupWindow.document.write('</body></html>');
            popupWindow.document.close();
            popupWindow.print();
        }
    </script>
@endsection
