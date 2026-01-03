@extends('admin.layouts.app')
@section('title', 'Lab Result Template Builder')
@section('page_name', 'Services')
@section('subpage_name', 'Build Result Template')

@section('styles')
<style>
    .parameter-item {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 10px;
        cursor: move;
        transition: all 0.3s;
    }
    .parameter-item:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .parameter-item.dragging {
        opacity: 0.5;
    }
    .drag-handle {
        cursor: grab;
        color: #999;
        margin-right: 10px;
    }
    .parameter-header {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    .parameter-number {
        background: #007bff;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
        font-weight: bold;
    }
    .remove-parameter {
        color: #dc3545;
        cursor: pointer;
        margin-left: auto;
    }
    .reference-range-fields {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 4px;
        margin-top: 10px;
    }
    .enum-options {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 4px;
        margin-top: 10px;
    }
    .enum-option-item {
        display: flex;
        gap: 10px;
        margin-bottom: 5px;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="mb-0">
                                <i class="fa fa-flask"></i>
                                Build Result Template for: <strong>{{ $service->service_name }}</strong>
                            </h5>
                            <small>Category: {{ $service->category ? $service->category->category_name : 'N/A' }}</small>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="{{ route('services.index') }}" class="btn btn-light btn-sm">
                                <i class="fa fa-arrow-left"></i> Back to Services
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <form id="template-form">
                        @csrf

                        <!-- Template Name -->
                        <div class="form-group row">
                            <label class="col-md-2 col-form-label">Template Name <i class="text-danger">*</i></label>
                            <div class="col-md-10">
                                <input type="text"
                                       class="form-control"
                                       id="template_name"
                                       name="template_name"
                                       value="{{ $template['template_name'] ?? $service->service_name . ' Template' }}"
                                       placeholder="e.g., Complete Blood Count Template"
                                       required>
                            </div>
                        </div>

                        <hr>

                        <!-- Parameters Section -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h5><i class="fa fa-list"></i> Test Parameters</h5>
                                <p class="text-muted small">Define the parameters that will be captured for this test</p>
                            </div>
                            <div class="col-md-6 text-right">
                                <button type="button" class="btn btn-success btn-sm" id="add-parameter">
                                    <i class="fa fa-plus"></i> Add Parameter
                                </button>
                            </div>
                        </div>

                        <!-- Parameters Container -->
                        <div id="parameters-container">
                            <!-- Parameters will be dynamically added here -->
                        </div>

                        <!-- Empty State -->
                        <div id="empty-state" class="text-center py-5" style="display: none;">
                            <i class="fa fa-flask fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No parameters defined yet. Click "Add Parameter" to start building your template.</p>
                        </div>
                    </form>
                </div>

                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6">
                            <button type="button" class="btn btn-secondary" onclick="previewTemplate()">
                                <i class="fa fa-eye"></i> Preview Template
                            </button>
                        </div>
                        <div class="col-md-6 text-right">
                            <button type="button" class="btn btn-primary" onclick="saveTemplate()">
                                <i class="fa fa-save"></i> Save Template
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script>
let parameterCount = 0;
const fieldTypes = {
    'string': 'Text (String)',
    'integer': 'Integer Number',
    'float': 'Decimal Number (Float)',
    'boolean': 'Yes/No (Boolean)',
    'enum': 'Multiple Choice (Enum)',
    'long_text': 'Long Text (Paragraph)'
};

// Initialize existing template if available
const existingTemplate = @json($template ?? null);

$(document).ready(function() {
    // Initialize Sortable for drag and drop
    const container = document.getElementById('parameters-container');
    new Sortable(container, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'dragging',
        onEnd: function() {
            updateParameterOrders();
        }
    });

    // Load existing parameters if template exists
    if (existingTemplate && existingTemplate.parameters) {
        existingTemplate.parameters.forEach(param => {
            addParameter(param);
        });
    }

    updateEmptyState();
});

// Add parameter button click
$('#add-parameter').click(function() {
    addParameter();
});

function addParameter(existingData = null) {
    parameterCount++;
    const order = existingData?.order || parameterCount;

    const html = `
        <div class="parameter-item" data-order="${order}">
            <div class="parameter-header">
                <span class="drag-handle">
                    <i class="fa fa-grip-vertical"></i>
                </span>
                <span class="parameter-number">${order}</span>
                <h6 class="mb-0 flex-grow-1">Parameter ${order}</h6>
                <span class="remove-parameter" onclick="removeParameter(this)">
                    <i class="fa fa-trash"></i> Remove
                </span>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Parameter Name <i class="text-danger">*</i></label>
                        <input type="text" class="form-control param-name"
                               value="${existingData?.name || ''}"
                               placeholder="e.g., White Blood Cells" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Code <i class="text-danger">*</i></label>
                        <input type="text" class="form-control param-code"
                               value="${existingData?.code || ''}"
                               placeholder="e.g., WBC" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Unit</label>
                        <input type="text" class="form-control param-unit"
                               value="${existingData?.unit || ''}"
                               placeholder="e.g., cells/μL">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Value Type <i class="text-danger">*</i></label>
                        <select class="form-control param-type" onchange="handleTypeChange(this)" required>
                            ${Object.entries(fieldTypes).map(([value, label]) =>
                                `<option value="${value}" ${existingData?.type === value ? 'selected' : ''}>${label}</option>`
                            ).join('')}
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Required?</label>
                        <select class="form-control param-required">
                            <option value="1" ${existingData?.required ? 'selected' : ''}>Yes</option>
                            <option value="0" ${!existingData?.required ? 'selected' : ''}>No</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Show in Report?</label>
                        <select class="form-control param-show-in-report">
                            <option value="1" ${existingData?.show_in_report !== false ? 'selected' : ''}>Yes</option>
                            <option value="0" ${existingData?.show_in_report === false ? 'selected' : ''}>No</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Reference Range Section (will be populated based on type) -->
            <div class="reference-range-container">
                ${generateReferenceRangeFields(existingData?.type || 'string', existingData)}
            </div>
        </div>
    `;

    $('#parameters-container').append(html);
    updateEmptyState();
}

function generateReferenceRangeFields(type, existingData = null) {
    const refRange = existingData?.reference_range || {};

    if (type === 'integer' || type === 'float') {
        return `
            <div class="reference-range-fields">
                <label class="font-weight-bold">Reference Range (Normal Values)</label>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Minimum (Low)</label>
                            <input type="number" step="${type === 'float' ? '0.01' : '1'}"
                                   class="form-control ref-min"
                                   value="${refRange.min || ''}"
                                   placeholder="Minimum normal value">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Maximum (High)</label>
                            <input type="number" step="${type === 'float' ? '0.01' : '1'}"
                                   class="form-control ref-max"
                                   value="${refRange.max || ''}"
                                   placeholder="Maximum normal value">
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else if (type === 'boolean') {
        return `
            <div class="reference-range-fields">
                <label class="font-weight-bold">Reference Value (Normal Value)</label>
                <div class="form-group">
                    <select class="form-control ref-value">
                        <option value="">No specific normal value</option>
                        <option value="true" ${refRange.reference_value === true ? 'selected' : ''}>True (Yes/Positive)</option>
                        <option value="false" ${refRange.reference_value === false ? 'selected' : ''}>False (No/Negative)</option>
                    </select>
                </div>
            </div>
        `;
    } else if (type === 'enum') {
        const options = existingData?.options || [];
        const normalValue = refRange.reference_value || '';

        return `
            <div class="reference-range-fields">
                <label class="font-weight-bold">Options & Reference Value</label>
                <div class="enum-options">
                    <label>Available Options <i class="text-danger">*</i></label>
                    <div class="enum-options-list">
                        ${options.map(opt => `
                            <div class="enum-option-item">
                                <input type="text" class="form-control enum-option-value" value="${opt.value}" placeholder="Option Value" required>
                                <input type="text" class="form-control enum-option-label" value="${opt.label}" placeholder="Display Label" required>
                                <button type="button" class="btn btn-sm btn-danger" onclick="removeEnumOption(this)">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        `).join('')}
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="addEnumOption(this)">
                        <i class="fa fa-plus"></i> Add Option
                    </button>
                </div>
                <div class="form-group mt-2">
                    <label>Reference Value (Normal Value)</label>
                    <input type="text" class="form-control ref-value"
                           value="${normalValue}"
                           placeholder="The value considered normal">
                </div>
            </div>
        `;
    } else {
        return `
            <div class="reference-range-fields">
                <label class="font-weight-bold">Reference Information</label>
                <div class="form-group">
                    <textarea class="form-control ref-text" rows="2"
                              placeholder="Optional: Describe what is considered normal">${refRange.text || ''}</textarea>
                </div>
            </div>
        `;
    }
}

function handleTypeChange(selectElement) {
    const paramItem = $(selectElement).closest('.parameter-item');
    const type = $(selectElement).val();
    const refContainer = paramItem.find('.reference-range-container');

    refContainer.html(generateReferenceRangeFields(type));
}

function addEnumOption(button) {
    const optionsList = $(button).siblings('.enum-options-list');
    const html = `
        <div class="enum-option-item">
            <input type="text" class="form-control enum-option-value" placeholder="Option Value" required>
            <input type="text" class="form-control enum-option-label" placeholder="Display Label" required>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeEnumOption(this)">
                <i class="fa fa-times"></i>
            </button>
        </div>
    `;
    optionsList.append(html);
}

function removeEnumOption(button) {
    $(button).closest('.enum-option-item').remove();
}

function removeParameter(element) {
    if (confirm('Are you sure you want to remove this parameter?')) {
        $(element).closest('.parameter-item').remove();
        updateParameterOrders();
        updateEmptyState();
    }
}

function updateParameterOrders() {
    $('#parameters-container .parameter-item').each(function(index) {
        $(this).attr('data-order', index + 1);
        $(this).find('.parameter-number').text(index + 1);
        $(this).find('.parameter-header h6').text('Parameter ' + (index + 1));
    });
}

function updateEmptyState() {
    const hasParameters = $('#parameters-container .parameter-item').length > 0;
    $('#empty-state').toggle(!hasParameters);
}

function collectTemplateData() {
    const parameters = [];

    $('#parameters-container .parameter-item').each(function(index) {
        const item = $(this);
        const type = item.find('.param-type').val();

        const param = {
            id: 'param_' + (index + 1),
            name: item.find('.param-name').val(),
            code: item.find('.param-code').val(),
            type: type,
            unit: item.find('.param-unit').val() || null,
            required: item.find('.param-required').val() === '1',
            show_in_report: item.find('.param-show-in-report').val() === '1',
            order: index + 1,
            reference_range: null,
            options: null,
            labels: null
        };

        // Collect reference range based on type
        if (type === 'integer' || type === 'float') {
            const min = item.find('.ref-min').val();
            const max = item.find('.ref-max').val();
            if (min || max) {
                param.reference_range = {
                    min: min ? parseFloat(min) : null,
                    max: max ? parseFloat(max) : null
                };
            }
        } else if (type === 'boolean') {
            const refValue = item.find('.ref-value').val();
            if (refValue) {
                param.reference_range = {
                    reference_value: refValue === 'true'
                };
            }
        } else if (type === 'enum') {
            const options = [];
            item.find('.enum-option-item').each(function() {
                const value = $(this).find('.enum-option-value').val();
                const label = $(this).find('.enum-option-label').val();
                if (value && label) {
                    options.push({ value, label });
                }
            });
            param.options = options;

            const refValue = item.find('.ref-value').val();
            if (refValue) {
                param.reference_range = {
                    reference_value: refValue
                };
            }
        } else {
            const refText = item.find('.ref-text').val();
            if (refText) {
                param.reference_range = {
                    text: refText
                };
            }
        }

        parameters.push(param);
    });

    return {
        template_name: $('#template_name').val(),
        parameters: parameters
    };
}

function saveTemplate() {
    const templateData = collectTemplateData();

    // Validation
    if (!templateData.template_name) {
        alert('Please enter a template name');
        return;
    }

    if (templateData.parameters.length === 0) {
        alert('Please add at least one parameter');
        return;
    }

    // Check if all required fields are filled
    let isValid = true;
    templateData.parameters.forEach(param => {
        if (!param.name || !param.code) {
            isValid = false;
        }
    });

    if (!isValid) {
        alert('Please fill in all required fields (Name and Code) for all parameters');
        return;
    }

    // Show loading
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

    // Send to server
    $.ajax({
        url: '{{ route("services.save-template", $service->id) }}',
        method: 'POST',
        data: templateData,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            btn.disabled = false;
            btn.innerHTML = originalText;

            if (response.success) {
                alert('Template saved successfully!');
                // Optionally redirect back to services
                // window.location.href = '{{ route("services.index") }}';
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr) {
            btn.disabled = false;
            btn.innerHTML = originalText;

            let errorMsg = 'Error saving template';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg += ': ' + xhr.responseJSON.message;
            }
            alert(errorMsg);
            console.error('Error:', xhr);
        }
    });
}

function previewTemplate() {
    const templateData = collectTemplateData();

    // Create preview modal
    const preview = `
        <div class="modal fade" id="previewModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Template Preview: ${templateData.template_name}</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <pre style="max-height: 500px; overflow-y: auto;">${JSON.stringify(templateData, null, 2)}</pre>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    $('#previewModal').remove();
    $('body').append(preview);
    $('#previewModal').modal('show');
}
</script>
@endsection
