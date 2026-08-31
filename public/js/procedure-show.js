const procedureId = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');
    const patientId = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');
    const labCategoryId = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');
    const imagingCategoryId = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');
    let noteEditorInstance = null;

    /* ═══════════════ TIMERS ═══════════════ */
    if (true) {
        (function() {
            const startMs = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '') * 1000;

            function tick() {
                const elapsed = Math.floor((Date.now() - startMs) / 1000);
                const h = Math.floor(elapsed / 3600);
                const m = Math.floor((elapsed % 3600) / 60);
                const s = elapsed % 60;
                const el = document.getElementById('elapsed-timer');
                if (el) el.textContent = (h > 0 ? h + 'h ' : '') + String(m).padStart(2, '0') + 'm ' + String(s).padStart(2, '0') + 's';
            }
            tick();
            setInterval(tick, 1000);
        })();
    }

    if (true) {
        (function() {
            const targetMs = new Date((window.WORKBENCH_CONFIG?.scheduledDate || '') + 'T' + (window.WORKBENCH_CONFIG?.scheduledTime || '') + ':00').getTime();

            function tick() {
                const diff = targetMs - Date.now();
                const el = document.getElementById('countdown-display');
                if (!el) return;
                if (diff <= 0) {
                    el.textContent = 'Now';
                    return;
                }
                const d = Math.floor(diff / 86400000);
                const h = Math.floor((diff % 86400000) / 3600000);
                const m = Math.floor((diff % 3600000) / 60000);
                el.textContent = (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm';
            }
            tick();
            setInterval(tick, 60000);
        })();
    }

    /* ═══════════════ NOTE TOGGLE ═══════════════ */
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.note-content-preview').forEach(function(el) {
            if (el.scrollHeight > 90) {
                const noteId = el.id.replace('note-preview-', '');
                const toggle = document.getElementById('note-toggle-' + noteId);
                if (toggle) toggle.style.display = 'inline';
            }
        });
    });

    function toggleNotePreview(noteId) {
        const el = document.getElementById('note-preview-' + noteId);
        const toggle = document.getElementById('note-toggle-' + noteId);
        if (!el) return;
        el.classList.toggle('expanded');
        toggle.innerHTML = el.classList.contains('expanded') ?
            'Show less <i class="fa fa-chevron-up"></i>' :
            'Show more <i class="fa fa-chevron-down"></i>';
    }

    /* ═══════════════ NOTE FILTER ═══════════════ */
    function filterNotes(type) {
        document.querySelectorAll('.note-pill').forEach(p => {
            p.classList.toggle('active', p.dataset.filter === type);
        });
        document.querySelectorAll('.note-timeline-item').forEach(function(el) {
            if (type === 'all' || el.dataset.noteType === type) {
                el.style.display = '';
            } else {
                el.style.display = 'none';
            }
        });
    }

    /* ═══════════════ TABS — Default by role ═══════════════ */
    document.addEventListener('DOMContentLoaded', function() {
        if (true) {
        const nurseTab = document.getElementById('tab-consent-billing-link');
        if (nurseTab) $(nurseTab).tab('show');
        } else {
        const docTab = document.getElementById('tab-clinical-link');
        if (docTab) $(docTab).tab('show');
        }
    });

    /* ═══════════════ BILLING KIT ═══════════════ */
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        const target = $(e.target).attr('href');
        if (target === '#proc-orders-services' && window.BillingKit) {
            if (!window._billingKitInitialized) {
                BillingKit.init(window.BILLING_KIT_CONFIG);
                BillingKit.setPatient(patientId);
                window._billingKitInitialized = true;
            }
        }
    });

    /* ═══════════════ DANGER ZONE ═══════════════ */
    function toggleDangerZone() {
        const body = document.getElementById('danger-zone-body');
        const chev = document.getElementById('danger-zone-chevron');
        if (!body) return;
        body.classList.toggle('open');
        if (chev) chev.classList.toggle('fa-chevron-down');
        if (chev) chev.classList.toggle('fa-chevron-up');
    }

    /* ═══════════════ TEAM MODAL ═══════════════ */
    function openAddTeamModal() {
        $('#addTeamForm')[0].reset();
        $('#team_user_id').empty().append('<option value="">-- Select Staff --</option>').trigger('chosen:updated');
        $.get('/api/staff-search', function(data) {
            if (data && data.data) {
                data.data.forEach(function(u) {
                    $('#team_user_id').append('<option value="' + u.id + '">' + u.name + '</option>');
                });
            }
            $('#team_user_id').trigger('chosen:updated');
        });
        $('#addTeamModal').modal('show');
        setTimeout(function() {
            if (!$('#team_user_id').hasClass('chosen-initialized')) {
                $('#team_user_id').chosen({
                    search_contains: true,
                    width: '100%'
                }).addClass('chosen-initialized');
            }
        }, 300);
    }

    function toggleCustomRoleField() {
        const val = $('#team_role').val();
        if (val === 'other') {
            $('#custom-role-group').show();
            $('#team_custom_role').prop('required', true);
        } else {
            $('#custom-role-group').hide();
            $('#team_custom_role').prop('required', false);
        }
    }

    $('#addTeamForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type=submit]');
        btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Adding…');
        const formData = {
            user_id: $('#team_user_id').val(),
            role: $('#team_role').val(),
            custom_role: $('#team_custom_role').val(),
            is_lead: $('#team_is_lead').is(':checked') ? 1 : 0,
            notes: $('#team_notes').val(),
            _token: $('meta[name="csrf-token"]').attr('content'),
        };
        $.ajax({
            url: wbUrl('/patient-procedures/' + procedureId + '/team'),
            method: 'POST',
            data: formData,
            success: function() {
                $('#addTeamModal').modal('hide');
                toastr.success('Team member added.');
                location.reload();
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Error adding team member.';
                toastr.error(msg);
                btn.prop('disabled', false).html('<i class="fa fa-user-plus mr-1"></i>Add Member');
            }
        });
    });

    function removeTeamMember(memberId) {
        if (!confirm('Remove this team member?')) return;
        $.ajax({
            url: wbUrl('/patient-procedures/' + procedureId + '/team/' + memberId),
            method: 'DELETE',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function() {
                $('#team-member-' + memberId).remove();
                toastr.success('Team member removed.');
            },
            error: function() {
                toastr.error('Error removing team member.');
            }
        });
    }

    /* ═══════════════ NOTE MODAL ═══════════════ */
    function openAddNoteModal(noteType) {
        $('#addNoteForm')[0].reset();
        $('#edit_note_id').val('');
        $('#noteModalTitle').html('<i class="fa fa-sticky-note mr-2"></i>Add Procedure Note');
        $('#noteSubmitBtn').html('<i class="fa fa-save mr-1"></i>Save Note');
        if (noteType) $('#note_type').val(noteType);
        $('#addNoteModal').modal('show');
        setTimeout(initializeNoteEditor, 400);
    }

    function initializeNoteEditor() {
        if (noteEditorInstance) {
            try {
                noteEditorInstance.destroy().then(function() {
                    noteEditorInstance = null;
                    createEditor();
                });
            } catch (e) {
                noteEditorInstance = null;
                createEditor();
            }
        } else {
            createEditor();
        }
    }

    function createEditor() {
        ClassicEditor.create(document.querySelector('#note_content'), {
            toolbar: ['heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', '|', 'blockQuote', '|', 'undo', 'redo']
        }).then(function(editor) {
            noteEditorInstance = editor;
        }).catch(function(err) {
            console.error(err);
        });
    }

    $('#addNoteForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#noteSubmitBtn');
        btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Saving…');
        const editId = $('#edit_note_id').val();
        const content = noteEditorInstance ? noteEditorInstance.getData() : $('#note_content').val();
        const formData = {
            note_type: $('#note_type').val(),
            title: $('#note_title').val(),
            content: content,
            _token: $('meta[name="csrf-token"]').attr('content'),
        };
        let url, method;
        if (editId) {
            url = '/patient-procedures/' + procedureId + '/notes/' + editId;
            method = 'PUT';
            formData._method = 'PUT';
        } else {
            url = '/patient-procedures/' + procedureId + '/notes';
            method = 'POST';
        }
        $.ajax({
            url,
            method: 'POST',
            data: formData,
            success: function() {
                $('#addNoteModal').modal('hide');
                toastr.success(editId ? 'Note updated.' : 'Note added.');
                location.reload();
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Error saving note.';
                toastr.error(msg);
                btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save Note');
            }
        });
    });

    function deleteNote(noteId) {
        if (!confirm('Delete this note?')) return;
        $.ajax({
            url: wbUrl('/patient-procedures/' + procedureId + '/notes/' + noteId),
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                _method: 'DELETE'
            },
            success: function() {
                $('#note-' + noteId).remove();
                toastr.success('Note deleted.');
            },
            error: function() {
                toastr.error('Error deleting note.');
            }
        });
    }

    function editNote(noteId) {
        $.get('/patient-procedures/' + procedureId + '/notes/' + noteId + '/edit', function(data) {
            $('#edit_note_id').val(noteId);
            $('#noteModalTitle').html('<i class="fa fa-edit mr-2"></i>Edit Note');
            $('#noteSubmitBtn').html('<i class="fa fa-save mr-1"></i>Update Note');
            $('#note_type').val(data.note_type);
            $('#note_title').val(data.title);
            $('#addNoteModal').modal('show');
            setTimeout(function() {
                initializeNoteEditor();
                setTimeout(function() {
                    if (noteEditorInstance) noteEditorInstance.setData(data.content || '');
                    else $('#note_content').val(data.content || '');
                }, 600);
            }, 400);
        }).fail(function() {
            toastr.error('Error loading note.');
        });
    }

    /* ═══════════════ QUICK NURSING NOTE ═══════════════ */
    function submitQuickNote() {
        const text = $('#quick-note-text').val().trim();
        if (!text) {
            toastr.warning('Please enter a note.');
            return;
        }
        $.ajax({
            url: wbUrl('/patient-procedures/' + procedureId + '/notes'),
            method: 'POST',
            data: {
                note_type: 'nursing',
                title: 'Nursing Note',
                content: text,
                _token: $('meta[name="csrf-token"]').attr('content'),
            },
            success: function() {
                $('#quick-note-text').val('');
                toastr.success('Nursing note added.');
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error saving note.');
            }
        });
    }

    /* ═══════════════ ITEM MODAL ═══════════════ */
    function openAddItemModal(type) {
        $('#addItemForm')[0].reset();
        $('#item_type').val(type);
        $('.item-type-section').hide();
        clearChosenPreview('#item_service_preview');
        clearChosenPreview('#item_product_preview');
        if (type === 'lab') {
            $('#addItemModalTitle').html('<i class="fa fa-flask mr-2 text-primary"></i>Add Lab Request');
            $('#item-service-section').show();
            loadServicesForChosen(labCategoryId, '-- Search Lab Service --');
        } else if (type === 'imaging') {
            $('#addItemModalTitle').html('<i class="fa fa-x-ray mr-2"></i>Add Imaging Request');
            $('#item-service-section').show();
            loadServicesForChosen(imagingCategoryId, '-- Search Imaging Service --');
        } else if (type === 'medication') {
            $('#addItemModalTitle').html('<i class="fa fa-pills mr-2 text-success"></i>Add Medication');
            $('#item-product-section').show();
            loadProductsForChosen();
        }
        $('#addItemModal').modal('show');
    }

    function _num(v, fallback) {
        const n = parseFloat(v);
        return Number.isFinite(n) ? n : (fallback || 0);
    }

    function clearChosenPreview(previewSelector) {
        $(previewSelector).addClass('d-none').html('');
    }

    function renderChosenPreview(previewSelector, payload) {
        if (!payload || !payload.name) {
            clearChosenPreview(previewSelector);
            return;
        }

        const base = _num(payload.base, 0);
        const payable = _num(payload.payable, base);
        const claims = _num(payload.claims, 0);
        const modeRaw = (payload.mode || 'cash').toString();
        const mode = modeRaw.toLowerCase();
        const modeClass = 'mode-' + mode.replace(/[^a-z0-9_-]/g, '');
        const code = payload.code ? '[' + payload.code + ']' : '';
        const stock = payload.stock !== undefined && payload.stock !== null ? `<span class="cr-chip">Stock: ${payload.stock}</span>` : '';

        const html =
            `<div class="cr-title">${payload.name} ${code}</div>` +
            `<div class="cr-meta">` +
            `<span class="cr-base">Base: NGN ${base.toLocaleString()}</span>` +
            `<span class="cr-pay">Pay: NGN ${payable.toLocaleString()}</span>` +
            `<span class="cr-claim">Claim: NGN ${claims.toLocaleString()}</span>` +
            `<span class="cr-mode ${modeClass}">${modeRaw.toUpperCase()}</span>` +
            stock +
            `</div>`;

        $(previewSelector).removeClass('d-none').html(html);
    }

    function bindChosenPreview(selectSelector, previewSelector, kind) {
        $(selectSelector).off('change.richPreview').on('change.richPreview', function() {
            const $opt = $(this).find('option:selected');
            if (!$opt.val()) {
                clearChosenPreview(previewSelector);
                return;
            }
            renderChosenPreview(previewSelector, {
                name: $opt.data('name'),
                code: $opt.data('code'),
                base: $opt.data('base'),
                payable: $opt.data('payable'),
                claims: $opt.data('claims'),
                mode: $opt.data('mode'),
                stock: kind === 'product' ? $opt.data('stock') : null
            });
        });
    }

    function loadServicesForChosen(categoryId, placeholder) {
        const $select = $('#item_service_id');
        $select.empty().append('<option value=""></option>');
        if ($select.data('chosen')) {
            $select.chosen('destroy');
        }
        $.ajax({
            url: wbRoute('live-search-services', '/live-search-services'),
            dataType: 'json',
            data: {
                term: '',
                category_id: categoryId,
                patient_id: patientId
            },
            success: function(data) {
                data.forEach(function(service) {
                    const price = service.price?.sale_price || 0;
                    const payable = service.payable_amount ?? price;
                    const claims = service.claims_amount ?? 0;
                    const mode = (service.coverage_mode || 'cash').toString();
                    const name = service.service_name || 'Unknown';
                    const code = service.service_code || '';
                    const text =
                        name +
                        (code ? ' [' + code + ']' : '') +
                        ' | Base NGN ' + formatMoney(price) +
                        ' | Pay NGN ' + formatMoney(payable) +
                        ' | Claim NGN ' + formatMoney(claims) +
                        ' | ' + mode.toUpperCase();

                    $select.append($('<option>', {
                        value: service.id,
                        text: text,
                        'data-name': name,
                        'data-code': code,
                        'data-base': price,
                        'data-payable': payable,
                        'data-claims': claims,
                        'data-mode': mode
                    }));
                });
                $select.chosen({
                    allow_single_deselect: true,
                    search_contains: true,
                    placeholder_text_single: placeholder,
                    width: '100%'
                });
                bindChosenPreview('#item_service_id', '#item_service_preview', 'service');
            },
            error: function() {
                toastr.error('Failed to load services');
                $select.chosen({
                    placeholder_text_single: placeholder,
                    width: '100%'
                });
                bindChosenPreview('#item_service_id', '#item_service_preview', 'service');
            }
        });
    }

    function loadProductsForChosen() {
        const $select = $('#item_product_id');
        $select.empty().append('<option value=""></option>');
        if ($select.data('chosen')) {
            $select.chosen('destroy');
        }
        $.ajax({
            url: wbRoute('live-search-products', '/live-search-products'),
            dataType: 'json',
            data: {
                term: '',
                patient_id: patientId
            },
            success: function(data) {
                data.forEach(function(product) {
                    const price = product.price?.sale_price ?? product.price?.initial_sale_price ?? 0;
                    const payable = product.payable_amount ?? price;
                    const claims = product.claims_amount ?? 0;
                    const mode = (product.coverage_mode || 'cash').toString();
                    const name = product.product_name || 'Unknown';
                    const code = product.product_code || '';
                    const stock = product.stock?.current_quantity ?? 0;
                    const text =
                        name +
                        (code ? ' [' + code + ']' : '') +
                        ' (' + stock + ' avail.)' +
                        ' | Base NGN ' + formatMoney(price) +
                        ' | Pay NGN ' + formatMoney(payable) +
                        ' | Claim NGN ' + formatMoney(claims) +
                        ' | ' + mode.toUpperCase();

                    $select.append($('<option>', {
                        value: product.id,
                        text: text,
                        'data-name': name,
                        'data-code': code,
                        'data-base': price,
                        'data-payable': payable,
                        'data-claims': claims,
                        'data-mode': mode,
                        'data-stock': stock
                    }));
                });
                $select.chosen({
                    allow_single_deselect: true,
                    search_contains: true,
                    placeholder_text_single: '-- Search Product --',
                    width: '100%'
                });
                bindChosenPreview('#item_product_id', '#item_product_preview', 'product');
            },
            error: function() {
                toastr.error('Failed to load products');
                $select.chosen({
                    placeholder_text_single: '-- Search Product --',
                    width: '100%'
                });
                bindChosenPreview('#item_product_id', '#item_product_preview', 'product');
            }
        });
    }

    function formatMoney(amount) {
        return parseFloat(amount || 0).toLocaleString('en-NG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    $('#item_product_id').on('change', function() {
        const productId = $(this).val();
        if (!productId) {
            $('#item_batch_id').html('<option value="">-- Select product first --</option>');
            return;
        }

        // Prevent fetching batches for free-form items
        if (productId.startsWith('FF_')) {
            $('#item_batch_id').html('<option value="">-- N/A (Free-form) --</option>');
            return;
        }

        $.get(wbRoute('nursing-workbench.product-batches', '/nursing-workbench/product-batches'), {
                product_id: productId,
                store_id: (window.WORKBENCH_CONFIG?.storeId || '')
            },
            function(data) {
                $('#item_batch_id').html('<option value="">-- Select batch --</option>');
                if (data && data.batches) {
                    data.batches.forEach(function(b) {
                        $('#item_batch_id').append('<option value="' + b.id + '">' + b.batch_number + ' (Qty: ' + b.quantity + ')</option>');
                    });
                }
            });
    });

    $('#addItemForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type=submit]');
        btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Adding…');
        const type = $('#item_type').val();
        const formData = {
            item_type: type,
            is_bundled: $('#item_is_bundled').is(':checked') ? 1 : 0,
            notes: $('#item_notes').val(),
            _token: $('meta[name="csrf-token"]').attr('content'),
        };
        if (type === 'medication') {
            formData.product_id = $('#item_product_id').val();
            formData.batch_id = $('#item_batch_id').val();
            formData.qty = $('#item_quantity').val();
        } else {
            formData.service_id = $('#item_service_id').val();
        }
        let submitUrl = '';
        if (formData.item_type === 'lab') submitUrl = '/patient-procedures/' + procedureId + '/items/lab';
        else if (formData.item_type === 'imaging') submitUrl = '/patient-procedures/' + procedureId + '/items/imaging';
        else if (formData.item_type === 'service') submitUrl = '/patient-procedures/' + procedureId + '/items/service';
        else if (formData.item_type === 'medication') submitUrl = '/patient-procedures/' + procedureId + '/items/medication';

        $.ajax({
            url: submitUrl,
            method: 'POST',
            data: formData,
            success: function() {
                $('#addItemModal').modal('hide');
                toastr.success('Item added.');
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error adding item.');
                btn.prop('disabled', false).html('<i class="fa fa-plus mr-1"></i>Add');
            }
        });
    });

    function removeItem(itemId) {
        if (!confirm('Remove this item?')) return;
        $.ajax({
            url: wbUrl('/patient-procedures/' + procedureId + '/items/' + itemId),
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                _method: 'DELETE'
            },
            success: function() {
                $('#item-row-' + itemId).remove();
                toastr.success('Item removed.');
            },
            error: function() {
                toastr.error('Error removing item.');
            }
        });
    }

    /* ═══════════════ CANCEL PROCEDURE ═══════════════ */
    function openCancelModal() {
        $('#cancelProcedureForm')[0].reset();
        $('#cancelProcedureModal').modal('show');
    }

    $('#cancelProcedureForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type=submit]');
        btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Cancelling…');
        $.ajax({
            url: wbUrl('/patient-procedures/' + procedureId + '/cancel'),
            method: 'POST',
            data: {
                cancellation_reason: $('#cancellation_reason').val(),
                refund_amount: $('#refund_amount').val(),
                _token: $('meta[name="csrf-token"]').attr('content'),
            },
            success: function() {
                $('#cancelProcedureModal').modal('hide');
                toastr.success('Procedure cancelled.');
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error cancelling procedure.');
                btn.prop('disabled', false).html('<i class="fa fa-ban mr-1"></i>Cancel Procedure');
            }
        });
    });

    /* ═══════════════ OUTCOME ═══════════════ */
    function toggleOutcomeEdit() {
        $('#outcome-display').toggle();
        $('#outcome-form-wrapper').toggle();
    }

    $('#outcome-form').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type=submit]');
        btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Saving…');
        $.ajax({
            url: wbUrl('/patient-procedures/' + procedureId + '/outcome'),
            method: 'POST',
            data: {
                outcome: $('#outcome').val(),
                outcome_notes: $('#outcome_notes').val(),
                _method: 'PUT',
                _token: $('meta[name="csrf-token"]').attr('content'),
            },
            success: function() {
                toastr.success('Outcome saved.');
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error saving outcome.');
                btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save Outcome');
            }
        });
    });

    /* ═══════════════ STATUS ═══════════════ */
    function updateStatus(status) {
        $.ajax({
            url: wbUrl('/patient-procedures/' + procedureId),
            method: 'POST',
            data: {
                procedure_status: status,
                _method: 'PUT',
                _token: $('meta[name="csrf-token"]').attr('content'),
            },
            success: function() {
                toastr.success('Status updated.');
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error updating status.');
            }
        });
    }

    function completeProcedure() {
        $.ajax({
            url: wbUrl('/patient-procedures/' + procedureId + '/complete'),
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function() {
                toastr.success('Procedure marked as completed.');
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error completing procedure.');
            }
        });
    }

    function confirmAction(action, title, message, subText, colorClass, icon) {
        $('#confirmActionHeader').attr('class', 'modal-header bg-' + colorClass + (colorClass === 'warning' || colorClass === 'secondary' ? ' text-dark' : ' text-white'));
        $('#confirmActionTitle').html('<i class="fa fa-' + icon + ' mr-2"></i>' + title);
        $('#confirmActionMessage').text(message);
        $('#confirmActionSub').text(subText || '');
        $('#confirmActionBtn')
            .attr('class', 'btn btn-' + colorClass)
            .off('click')
            .on('click', function() {
                $('#confirmActionModal').modal('hide');
                if (action === 'complete') {
                    completeProcedure();
                } else {
                    updateStatus(action);
                }
            });
        $('#confirmActionModal').modal('show');
    }

    /* ═══════════════ SCHEDULE MODAL (NEW) ═══════════════ */
    function openScheduleModal() {
        $('#scheduleProcedureModal').modal('show');
    }

    $('#btn-submit-schedule').on('click', function() {
        const btn = $(this);
        const scheduled_date = $('#schedule_date').val();
        const scheduled_time = $('#schedule_time').val();
        const operating_room = $('#operating_room').val();
        if (!scheduled_date || !scheduled_time || !operating_room) {
            toastr.warning('Please fill all required fields.');
            return;
        }
        btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Saving…');
        $.ajax({
            url: wbUrl('/patient-procedures/' + procedureId),
            method: 'POST',
            data: {
                procedure_status: 'scheduled',
                scheduled_date: scheduled_date,
                scheduled_time: scheduled_time,
                operating_room: operating_room,
                _method: 'PUT',
                _token: $('meta[name="csrf-token"]').attr('content'),
            },
            success: function() {
                $('#scheduleProcedureModal').modal('hide');
                toastr.success('Procedure scheduled.');
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error scheduling procedure.');
                btn.prop('disabled', false).html('<i class="fa fa-calendar-check mr-1"></i>Confirm Schedule');
            }
        });
    });

    /* ═══════════════ PRINT ═══════════════ */
    function openPrintSelectionModal() {
        $('#printSelectionModal').modal('show');
    }

    function selectAllPrintOptions() {
        $('.print-option').prop('checked', true);
    }

    function executePrint() {
        const sections = [];
        $('.print-option:checked').each(function() {
            sections.push($(this).val());
        });
        if (sections.length === 0) {
            toastr.warning('Select at least one section.');
            return;
        }
        const params = new URLSearchParams();
        sections.forEach(s => params.append('sections[]', s));
        window.open('/patient-procedures/' + procedureId + '/print?' + params.toString(), '_blank');
        $('#printSelectionModal').modal('hide');
    }

    /* ═══════════════ DATATABLES ═══════════════ */
    function initLabHistoryTable() {
        if ($.fn.DataTable.isDataTable("#procedure_lab_history")) return;
        $("#procedure_lab_history").DataTable({
            ajax: {
                url: "(window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '')",
                type: "GET"
            },
            columns: [{
                data: "info"
            }],
            pageLength: 10,
            dom: '<"d-flex justify-content-between align-items-center px-3 pt-2"f>t<"d-flex justify-content-between align-items-center px-3"ip>',
            language: {
                search: "",
                searchPlaceholder: "Search labs…",
                emptyTable: "No lab requests."
            },
        });
    }

    function initImagingHistoryTable() {
        if ($.fn.DataTable.isDataTable("#procedure_imaging_history")) return;
        $("#procedure_imaging_history").DataTable({
            ajax: {
                url: "(window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '')",
                type: "GET"
            },
            columns: [{
                data: "info"
            }],
            pageLength: 10,
            dom: '<"d-flex justify-content-between align-items-center px-3 pt-2"f>t<"d-flex justify-content-between align-items-center px-3"ip>',
            language: {
                search: "",
                searchPlaceholder: "Search imaging…",
                emptyTable: "No imaging requests."
            },
        });
    }

    function initMedsHistoryTable() {
        if ($.fn.DataTable.isDataTable("#procedure_meds_history")) return;
        $("#procedure_meds_history").DataTable({
            ajax: {
                url: "(window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '')",
                type: "GET"
            },
            columns: [{
                data: "info"
            }],
            pageLength: 10,
            dom: '<"d-flex justify-content-between align-items-center px-3 pt-2"f>t<"d-flex justify-content-between align-items-center px-3"ip>',
            language: {
                search: "",
                searchPlaceholder: "Search meds…",
                emptyTable: "No medication requests."
            },
        });
    }

    $('a[href="#proc-orders-labs"]').on("shown.bs.tab", function() {
        initLabHistoryTable();
    });
    $("#tab-orders-link").on("shown.bs.tab", function() {
        initLabHistoryTable();
    });
    $('a[href="#proc-orders-imaging"]').on("shown.bs.tab", function() {
        initImagingHistoryTable();
    });
    $('a[href="#proc-orders-meds"]').on("shown.bs.tab", function() {
        initMedsHistoryTable();
    });

    function openConsentModal() {
        $("#consentModal").modal("show");
    }

    let canvas, ctx;
    let isDrawing = false;
    let hasDrawnSignature = false;
    let lastX = 0;
    let lastY = 0;

    function initSignatureCanvas() {
        canvas = document.getElementById('signatureCanvas');
        if (!canvas) return;

        ctx = canvas.getContext('2d');

        // Reset sizes properly based on CSS layout
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;

        // High-resolution canvas scaling
        const devicePixelRatio = window.devicePixelRatio || 1;
        canvas.width = rect.width * devicePixelRatio;
        canvas.height = rect.height * devicePixelRatio;
        ctx.scale(devicePixelRatio, devicePixelRatio);

        // Elegant drawing styles for realistic ink flow
        ctx.strokeStyle = '#1a568c'; // Deep navy blue clinical ink
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        // Mouse drawing event listeners
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);

        // Mobile touch drawing event listeners
        canvas.addEventListener('touchstart', function(e) {
            e.preventDefault();
            const touch = e.touches[0];
            const rect = canvas.getBoundingClientRect();
            startDrawing({
                clientX: touch.clientX,
                clientY: touch.clientY
            });
        });

        canvas.addEventListener('touchmove', function(e) {
            e.preventDefault();
            const touch = e.touches[0];
            draw({
                clientX: touch.clientX,
                clientY: touch.clientY
            });
        });

        canvas.addEventListener('touchend', stopDrawing);
    }

    function startDrawing(e) {
        isDrawing = true;
        const rect = canvas.getBoundingClientRect();

        // Hide visual placeholder overlay
        const placeholder = document.getElementById('canvasPlaceholderText');
        if (placeholder) placeholder.style.display = 'none';

        // Calculate accurate coordinate relative to client canvas bounds
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        lastX = x;
        lastY = y;
    }

    function draw(e) {
        if (!isDrawing) return;

        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(x, y);
        ctx.stroke();

        lastX = x;
        lastY = y;
        hasDrawnSignature = true;
    }

    function stopDrawing() {
        isDrawing = false;
    }

    function clearSignatureCanvas() {
        if (!canvas) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const placeholder = document.getElementById('canvasPlaceholderText');
        if (placeholder) placeholder.style.display = 'flex';
        hasDrawnSignature = false;
    }

    function generateCursiveSignature() {
        const nameInput = document.getElementById('signee_name').value.trim();
        if (!nameInput) {
            toastr.warning('Please enter a signee name first to generate a cursive signature.');
            return;
        }

        clearSignatureCanvas();

        // Hide visual placeholder overlay
        const placeholder = document.getElementById('canvasPlaceholderText');
        if (placeholder) placeholder.style.display = 'none';

        const devicePixelRatio = window.devicePixelRatio || 1;
        const logicalWidth = canvas.width / devicePixelRatio;
        const logicalHeight = canvas.height / devicePixelRatio;

        ctx.strokeStyle = '#1d68a7'; // Premium clinic blue ink
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        // Write elegant cursive text
        ctx.font = 'italic 32px "Brush Script MT", "Lucida Handwriting", "Zapfino", "cursive"';
        ctx.fillStyle = '#1d68a7';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        const words = nameInput.split(' ');
        let signatureText = nameInput;
        if (words.length > 1) {
            signatureText = words[0] + ' ' + words[words.length - 1];
        }

        ctx.fillText(signatureText, logicalWidth / 2, logicalHeight / 2 - 10);

        // Add cursive swash line
        ctx.beginPath();
        ctx.moveTo(logicalWidth / 2 - 90, logicalHeight / 2 + 15);
        ctx.quadraticCurveTo(logicalWidth / 2, logicalHeight / 2 + 28, logicalWidth / 2 + 100, logicalHeight / 2 + 12);
        ctx.stroke();

        hasDrawnSignature = true;
    }

    let consentEditorInstance = null;

    function createConsentEditor() {
        if (typeof ClassicEditor === 'undefined') return;

        ClassicEditor.create(document.querySelector('#consent_template_editor_modal'), {
            toolbar: ['heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', '|', 'blockQuote', '|', 'undo', 'redo']
        }).then(function(editor) {
            consentEditorInstance = editor;
        }).catch(function(err) {
            console.error(err);
        });
    }

    // Bootstrap modal show hook to initialize canvas safely after rendering
    $('#consentModal').on('shown.bs.modal', function() {
        initSignatureCanvas();
        hasDrawnSignature = false;

        if (consentEditorInstance) {
            try {
                consentEditorInstance.destroy().then(createConsentEditor);
            } catch (e) {
                createConsentEditor();
            }
        } else {
            createConsentEditor();
        }
    });

    // Clean up CKEditor on modal close
    $('#consentModal').on('hidden.bs.modal', function() {
        if (consentEditorInstance) {
            try {
                consentEditorInstance.destroy().then(function() {
                    consentEditorInstance = null;
                });
            } catch (e) {
                consentEditorInstance = null;
            }
        }
    });

    function submitDigitalConsent() {
        const signeeName = $('#signee_name').val().trim();
        const relationship = $('#signee_relationship').val();
        const notes = $('#signature_notes').val().trim();

        if (!signeeName) {
            toastr.warning('Please enter the signee printed name.');
            return;
        }

        if (!hasDrawnSignature) {
            toastr.warning('Please sign on the signature canvas before proceeding.');
            return;
        }

        const consentText = consentEditorInstance ? consentEditorInstance.getData() : $('#consent_template_editor_modal').val();
        const signatureDataUrl = canvas.toDataURL('image/png');
        const btn = $('#submit-digital-signature-btn');

        btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-2"></i>Generating Branded PDF…');

        $.ajax({
            url: wbUrl('/patient-procedures/' + procedureId + '/consent/sign'),
            method: 'POST',
            data: {
                signee_name: signeeName,
                relationship: relationship,
                notes: notes,
                consent_text: consentText,
                signature: signatureDataUrl,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (consentEditorInstance) {
                    try {
                        consentEditorInstance.destroy();
                        consentEditorInstance = null;
                    } catch (e) {}
                }
                $('#consentModal').modal('hide');
                toastr.success('Digital consent signed and branded PDF created successfully!');
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error signing digital consent.');
                btn.prop('disabled', false).html('<i class="fa fa-check mr-2"></i>Sign & Obtain Consent');
            }
        });
    }

    /* ═══════════════ ATTACHMENTS ═══════════════ */
    $('#upload-attachment-form').on('submit', function(e) {
        e.preventDefault();
        const fileInput = document.getElementById('attachment-file');
        if (!fileInput.files.length) {
            toastr.warning('Please select a file.');
            return;
        }
        const btn = $(this).find('button[type=submit]');
        btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Uploading…');
        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('label', $('#attachment-label').val());
        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
        $.ajax({
            url: wbUrl('/patient-procedures/' + procedureId + '/attachments'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function() {
                toastr.success('Attachment uploaded.');
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Upload failed.');
                btn.prop('disabled', false).html('<i class="fa fa-upload mr-1"></i>Upload');
            }
        });
    });

    function deleteAttachment(attId) {
        if (!confirm('Delete this attachment?')) return;
        executeDeleteAttachment(attId);
    }

    function executeDeleteAttachment(attId) {
        $.ajax({
            url: wbUrl('/patient-procedures/' + procedureId + '/attachments/' + attId),
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                _method: 'DELETE'
            },
            success: function() {
                $('#attachment-row-' + attId).remove();
                toastr.success('Attachment deleted.');
            },
            error: function() {
                toastr.error('Error deleting attachment.');
            }
        });
    }

    function promptProcedureFreeFormService() {
        Swal.fire({
            title: 'Add Free-Form Service',
            text: 'Enter the exact name of the lab/imaging request:',
            input: 'text',
            showCancelButton: true,
            confirmButtonText: 'Add',
            target: document.getElementById('addItemModal'),
            inputValidator: function(value) {
                if (!value) return 'You need to write something!';
            }
        }).then((result) => {
            if (result.isConfirmed) {
                let val = result.value;
                let combined = 'FF_' + val + ' [Free-form]';
                let newOption = new Option(val + ' (Free-form)', combined, true, true);
                $('#item_service_id').append(newOption).trigger('chosen:updated').trigger('change');

                let html = `
                <div class="d-flex align-items-center p-2 mt-2" style="border: 1px dashed #6c757d; border-radius: 4px; background: #f8f9fa;">
                    <div class="mr-3">
                        <i class="fa fa-file-alt text-secondary fa-2x"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">${val} <span class="badge bg-secondary ms-1">Free-Form</span></h6>
                        <small class="text-muted d-block">External or Unlisted Request</small>
                        <small class="text-success"><i class="fa fa-check-circle"></i> Ready to process</small>
                    </div>
                </div>
            `;
                $('#item_service_preview').removeClass('d-none').html(html);
                toastr.success('Free-form service set.');
            }
        });
    }

    function promptProcedureFreeFormProduct() {
        Swal.fire({
            title: 'Add Free-Form Medication',
            text: 'Enter the exact name of the medication:',
            input: 'text',
            showCancelButton: true,
            confirmButtonText: 'Add',
            target: document.getElementById('addItemModal'),
            inputValidator: function(value) {
                if (!value) return 'You need to write something!';
            }
        }).then((result) => {
            if (result.isConfirmed) {
                let val = result.value;
                let combined = 'FF_' + val + ' [Free-form]';
                let newOption = new Option(val + ' (Free-form)', combined, true, true);
                $('#item_product_id').append(newOption).trigger('chosen:updated').trigger('change');

                // For free form, there's no batch validation
                $('#item_batch_id').html('<option value="">-- N/A (Free-form) --</option>');

                let html = `
                <div class="d-flex align-items-center p-2 mt-2" style="border: 1px dashed #6c757d; border-radius: 4px; background: #f8f9fa;">
                    <div class="mr-3">
                        <i class="fa fa-file-alt text-secondary fa-2x"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">${val} <span class="badge bg-secondary ms-1">Free-Form</span></h6>
                        <small class="text-muted d-block">External or Unlisted Medication</small>
                        <small class="text-success"><i class="fa fa-check-circle"></i> Ready to dispense</small>
                    </div>
                </div>
            `;
                $('#item_product_preview').removeClass('d-none').html(html);

                toastr.success('Free-form medication set.');
            }
        });
    }

    function enterLabResult(requestId) {
        window._investResultContext = {
            type: 'lab',
            id: requestId
        };
        InvestResultEntry.enterResult(
            requestId,
            `/lab-workbench/lab-service-requests/${requestId}`,
            `/lab-workbench/lab-service-requests/${requestId}/attachments`,
            wbRoute('lab.saveResult', '/lab/saveResult')
        );
    }

    function editLabResult(obj) {
        const requestId = $(obj).data('id');
        InvestResultEntry.editResult(
            requestId,
            `/lab-workbench/lab-service-requests/${requestId}`,
            `/lab-workbench/lab-service-requests/${requestId}/attachments`,
            wbRoute('lab.saveResult', '/lab/saveResult')
        );
    }

    function enterImagingResult(requestId) {
        window._investResultContext = {
            type: 'imaging',
            id: requestId
        };
        InvestResultEntry.enterResult(
            requestId,
            `/imaging-workbench/imaging-service-requests/${requestId}`,
            `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
            wbRoute('imaging.saveResult', '/imaging/saveResult')
        );
    }

    function editImagingResult(obj) {
        const requestId = $(obj).data('id');
        InvestResultEntry.editResult(
            requestId,
            `/imaging-workbench/imaging-service-requests/${requestId}`,
            `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
            wbRoute('imaging.saveResult', '/imaging/saveResult')
        );
    }

    function dispenseFreeFormMed(requestId) {
        Swal.fire({
            title: 'Mark as Dispensed',
            text: 'Enter the quantity dispensed:',
            input: 'number',
            inputValue: 1,
            inputAttributes: {
                min: 1,
                step: 1
            },
            showCancelButton: true,
            confirmButtonText: 'Record as Dispensed',
            showLoaderOnConfirm: true,
            preConfirm: (qty) => {
                return $.ajax({
                    url: wbUrl('/pharmacy-workbench/dispense-free-form'),
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        request_id: requestId,
                        qty_dispensed: qty
                    }
                }).catch(error => {
                    Swal.showValidationMessage(
                        `Request failed: ${error.responseJSON?.message || error.statusText}`
                    );
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire('Success', 'Medication marked as dispensed.', 'success');
                if ($.fn.DataTable.isDataTable('#procedure_meds_history')) {
                    $('#procedure_meds_history').DataTable().ajax.reload(null, false);
                }
            }
        });
    }
