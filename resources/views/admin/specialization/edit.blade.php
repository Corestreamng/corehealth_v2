@extends('admin.layouts.app')
@section('title', 'Edit Specialization')
@section('page_name', 'Specialization')
@section('subpage_name', 'Edit Specialization')

@section('content')
    <section class="container">
        <div class="card border-info mb-3">
            <div class="card-header">
                <h4>Edit Specialization</h4>
                <a href="{{ route('specializations.index') }}" class="btn btn-secondary float-end">Back to List</a>
            </div>
            <div class="card-body">
                <form action="{{ route('specializations.update', $specialization->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="{{ $specialization->name }}" required>
                    </div>
                    <button type="submit" class="btn btn-success">Update</button>
                </form>
            </div>
        </div>
    </section>
@endsection
