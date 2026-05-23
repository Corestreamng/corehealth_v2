/**
 * Shared Workbench Notes Helper
 * Eliminates code duplication between Nursing and Maternity workbenches
 */
window.WorkbenchNotesKit = {
    editors: {},
    autosaveTimers: {},

    initEditor: function(options) {
        const {
            prefix,
            editorSelector,
            formSelector,
            statusSelector,
            getSaveUrl,
            getMethod,
            getPatientId,
            getEnrollmentId,
            noteTypeId,
            csrfToken,
            onSaveSuccess
        } = options;

        const editorEl = document.querySelector(editorSelector);
        if (!editorEl) return;

        // Return existing editor if already initialized to prevent duplicate instances
        if (this.editors[prefix]) {
            return;
        }

        ClassicEditor
            .create(editorEl, {
                toolbar: {
                    items: [
                        'heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', 
                        '|', 'outdent', 'indent', '|', 'blockQuote', 'insertTable', 'undo', 'redo'
                    ]
                }
            })
            .then(editor => {
                this.editors[prefix] = editor;

                // Wire up autosave on editor data changes
                editor.model.document.on('change:data', () => {
                    clearTimeout(this.autosaveTimers[prefix]);
                    const content = editor.getData();
                    const patientId = typeof getPatientId === 'function' ? getPatientId() : getPatientId;
                    if (!content.trim() || !patientId) return;

                    $(statusSelector).html('<i class="mdi mdi-loading mdi-spin text-warning"></i> <span class="text-warning">Unsaved changes...</span>');

                    this.autosaveTimers[prefix] = setTimeout(() => {
                        const activePatient = typeof getPatientId === 'function' ? getPatientId() : getPatientId;
                        const activeEnrollment = typeof getEnrollmentId === 'function' ? getEnrollmentId() : null;
                        const saveUrl = typeof getSaveUrl === 'function' ? getSaveUrl(activePatient, activeEnrollment) : getSaveUrl;
                        const noteTypeSelect = document.querySelector(`${formSelector} select[name="note_type_id"]`);
                        const resolvedNoteTypeId = noteTypeSelect ? noteTypeSelect.value : (noteTypeId || 5);

                        $.ajax({
                            url: saveUrl,
                            method: 'POST', // autosave is always POST for drafting
                            data: {
                                patient_id: activePatient,
                                note_type_id: resolvedNoteTypeId,
                                note: content,
                                completed: 0
                            },
                            headers: { 'X-CSRF-TOKEN': csrfToken },
                            success: function() {
                                const t = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                                $(statusSelector).html('<i class="mdi mdi-check-circle text-success"></i> <span class="text-success">Autosaved ' + t + '</span>');
                            },
                            error: function() {
                                $(statusSelector).html('<i class="mdi mdi-alert-circle text-danger"></i> <span class="text-danger">Autosave failed</span>');
                            }
                        });
                    }, 3000);
                });
            })
            .catch(error => {
                console.error('Failed to initialize shared CKEditor for prefix ' + prefix, error);
            });

        // Form Submit handler
        $(formSelector).off('submit').on('submit', (e) => {
            e.preventDefault();
            const editor = this.editors[prefix];
            const noteContent = editor ? editor.getData() : '';

            if (!noteContent.trim()) {
                if (typeof showNotification === 'function') {
                    showNotification('error', 'Please enter note content');
                } else if (typeof toastr !== 'undefined') {
                    toastr.error('Please enter note content');
                }
                return;
            }

            const activePatient = typeof getPatientId === 'function' ? getPatientId() : getPatientId;
            const activeEnrollment = typeof getEnrollmentId === 'function' ? getEnrollmentId() : null;
            const saveUrl = typeof getSaveUrl === 'function' ? getSaveUrl(activePatient, activeEnrollment) : getSaveUrl;
            const method = typeof getMethod === 'function' ? getMethod() : 'POST';
            const noteTypeSelect = document.querySelector(`${formSelector} select[name="note_type_id"]`);
            const resolvedNoteTypeId = noteTypeSelect ? noteTypeSelect.value : (noteTypeId || 5);

            const data = {
                patient_id: activePatient,
                note_type_id: resolvedNoteTypeId,
                note: noteContent,
                completed: 1
            };

            $.ajax({
                url: saveUrl,
                method: method,
                data: data,
                headers: { 'X-CSRF-TOKEN': csrfToken },
                success: (response) => {
                    if (typeof showNotification === 'function') {
                        showNotification('success', response.message || 'Note saved successfully');
                    } else if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Note saved successfully');
                    }
                    if (editor) {
                        editor.setData('');
                    }
                    clearTimeout(this.autosaveTimers[prefix]);
                    $(statusSelector).html('');

                    if (typeof onSaveSuccess === 'function') {
                        onSaveSuccess(response);
                    }
                },
                error: (xhr) => {
                    const errMsg = xhr.responseJSON?.message || 'Failed to save note';
                    if (typeof showNotification === 'function') {
                        showNotification('error', errMsg);
                    } else if (typeof toastr !== 'undefined') {
                        toastr.error(errMsg);
                    }
                }
            });
        });
    }
};
