@extends('admin.layouts.app')
@section('title', 'View Specialization')
@section('page_name', 'Specialization')
@section('subpage_name', 'View Specialization')

@section('content')
    <section class="container">
        <div class="card-modern border-info mb-3">
            <div class="card-header">
                <h4>Specialization Details</h4>
                <a href="{{ route('specializations.index') }}" class="btn btn-secondary float-end">Back to List</a>
            </div>
            <div class="card-body">
                <h5><strong>Name:</strong> {{ $specialization->name }}</h5>
            </div>
        </div>
    </section>
@endsection
