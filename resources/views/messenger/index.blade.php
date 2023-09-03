@extends('layouts.app')

@section('content')
    <div class="container">
        <h3>Conversations</h3>
        @include('messenger.partials.flash')

        @each('messenger.partials.thread', $threads, 'thread', 'messenger.partials.no-threads')
    </div>
@stop
