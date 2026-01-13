@extends('admin.layouts.app')
@section('title', 'Edit Clinic')
@section('page_name', 'Clinic')
@section('subpage_name', 'Edit Clinic')

@section('content')
    <section class="container">
        <div modern border-info mb-3">
            <div class="card-header">
                <h4>Edit Clinic</h4>
                <a href="{{ route('clinics.index') }}" class="btn btn-secondary float-end">Back to List</a>
            </div>
            <div class="card-body">
                <form action="{{ route('clinics.update', $clinic->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="{{ $clinic->name }}" required>
                    </div>
                    <button type="submit" class="btn btn-success">Update</button>
                </form>
            </div>
        </div>
    </section>
@endsection
