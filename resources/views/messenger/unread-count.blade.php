@php
$count = Auth::user()->newThreadsCount();
@endphp
@if($count > 0)
    <span class="badge badge-danger">{{ $count }}</span>
@endif
