@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('js/morgue-workbench.js') }}?v={{ filemtime(public_path('js/morgue-workbench.js')) }}"></script>
@endsection
