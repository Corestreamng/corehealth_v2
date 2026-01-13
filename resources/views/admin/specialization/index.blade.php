@extends('admin.layouts.app')
@section('title', 'Specializations List')
@section('page_name', 'Specialization')
@section('subpage_name', 'List Specializations')

@section('content')
    <section class="container">
        <div class="card-modern border-info mb-3">
            <div class="card-header">
                <h4>Specializations</h4>
                <a href="{{ route('specializations.create') }}" class="btn btn-primary float-end">Create New</a>
            </div>
            <div class="card-body">
                @if($specializations->isEmpty())
                    <p class="text-center">No specializations found.</p>
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
                            @foreach($specializations as $specialization)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $specialization->name }}</td>
                                    <td>
                                        <a href="{{ route('specializations.show', $specialization->id) }}" class="btn btn-info btn-sm">View</a>
                                        <a href="{{ route('specializations.edit', $specialization->id) }}" class="btn btn-warning btn-sm">Edit</a>
                                        <form action="{{ route('specializations.destroy', $specialization->id) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Are you sure?')">
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
