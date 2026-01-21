{{-- JavaScript functions for delete functionality --}}
<script>
let currentDeleteItem = null;

// Delete Lab Request
function deleteLabRequest(labId, encounterId, serviceName) {
    currentDeleteItem = {
        type: 'lab',
        id: labId,
        encounterId: encounterId,
        name: serviceName
    };

    $('#deleteItemInfo').html(`
        <strong>Service:</strong> ${serviceName}<br>
        <strong>Type:</strong> Laboratory Test
    `);

    $('#deleteConfirmModal').modal('show');
}

// Delete Imaging Request
function deleteImagingRequest(imagingId, encounterId, serviceName) {
    currentDeleteItem = {
        type: 'imaging',
        id: imagingId,
        encounterId: encounterId,
        name: serviceName
    };

    $('#deleteItemInfo').html(`
        <strong>Service:</strong> ${serviceName}<br>
        <strong>Type:</strong> Imaging/Radiology
    `);

    $('#deleteConfirmModal').modal('show');
}

// Delete Prescription
function deletePrescription(prescriptionId, encounterId, productName) {
    currentDeleteItem = {
        type: 'prescription',
        id: prescriptionId,
        encounterId: encounterId,
        name: productName
    };

    $('#deleteItemInfo').html(`
        <strong>Medication:</strong> ${productName}<br>
        <strong>Type:</strong> Prescription
    `);

    $('#deleteConfirmModal').modal('show');
}

// Confirm Delete Button Handler
$('#confirmDeleteBtn').on('click', function() {
    const reasonSelect = $('#deletionReasonSelect').val();
    const reasonOther = $('#deletionReasonOther').val();
    const notes = $('#deletionNotes').val();

    // Validate reason
    if (!reasonSelect) {
        alert('Please select a reason for deletion');
        return;
    }

    if (reasonSelect === 'Other' && !reasonOther) {
        alert('Please specify the reason');
        return;
    }

    // Build final reason text
    let finalReason = reasonSelect;
    if (reasonSelect === 'Other' && reasonOther) {
        finalReason = reasonOther;
    }
    if (notes) {
        finalReason += '. Additional notes: ' + notes;
    }

    // Determine the URL based on type
    let url = '';
    if (currentDeleteItem.type === 'lab') {
        url = `/encounters/${currentDeleteItem.encounterId}/labs/${currentDeleteItem.id}`;
    } else if (currentDeleteItem.type === 'imaging') {
        url = `/encounters/${currentDeleteItem.encounterId}/imaging/${currentDeleteItem.id}`;
    } else if (currentDeleteItem.type === 'prescription') {
        url = `/encounters/${currentDeleteItem.encounterId}/prescriptions/${currentDeleteItem.id}`;
    } else if (currentDeleteItem.type === 'procedure') {
        url = `/encounters/${currentDeleteItem.encounterId}/procedures/${currentDeleteItem.id}`;
    } else if (currentDeleteItem.type === 'encounter') {
        url = `/encounters/${currentDeleteItem.id}`;
    }

    // Disable button and show loading
    $('#confirmDeleteBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');

    // Send AJAX DELETE request
    $.ajax({
        url: url,
        type: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            reason: finalReason
        },
        success: function(response) {
            if (response.success) {
                // Close modal
                $('#deleteConfirmModal').modal('hide');

                // Show success message
                alert('Request deleted successfully');

                // Reload the appropriate DataTable
                if (currentDeleteItem.type === 'lab') {
                    $('#investigation_history_list').DataTable().ajax.reload();
                } else if (currentDeleteItem.type === 'imaging') {
                    $('#imaging_history_list').DataTable().ajax.reload();
                } else if (currentDeleteItem.type === 'prescription') {
                    $('#presc_history_list').DataTable().ajax.reload();
                } else if (currentDeleteItem.type === 'procedure') {
                    $('#procedure_history_list').DataTable().ajax.reload();
                } else if (currentDeleteItem.type === 'encounter') {
                    $('#encounter_history_list').DataTable().ajax.reload();
                }

                // Reset form
                resetDeleteModal();
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON;

            // Check if it's a permission/validation error
            if (xhr.status === 403 && response.reason) {
                // Close confirm modal
                $('#deleteConfirmModal').modal('hide');

                // Show denial modal
                $('#deleteDeniedReason').html('<strong>' + response.message + '</strong>');
                $('#deleteDeniedModal').modal('show');

                resetDeleteModal();
            } else {
                alert('Error: ' + (response && response.message ? response.message : 'Failed to delete request'));
                $('#confirmDeleteBtn').prop('disabled', false).html('<i class="fa fa-trash-alt"></i> Delete Request');
            }
        }
    });
});

// Reset delete modal
function resetDeleteModal() {
    $('#deletionReasonSelect').val('');
    $('#deletionReasonOther').val('');
    $('#deletionNotes').val('');
    $('#otherReasonGroup').hide();
    $('#confirmDeleteBtn').prop('disabled', false).html('<i class="fa fa-trash-alt"></i> Delete Request');
    currentDeleteItem = null;
}

// Reset modal when closed
$('#deleteConfirmModal').on('hidden.bs.modal', function() {
    resetDeleteModal();
});

// Delete Encounter Note
function deleteEncounter(encounterId, encounterDate) {
    currentDeleteItem = {
        type: 'encounter',
        id: encounterId,
        name: `Encounter from ${encounterDate}`
    };

    $('#deleteItemInfo').html(`
        <strong>Encounter:</strong> ${encounterDate}<br>
        <strong>Type:</strong> Clinical Note
    `);

    $('#deleteConfirmModal').modal('show');
}

// Edit Encounter Note - Populate modal with full functionality
if (typeof editEncounterEditorInstance === 'undefined') {
    var editEncounterEditorInstance = null;
}

function editEncounterNote(btn) {
    const $btn = $(btn);
    const id = $btn.data('id');
    const notes = $btn.data('notes');
    const reasons = $btn.attr('data-reasons'); // Use attr to ensure string
    const comment1 = $btn.data('comment1');
    const comment2 = $btn.data('comment2');
    const isWardRound = $btn.data('is-ward-round');

    // Store the encounter ID
    $('#editEncounterId').val(id);

    // Populate diagnosis selection if available
    @if(appsettings('requirediagnosis', 0))
    if (reasons && reasons.trim() !== '') {
        // Split by comma and trim each value
        const reasonsArray = reasons.split(',').map(r => r.trim());
        $('#editEncounterReasons').val(reasonsArray).trigger('change');

        // Uncheck Not Applicable
        $('#editEncounterNotApplicable').prop('checked', false).trigger('change');
    } else {
        $('#editEncounterReasons').val([]).trigger('change');

        // Check Not Applicable if no reasons, or if it's a ward round (default behavior requested)
        // Logic: If reasons are empty, we assume N/A.
        $('#editEncounterNotApplicable').prop('checked', true).trigger('change');
    }

    $('#editEncounterComment1').val(comment1 || 'NA');
    $('#editEncounterComment2').val(comment2 || 'NA');
    @endif

    // Store notes to set after editor initialization
    const notesContent = notes || '';

    // Show the modal first
    $('#editEncounterModal').modal('show');

    // Initialize ClassicEditor (CKEditor 5) after modal is shown
    // Wait a bit to ensure modal DOM is ready
    setTimeout(function() {
        // Destroy existing editor instance if any
        if (editEncounterEditorInstance) {
            editEncounterEditorInstance.destroy()
                .then(() => {
                    initializeEditEncounterEditor(notesContent);
                })
                .catch(error => {
                    console.error('Error destroying editor:', error);
                    initializeEditEncounterEditor(notesContent);
                });
        } else {
            initializeEditEncounterEditor(notesContent);
        }
    }, 300);
}

function initializeEditEncounterEditor(content) {
    const editorElement = document.querySelector('#editEncounterNotes');

    if (!editorElement) {
        console.error('Editor element not found');
        return;
    }

    ClassicEditor
        .create(editorElement, {
            toolbar: {
                items: [
                    'undo', 'redo',
                    '|', 'heading',
                    '|', 'bold', 'italic',
                    '|', 'link', 'uploadImage', 'insertTable', 'mediaEmbed',
                    '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                ]
            }
        })
        .then(editor => {
            editEncounterEditorInstance = editor;
            // Set the content
            editor.setData(content);
        })
        .catch(error => {
            console.error('Error initializing editor:', error);
            // Fallback to plain textarea
            $('#editEncounterNotes').val(content);
        });
}

// Save Encounter Edit
$('#saveEncounterEditBtn').on('click', function() {
    const encounterId = $('#editEncounterId').val();

    // Get notes from ClassicEditor or textarea
    let notes = '';
    if (editEncounterEditorInstance) {
        notes = editEncounterEditorInstance.getData();
    } else {
        notes = $('#editEncounterNotes').val();
    }

    @if(appsettings('requirediagnosis', 0))
    const notApplicable = $('#editEncounterNotApplicable').is(':checked');
    let reasons = [];
    let comment1 = 'NA';
    let comment2 = 'NA';

    if (!notApplicable) {
        reasons = $('#editEncounterReasons').val();
        comment1 = $('#editEncounterComment1').val();
        comment2 = $('#editEncounterComment2').val();

        if (!reasons || reasons.length === 0) {
            alert('Please select at least one diagnosis reason or check "Diagnosis Not Applicable".');
            return;
        }

        if (!comment1 || !comment2) {
            alert('Please select both diagnosis comments.');
            return;
        }
    }
    @endif

    if (!notes || !notes.trim()) {
        alert('Clinical notes are required.');
        return;
    }

    $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

    $.ajax({
        url: `/encounters/${encounterId}/notes`,
        type: 'PUT',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            notes: notes,
            @if(appsettings('requirediagnosis', 0))
            reasons_for_encounter: notApplicable ? '' : reasons.join(','),
            reasons_for_encounter_comment_1: notApplicable ? 'NA' : comment1,
            reasons_for_encounter_comment_2: notApplicable ? 'NA' : comment2
            @endif
        },
        success: function(response) {
            if (response.success) {
                $('#editEncounterModal').modal('hide');
                $('#encounter_history_list').DataTable().ajax.reload();
                alert('Encounter note updated successfully!');
            } else {
                alert(response.message || 'Failed to update encounter note');
            }
        },
        error: function(xhr) {
            const errorMsg = xhr.responseJSON?.message || 'An error occurred while updating the encounter note';
            alert(errorMsg);
        },
        complete: function() {
            $('#saveEncounterEditBtn').prop('disabled', false).html('<i class="fa fa-save"></i> Save Changes');
        }
    });
});

// Cleanup ClassicEditor when modal is closed
$('#editEncounterModal').on('hidden.bs.modal', function() {
    if (editEncounterEditorInstance) {
        editEncounterEditorInstance.destroy()
            .then(() => {
                editEncounterEditorInstance = null;
            })
            .catch(error => {
                console.error('Error destroying editor on modal close:', error);
                editEncounterEditorInstance = null;
            });
    }
});

// Initialize Select2 for the edit modal diagnosis selection
$(document).ready(function() {
    if ($('#editEncounterReasons').length > 0) {
        $('#editEncounterReasons').select2({
            placeholder: "Select Reason(s)",
            allowClear: true,
            tags: true, // Allow custom reasons
            dropdownParent: $('#editEncounterModal') // Important for Select2 in Bootstrap modal
        });
    }

    // Handle Not Applicable Checkbox
    $('#editEncounterNotApplicable').on('change', function() {
        const isChecked = $(this).is(':checked');
        if (isChecked) {
            $('#editEncounterReasonsGroup').hide();
            $('#editEncounterCommentsGroup').hide();
            $('#editEncounterReasons').val(null).trigger('change');
            $('#editEncounterComment1').val('NA');
            $('#editEncounterComment2').val('NA');
        } else {
            $('#editEncounterReasonsGroup').show();
            $('#editEncounterCommentsGroup').show();
        }
    });
});
</script>
