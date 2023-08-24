@extends('admin.layouts.app')
@section('title', 'Services Rendered History')
@section('page_name', 'Services Rendered')
@section('subpage_name', 'History')
@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                {{userfullname($patient->user_id)}} [{{$patient->file_no}}]
            </h3>
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-sm-12">
                    <form
                        action="{{ route('patient-services-rendered', ['patient_id'=>$patient->id]) }}"
                        method="get" class="form-inline">
                        <div class="form-group">
                            <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                            <label for="">Date between</label>
                            <input type="date" name="start_from" id="start_from" class="form-control m-1"
                                value="{{ Request::get('start_from') ? Request::get('start_from') : '' }}" required>
                            <input type="date" name="stop_at" id="stop_at" class="form-control m-1"
                                value="{{ Request::get('stop_at') ? Request::get('stop_at') : '' }}" required>
                            <button type="submit" class="btn btn-primary m-1">Fetch</button>
                        </div>
                    </form>
                </div>
                <hr>
            </div>
            @if (null != Request::get('start_from') && null != Request::get('stop_at'))
                <button type="button" class="btn btn-warning" onclick="PrintElem('tableToPrint')"><i
                        class="fa fa-print"></i> Print</button>
                <hr>
                <div id="tableToPrint">
                    <div class="table-responsive">
                        <table class="table table-sm text-sm table-sm table-bordered table-striped ">
                            <thead>
                                <tr align="center">
                                    <th>{{ $app->site_name }}</th>
                                </tr>
                                <tr>
                                    <th>Fullname</th>
                                </tr>
                                <tr>
                                    <td>{{ userfullname($patient->user_id) }} <br>Hosp.
                                        No:{{ ($patient->file_no) }}</td>
                                </tr>
                                <tr>
                                    <th>HMO / Insurance</th>
                                </tr>
                                <tr>
                                    <td>{{ $patient->hmo->name }}</td>

                                </tr>
                                <tr>
                                    <th>HMO Number</th>
                                </tr>
                                <tr>
                                    <td>{{ $patient->hmo_no }}</td>
                                </tr>
                                <tr>
                                    <th>Repeort period</th>
                                </tr>
                                <tr>
                                    <td>
                                        {{ Request::get('start_from') }}
                                        <br>
                                        {{ Request::get('stop_at') }}
                                    </td>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th>Services Rendered</th>
                                </tr>
                                @foreach ($consultation as $con)
                                    <tr>
                                        <td>
                                            Consultation
                                            <br> {{ (($con->doctor->staff_profile) ? $con->doctor->staff_profile->specialization->name : 'N/A') }}
                                            <br> Dr. {{ userfullname($con->doctor->user_id) }}
                                            <br>{{ $con->created_at }}
                                        </td>
                                    </tr>
                                @endforeach
                                @foreach ($prescription as $pres)
                                    <tr>
                                        <td>
                                            Precription
                                            <br> Dr. {{ userfullname($pres->doctor_id) }}
                                            <br>{{$pres->product->product_name }}
                                            <br>{{ $pres->created_at }}
                                        </td>
                                    </tr>
                                @endforeach
                                @foreach ($lab as $la)
                                    <tr>
                                        <td>
                                            Investigation Request
                                            <br> Dr. {{ userfullname($la->doctor_id) }}
                                            <br>{{ $la->service->service_name }}
                                            <br>{{ $la->created_at }}
                                        </td>
                                    </tr>
                                @endforeach
                                @foreach ($misc as $mis)
                                    <tr>
                                        <td>
                                            Nursing service request
                                            <br> Nurse: {{ userfullname($mis->created_by) }}
                                            <br>{{ $mis->service->service_name }}
                                            <br>{{ $mis->created_at }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script>
        function PrintElem(elem) {
            var mywindow = window.open('', 'PRINT', 'height=400,width=600');

            mywindow.document.write('<html><head><title>' + document.title + '</title>');
            mywindow.document.write(`<style>
                            table,th,td{
                                padding:2px;
                                border: 1px solid black;
                                border-collapse: collapse;
                            }
                            table{
                                width:50mm;
                                margin:0;
                            }
                            body{
                                margin:0;
                                max-height:100%;
                                font-size:9pt;
                                font-family:monospace;
                            }
                            html{
                                margin:0;
                                height:100%;
                            }
                        </style>`)
            mywindow.document.write('</head><body>');
            mywindow.document.write(document.getElementById(elem).innerHTML);
            mywindow.document.write('</body></html>');

            mywindow.document.close(); //IE >=10
            mywindow.focus(); // IE >= 10

            mywindow.print();
            //mywindow.close();

            return true;
        }
    </script>
@endsection
