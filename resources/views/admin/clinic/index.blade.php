@extends('admin.layouts.app')
@section('title', 'Clinics List')
@section('page_name', 'Clinic')
@section('subpage_name', 'List Clinics')

@section('content')
    <section class="container">
        <div class="card-modern border-info mb-3">
            <div class="card-header">
                <h4>Clinics</h4>
                <a href="{{ route('clinics.create') }}" class="btn btn-primary float-end">Create New</a>
            </div>
            <div class="card-body">
                @if($clinics->isEmpty())
                    <p class="text-center">No clinics found.</p>
                @else
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($clinics as $clinic)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $clinic->name }}</td>
                                    <td>
                                        <a href="{{ route('clinics.show', $clinic->id) }}" class="btn btn-info btn-sm">View</a>
                                        <a href="{{ route('clinics.edit', $clinic->id) }}" class="btn btn-warning btn-sm">Edit</a>
                                        <form action="{{ route('clinics.destroy', $clinic->id) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Are you sure?')">
                                            @csrf
                                            @method('DELETE')
                                            {{-- <button class="btn btn-danger btn-sm">Delete</button> --}}
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </section>
@endsection
