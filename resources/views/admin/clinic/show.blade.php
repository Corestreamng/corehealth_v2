@extends('admin.layouts.app')
@section('title', 'View Clinic')
@section('page_name', 'Clinic')
@section('subpage_name', 'View Clinic')

@section('content')
    <section class="container">
        <div class="card border-info mb-3">
            <div class="card-header">
                <h4>Clinic Details</h4>
                <a href="{{ route('clinics.index') }}" class="btn btn-secondary float-end">Back to List</a>
            </div>
            <div class="card-body">
                <h5><strong>Name:</strong> {{ $clinic->name }}</h5>
            </div>
        </div>
    </section>
@endsection
