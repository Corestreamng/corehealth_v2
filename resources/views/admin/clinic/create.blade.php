@extends('admin.layouts.app')
@section('title', 'Create Clinic')
@section('page_name', 'Clinic')
@section('subpage_name', 'Create Clinic')

@section('content')
    <section class="container">
        <div class="card-modern border-info mb-3">
            <div class="card-header">
                <h4>Create Clinic</h4>
                <a href="{{ route('clinics.index') }}" class="btn btn-secondary float-end">Back to List</a>
            </div>
            <div class="card-body">
                <form action="{{ route('clinics.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <button type="submit" class="btn btn-success">Create</button>
                </form>
            </div>
        </div>
    </section>
@endsection
