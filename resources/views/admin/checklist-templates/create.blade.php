@extends('admin.layouts.app')
@section('title', 'New Checklist Template')
@section('page_name', 'Hospital Setup')
@section('subpage_name', 'New Checklist Template')

@section('styles')
<style>
    .checklist-items-container {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 1rem;
        min-height: 200px;
    }
    .checklist-item-row {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
    }
    .checklist-item-row:hover {
        border-color: #007bff;
    }
    .checklist-item-row .drag-handle {
        cursor: move;
        color: #adb5bd;
    }
    .checklist-item-row .drag-handle:hover {
        color: #495057;
    }
    .btn-remove-item {
        opacity: 0.6;
    }
    .btn-remove-item:hover {
        opacity: 1;
    }
</style>
@endsection

@section('content')
<section class="content">
    <div class="col-12">
        <div class="card-modern">
            <div class="card-header">
                <h3 class="card-title">Create New Checklist Template</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('checklist-templates.store') }}" id="templateForm">
                    @csrf

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name" class="control-label">Template Name <i class="text-danger">*</i></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    id="name" name="name" value="{{ old('name') }}"
                                    required placeholder="e.g., Standard Admission Checklist">
                                @error('name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="type" class="control-label">Template Type <i class="text-danger">*</i></label>
                                <select class="form-control @error('type') is-invalid @enderror"
                                    id="type" name="type" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="admission" {{ old('type') == 'admission' ? 'selected' : '' }}>
                                        Admission Checklist
                                    </option>
                                    <option value="discharge" {{ old('type') == 'discharge' ? 'selected' : '' }}>
                                        Discharge Checklist
                                    </option>
                                </select>
                                @error('type')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description" class="control-label">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror"
                            id="description" name="description" rows="2"
                            placeholder="Brief description of when this checklist should be used">{{ old('description') }}</textarea>
                        @error('description')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="is_active"
                                name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_active">Active</label>
                        </div>
                    </div>

                    <hr>

                    <h5><i class="mdi mdi-format-list-checks"></i> Checklist Items</h5>
                    <p class="text-muted">Add the items that nurses must verify during the checklist process.</p>

                    <div class="checklist-items-container" id="checklistItems">
                        <!-- Items will be added here -->
                    </div>

                    <button type="button" class="btn btn-outline-primary mt-3" id="addItemBtn">
                        <i class="fa fa-plus"></i> Add Checklist Item
                    </button>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-save"></i> Save Template
                            </button>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="{{ route('checklist-templates.index') }}" class="btn btn-secondary">
                                <i class="fa fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    var itemIndex = 0;

    // Add item button
    $('#addItemBtn').click(function() {
        addItemRow();
    });

    function addItemRow(name = '', description = '', isRequired = true) {
        var html = `
            <div class="checklist-item-row" data-index="${itemIndex}">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="mdi mdi-drag drag-handle"></i>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm"
                            name="items[${itemIndex}][name]" value="${name}"
                            placeholder="Item name (e.g., Verify Patient ID)" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm"
                            name="items[${itemIndex}][description]" value="${description}"
                            placeholder="Description (optional)">
                    </div>
                    <div class="col-md-2">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input"
                                id="required_${itemIndex}" name="items[${itemIndex}][is_required]"
                                value="1" ${isRequired ? 'checked' : ''}>
                            <label class="custom-control-label" for="required_${itemIndex}">Required</label>
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-item">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;

        $('#checklistItems').append(html);
        itemIndex++;
    }

    // Remove item
    $(document).on('click', '.btn-remove-item', function() {
        $(this).closest('.checklist-item-row').remove();
    });

    // Add default items based on type selection
    $('#type').change(function() {
        var type = $(this).val();
        if ($('#checklistItems').children().length === 0) {
            if (type === 'admission') {
                addDefaultAdmissionItems();
            } else if (type === 'discharge') {
                addDefaultDischargeItems();
            }
        }
    });

    function addDefaultAdmissionItems() {
        var items = [
            { name: 'Verify Patient Identity', description: 'Check patient ID, wristband, and photo ID', required: true },
            { name: 'Review Medical History', description: 'Confirm allergies, medications, and conditions', required: true },
            { name: 'Vital Signs Recorded', description: 'Complete baseline vital signs assessment', required: true },
            { name: 'Consent Forms Signed', description: 'Ensure all required consent forms are signed', required: true },
            { name: 'Patient Belongings Documented', description: 'List and secure patient belongings', required: false },
            { name: 'Orient Patient to Ward', description: 'Explain call bell, visiting hours, meals', required: false },
        ];

        items.forEach(function(item) {
            addItemRow(item.name, item.description, item.required);
        });
    }

    function addDefaultDischargeItems() {
        var items = [
            { name: 'Discharge Orders Reviewed', description: 'Verify doctor has signed discharge orders', required: true },
            { name: 'Medications Explained', description: 'Review discharge medications with patient', required: true },
            { name: 'Follow-up Appointments Scheduled', description: 'Confirm follow-up appointments are booked', required: true },
            { name: 'Patient Education Completed', description: 'Provide care instructions and warning signs', required: true },
            { name: 'Belongings Returned', description: 'Return all documented patient belongings', required: true },
            { name: 'Final Vital Signs', description: 'Record final vital signs before discharge', required: false },
            { name: 'Transport Arranged', description: 'Confirm patient has transportation home', required: false },
        ];

        items.forEach(function(item) {
            addItemRow(item.name, item.description, item.required);
        });
    }

    // Initialize with one empty item
    addItemRow();
});
</script>
@endsection
