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
