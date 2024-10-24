@extends('admin.layouts.app')
@section('title', 'Create Specialization')
@section('page_name', 'Specialization')
@section('subpage_name', 'Create Specialization')

@section('content')
    <section class="container">
        <div class="card border-info mb-3">
            <div class="card-header">
                <h4>Create Specialization</h4>
                <a href="{{ route('specializations.index') }}" class="btn btn-secondary float-end">Back to List</a>
            </div>
            <div class="card-body">
                <form action="{{ route('specializations.store') }}" method="POST">
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
