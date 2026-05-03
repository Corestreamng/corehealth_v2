@extends('admin.layouts.app')
@section('title', 'View Clinic - ' . $clinic->name)
@section('page_name', 'Clinic')
@section('subpage_name', 'View Clinic')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card-modern shadow-sm border-0">
                    <div class="card-header-modern d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1 fw-bold text-dark">
                                <i class="mdi mdi-hospital-building text-primary"></i> {{ $clinic->name }}
                            </h4>
                            <p class="text-muted mb-0 small">Clinic profile and specialized configurations.</p>
                        </div>
                        <div class="btn-group">
                            <a href="{{ route('clinics.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="mdi mdi-arrow-left"></i> Back
                            </a>
                            <a href="{{ route('clinics.edit', $clinic->id) }}" class="btn btn-primary btn-sm shadow-sm">
                                <i class="mdi mdi-pencil"></i> Edit Configuration
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-sm-3 text-muted fw-bold">Clinic Name:</div>
                            <div class="col-sm-9 text-dark fw-bold h5 mb-0">{{ $clinic->name }}</div>
                        </div>

                        <hr class="light">

                        <h5 class="mb-3 text-dark fw-bold">
                            <i class="mdi mdi-heart-pulse text-danger me-2"></i> Specialized Vitals Template
                        </h5>

                        @if($clinic->vitals_template && count($clinic->vitals_template) > 0)
                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-bordered border-light">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Label</th>
                                            <th>Field ID</th>
                                            <th>Type</th>
                                            <th>Unit / Options</th>
                                            <th class="text-center">Required</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($clinic->vitals_template as $field)
                                            <tr>
                                                <td class="fw-bold">{{ $field['label'] }}</td>
                                                <td><code>{{ $field['name'] }}</code></td>
                                                <td>
                                                    <span class="badge bg-soft-primary text-primary border border-primary px-2">
                                                        {{ ucfirst($field['type']) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if($field['type'] === 'select')
                                                        <div class="small">
                                                            @foreach($field['options'] ?? [] as $option)
                                                                <span class="badge bg-light text-dark border me-1">{{ $option }}</span>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        {{ $field['unit'] ?? 'N/A' }}
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if(isset($field['required']) && $field['required'])
                                                        <i class="mdi mdi-check-circle text-success" style="font-size: 1.2rem;"></i>
                                                    @else
                                                        <i class="mdi mdi-close-circle text-muted" style="font-size: 1.2rem;"></i>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-soft-warning border-warning d-flex align-items-center">
                                <i class="mdi mdi-alert-circle me-2" style="font-size: 1.5rem;"></i>
                                <div>
                                    <strong>No specialized template found!</strong> This clinic will use the standard hospital vitals configuration.
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
