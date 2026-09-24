if (typeof window.wbUrl !== 'function') {
    window.wbUrl = function(path) {
        var base = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.baseUrl) ? window.WORKBENCH_CONFIG.baseUrl : '';
        base = base.replace(/\/$/, '');
        var cleanPath = (path || '').replace(/^\//, '');
        return base ? (base + '/' + cleanPath) : ('/' + cleanPath);
    };
}
if (typeof window.wbRoute !== 'function') {
    window.wbRoute = function(name, fallbackPath) {
        if (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.routes) {
            if (window.WORKBENCH_CONFIG.routes[name]) return window.WORKBENCH_CONFIG.routes[name];
            var dotKey = name.replace(/_/g, '.');
            if (window.WORKBENCH_CONFIG.routes[dotKey]) return window.WORKBENCH_CONFIG.routes[dotKey];
            var underscoreKey = name.replace(/\./g, '_');
            if (window.WORKBENCH_CONFIG.routes[underscoreKey]) return window.WORKBENCH_CONFIG.routes[underscoreKey];
        }
        return window.wbUrl(fallbackPath || '');
    };
}

const procedureId = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.procedureId : '');
    const patientId = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');
    const labCategoryId = (window.WORKBENCH_CONFIG ? (window.WORKBENCH_CONFIG.investigationCategoryId || '') : '');
    const imagingCategoryId = (window.WORKBENCH_CONFIG ? (window.WORKBENCH_CONFIG.imagingCategoryId || '') : '');
    let noteEditorInstance = null;

    /* ═══════════════ TIMERS ═══════════════ */
    if (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.actualStartTime) {
        (function() {
            const startMs = parseInt(window.WORKBENCH_CONFIG.actualStartTime, 10) * 1000;
            if (!startMs || isNaN(startMs)) return;

            function tick() {
                const elapsed = Math.max(0, Math.floor((Date.now() - startMs) / 1000));
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

    if (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.scheduledDate) {
        (function() {
            const timeStr = window.WORKBENCH_CONFIG.scheduledTime ? (window.WORKBENCH_CONFIG.scheduledTime.length === 5 ? window.WORKBENCH_CONFIG.scheduledTime + ':00' : window.WORKBENCH_CONFIG.scheduledTime) : '00:00:00';
            const targetMs = new Date(window.WORKBENCH_CONFIG.scheduledDate + 'T' + timeStr).getTime();
            if (isNaN(targetMs)) return;

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
        const pageRole = window.WORKBENCH_CONFIG?.pageRole || document.querySelector('.procedure-page')?.dataset?.pageRole || '';
        if (pageRole === 'nurse') {
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
        if (formData.item_type === 'lab') submitUrl = (window.WORKBENCH_CONFIG?.addLabRoute) || wbUrl('/patient-procedures/' + procedureId + '/items/lab');
        else if (formData.item_type === 'imaging') submitUrl = (window.WORKBENCH_CONFIG?.addImagingRoute) || wbUrl('/patient-procedures/' + procedureId + '/items/imaging');
        else if (formData.item_type === 'service') submitUrl = (window.WORKBENCH_CONFIG?.addServiceRoute) || wbUrl('/patient-procedures/' + procedureId + '/items/service');
        else if (formData.item_type === 'medication') submitUrl = (window.WORKBENCH_CONFIG?.addConsumableRoute) || wbUrl('/patient-procedures/' + procedureId + '/items/medication');

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
        const url = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.labHistoryRoute)
            ? window.WORKBENCH_CONFIG.labHistoryRoute
            : wbUrl('/patient-procedures/' + procedureId + '/lab-history');
        $("#procedure_lab_history").DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            dom: '<"top"f>rt<"bottom"lip><"clear">',
            ajax: {
                url: url,
                type: "GET"
            },
            columns: [{
                data: "info",
                name: "info",
                orderable: false,
                searchable: true
            }],
            order: [[0, 'desc']],
            pageLength: 10,
            lengthMenu: [[5, 10, 25], [5, 10, 25]],
            language: {
                search: "",
                searchPlaceholder: "Search labs…",
                emptyTable: "<div class='text-center text-muted py-4'><i class='fa fa-flask fa-2x mb-2 d-block'></i>No lab requests for this procedure</div>",
                processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
            },
        });
    }

    function initImagingHistoryTable() {
        if ($.fn.DataTable.isDataTable("#procedure_imaging_history")) return;
        const url = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.imagingHistoryRoute)
            ? window.WORKBENCH_CONFIG.imagingHistoryRoute
            : wbUrl('/patient-procedures/' + procedureId + '/imaging-history');
        $("#procedure_imaging_history").DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            dom: '<"top"f>rt<"bottom"lip><"clear">',
            ajax: {
                url: url,
                type: "GET"
            },
            columns: [{
                data: "info",
                name: "info",
                orderable: false,
                searchable: true
            }],
            order: [[0, 'desc']],
            pageLength: 10,
            lengthMenu: [[5, 10, 25], [5, 10, 25]],
            language: {
                search: "",
                searchPlaceholder: "Search imaging…",
                emptyTable: "<div class='text-center text-muted py-4'><i class='fa fa-x-ray fa-2x mb-2 d-block'></i>No imaging requests for this procedure</div>",
                processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
            },
        });
    }

    function initMedsHistoryTable() {
        if ($.fn.DataTable.isDataTable("#procedure_meds_history")) return;
        const url = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.medicationHistoryRoute)
            ? window.WORKBENCH_CONFIG.medicationHistoryRoute
            : wbUrl('/patient-procedures/' + procedureId + '/medication-history');
        $("#procedure_meds_history").DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            dom: '<"top"f>rt<"bottom"lip><"clear">',
            ajax: {
                url: url,
                type: "GET"
            },
            columns: [{
                data: "info",
                name: "info",
                orderable: false,
                searchable: true
            }],
            order: [[0, 'desc']],
            pageLength: 10,
            lengthMenu: [[5, 10, 25], [5, 10, 25]],
            language: {
                search: "",
                searchPlaceholder: "Search meds…",
                emptyTable: "<div class='text-center text-muted py-4'><i class='fa fa-pills fa-2x mb-2 d-block'></i>No medications for this procedure</div>",
                processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
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
            id: requestId,
            source: 'procedure'
        };
        InvestResultEntry.enterResult(
            requestId,
            `/lab-workbench/lab-service-requests/${requestId}`,
            `/lab-workbench/lab-service-requests/${requestId}/attachments`,
            wbRoute('lab.saveResult', '/lab/saveResult'),
            'procedure'
        );
    }

    function editLabResult(obj) {
        const requestId = $(obj).data('id');
        InvestResultEntry.editResult(
            requestId,
            `/lab-workbench/lab-service-requests/${requestId}`,
            `/lab-workbench/lab-service-requests/${requestId}/attachments`,
            wbRoute('lab.saveResult', '/lab/saveResult'),
            'procedure'
        );
    }

    function enterImagingResult(requestId) {
        window._investResultContext = {
            type: 'imaging',
            id: requestId,
            source: 'procedure'
        };
        InvestResultEntry.enterResult(
            requestId,
            `/imaging-workbench/imaging-service-requests/${requestId}`,
            `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
            wbRoute('imaging.saveResult', '/imaging/saveResult'),
            'procedure'
        );
    }

    function editImagingResult(obj) {
        const requestId = $(obj).data('id');
        InvestResultEntry.editResult(
            requestId,
            `/imaging-workbench/imaging-service-requests/${requestId}`,
            `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
            wbRoute('imaging.saveResult', '/imaging/saveResult'),
            'procedure'
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

    /* ═══════════════════════════════════════════════════════════════
       PROCEDURE BASE FEE PRICING & TARIFF GUIDE MODAL
       ═══════════════════════════════════════════════════════════════ */

    let currentTariffGuide = null;

    function formatCurrency(num) {
        const n = parseFloat(num) || 0;
        return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function openBillBaseFeeModal() {
        const modal = $('#billBaseFeeModal');
        if (!modal.length) return;

        // Reset form & states
        $('#bbf_alert_area').empty();
        $('#bbf_submit_btn').prop('disabled', false);
        $('#bbf_patient_type_badge').text('Loading...').attr('class', 'badge bg-secondary text-white');
        $('#bbf_tariff_details').html('<i class="fa fa-spinner fa-spin mr-1"></i> Loading tariff benchmark guide...');
        $('#bbf_btn_apply_tariff').hide();

        modal.modal('show');

        const procId = window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.procedureId : '';
        const guideUrl = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.tariffGuideRoute)
            ? window.WORKBENCH_CONFIG.tariffGuideRoute
            : wbUrl(`/patient-procedures/${procId}/tariff-guide`);

        $.ajax({
            url: guideUrl,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.csrf : '')
            },
            success: function(resp) {
                if (!resp || !resp.success) {
                    $('#bbf_tariff_details').html('<span class="text-danger">Failed to load tariff guide information.</span>');
                    return;
                }

                currentTariffGuide = resp;
                renderTariffGuideDetails(resp);

                // Pre-populate fields
                if (resp.is_billed && resp.billing) {
                    $('#bbf_total_price').val(resp.billing.total_amount);
                    $('#bbf_coverage_mode').val(resp.billing.coverage_mode || 'cash');
                    $('#bbf_payable_amount').val(resp.billing.payable_amount);
                    $('#bbf_claims_amount').val(resp.billing.claims_amount);
                    $('#bbf_auth_code').val(resp.billing.auth_code || '');
                    $('#bbf_submit_text').text('Update Base Fee Bill');
                } else {
                    // Not billed yet -> default to benchmark
                    if (resp.patient.is_hmo && resp.hmo_tariff && resp.hmo_tariff.has_tariff) {
                        const tariffTotal = (resp.hmo_tariff.payable_amount || 0) + (resp.hmo_tariff.claims_amount || 0);
                        $('#bbf_total_price').val(tariffTotal);
                        $('#bbf_coverage_mode').val(resp.hmo_tariff.coverage_mode || 'primary');
                        $('#bbf_payable_amount').val(resp.hmo_tariff.payable_amount || 0);
                        $('#bbf_claims_amount').val(resp.hmo_tariff.claims_amount || 0);
                    } else {
                        const catPrice = resp.service ? (resp.service.catalog_price || 0) : 0;
                        $('#bbf_total_price').val(catPrice);
                        $('#bbf_coverage_mode').val(resp.patient.is_hmo ? 'primary' : 'cash');
                        $('#bbf_payable_amount').val(resp.patient.is_hmo ? 0 : catPrice);
                        $('#bbf_claims_amount').val(resp.patient.is_hmo ? catPrice : 0);
                    }
                    $('#bbf_auth_code').val('');
                    $('#bbf_submit_text').text('Confirm & Bill Base Fee');
                }

                onBbfCoverageModeChange();
            },
            error: function(err) {
                $('#bbf_tariff_details').html('<span class="text-danger"><i class="fa fa-exclamation-triangle mr-1"></i>Could not load live tariff guide.</span>');
            }
        });
    }

    function renderTariffGuideDetails(data) {
        const catPrice = data.service ? data.service.catalog_price : 0;

        if (data.patient.is_hmo) {
            $('#bbf_patient_type_badge').text(`HMO: ${data.patient.hmo_name || 'Enrolled'}`).attr('class', 'badge bg-info text-white');

            if (data.hmo_tariff && data.hmo_tariff.has_tariff) {
                const totalTariff = (data.hmo_tariff.payable_amount || 0) + (data.hmo_tariff.claims_amount || 0);
                $('#bbf_tariff_details').html(`
                    <div class="d-flex flex-wrap gap-3 align-items-center">
                        <div><strong>HMO Tariff:</strong> <span class="text-primary font-weight-bold">₦${formatCurrency(totalTariff)}</span></div>
                        <div><strong>Patient Co-Pay:</strong> ₦${formatCurrency(data.hmo_tariff.payable_amount)}</div>
                        <div><strong>HMO Claims:</strong> ₦${formatCurrency(data.hmo_tariff.claims_amount)}</div>
                        <div><strong>Mode:</strong> <span class="badge bg-light text-dark border">${(data.hmo_tariff.coverage_mode || 'primary').toUpperCase()}</span></div>
                        <div class="text-muted small">| Catalog Price: ₦${formatCurrency(catPrice)}</div>
                    </div>
                `);
                $('#bbf_btn_apply_tariff').show();
            } else {
                $('#bbf_tariff_details').html(`
                    <div>
                        <span class="text-warning"><i class="fa fa-info-circle mr-1"></i>No tariff item mapped for this HMO on this procedure service.</span><br>
                        <strong>Standard Catalog Base Price:</strong> <span class="text-dark font-weight-bold">₦${formatCurrency(catPrice)}</span>
                    </div>
                `);
                $('#bbf_btn_apply_tariff').show();
            }
        } else {
            $('#bbf_patient_type_badge').text('Self-Pay / Cash Patient').attr('class', 'badge bg-secondary text-white');
            $('#bbf_tariff_details').html(`
                <div>
                    <strong>Hospital Catalog Base Price:</strong> <span class="text-primary font-weight-bold">₦${formatCurrency(catPrice)}</span>
                </div>
            `);
            $('#bbf_btn_apply_tariff').show();
        }
    }

    function applyTariffBenchmark() {
        if (!currentTariffGuide) return;

        const data = currentTariffGuide;
        const catPrice = data.service ? data.service.catalog_price : 0;

        if (data.patient.is_hmo && data.hmo_tariff && data.hmo_tariff.has_tariff) {
            const totalTariff = (data.hmo_tariff.payable_amount || 0) + (data.hmo_tariff.claims_amount || 0);
            $('#bbf_total_price').val(totalTariff);
            $('#bbf_coverage_mode').val(data.hmo_tariff.coverage_mode || 'primary');
            $('#bbf_payable_amount').val(data.hmo_tariff.payable_amount || 0);
            $('#bbf_claims_amount').val(data.hmo_tariff.claims_amount || 0);
        } else {
            $('#bbf_total_price').val(catPrice);
            $('#bbf_coverage_mode').val(data.patient.is_hmo ? 'primary' : 'cash');
            $('#bbf_payable_amount').val(data.patient.is_hmo ? 0 : catPrice);
            $('#bbf_claims_amount').val(data.patient.is_hmo ? catPrice : 0);
        }

        onBbfCoverageModeChange();
    }

    function onBbfCoverageModeChange() {
        const mode = $('#bbf_coverage_mode').val();
        const total = parseFloat($('#bbf_total_price').val()) || 0;

        if (mode === 'cash') {
            $('#bbf_auth_code_row').hide();
            $('#bbf_claims_col').hide();
            $('#bbf_payable_amount').val(total);
            $('#bbf_claims_amount').val(0);
            $('#bbf_coverage_hint').text('100% Patient self-pay. No HMO claim will be logged.');
            $('#bbf_preset_hmo_btn').prop('disabled', true).addClass('opacity-50');
            $('#bbf_preset_split_btn').prop('disabled', true).addClass('opacity-50');
        } else {
            $('#bbf_auth_code_row').show();
            $('#bbf_claims_col').show();
            $('#bbf_preset_hmo_btn').prop('disabled', false).removeClass('opacity-50');
            $('#bbf_preset_split_btn').prop('disabled', false).removeClass('opacity-50');

            if (mode === 'express') {
                $('#bbf_coverage_hint').text('Auto-approved HMO coverage. Bill drops as approved claims into Cashier.');
            } else if (mode === 'primary') {
                $('#bbf_coverage_hint').text('Standard HMO coverage. Claims amount queued for desk verification.');
            } else if (mode === 'secondary') {
                $('#bbf_coverage_hint').text('Specialist secondary HMO coverage requiring Pre-Auth Code.');
            }
        }
    }

    function onBbfTotalOrSplitChange(source) {
        const mode = $('#bbf_coverage_mode').val();
        const total = parseFloat($('#bbf_total_price').val()) || 0;

        if (mode === 'cash') {
            $('#bbf_payable_amount').val(total);
            $('#bbf_claims_amount').val(0);
            return;
        }

        if (source === 'total') {
            const currentPayable = parseFloat($('#bbf_payable_amount').val()) || 0;
            const currentClaims = parseFloat($('#bbf_claims_amount').val()) || 0;

            if (currentClaims === 0 && currentPayable > 0) {
                $('#bbf_payable_amount').val(total);
            } else if (currentPayable === 0 && currentClaims > 0) {
                $('#bbf_claims_amount').val(total);
            } else {
                // Keep payable, adjust claims
                const newClaims = Math.max(0, total - currentPayable);
                $('#bbf_claims_amount').val(newClaims);
            }
        } else if (source === 'payable') {
            const payable = parseFloat($('#bbf_payable_amount').val()) || 0;
            const newClaims = Math.max(0, total - payable);
            $('#bbf_claims_amount').val(newClaims);
        } else if (source === 'claims') {
            const claims = parseFloat($('#bbf_claims_amount').val()) || 0;
            const newPayable = Math.max(0, total - claims);
            $('#bbf_payable_amount').val(newPayable);
        }
    }

    function setBbfPreset(preset) {
        const total = parseFloat($('#bbf_total_price').val()) || 0;

        if (preset === '100_cash') {
            $('#bbf_coverage_mode').val('cash');
            $('#bbf_payable_amount').val(total);
            $('#bbf_claims_amount').val(0);
        } else if (preset === '100_hmo') {
            if ($('#bbf_coverage_mode').val() === 'cash') {
                $('#bbf_coverage_mode').val('primary');
            }
            $('#bbf_payable_amount').val(0);
            $('#bbf_claims_amount').val(total);
        } else if (preset === '50_50') {
            if ($('#bbf_coverage_mode').val() === 'cash') {
                $('#bbf_coverage_mode').val('primary');
            }
            const half = (total / 2).toFixed(2);
            $('#bbf_payable_amount').val(half);
            $('#bbf_claims_amount').val(half);
        }

        onBbfCoverageModeChange();
    }

    function submitBillBaseFee(e) {
        e.preventDefault();

        const form = $('#billBaseFeeForm');
        const alertArea = $('#bbf_alert_area');
        alertArea.empty();

        const procId = window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.procedureId : '';
        const billRoute = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.billBaseFeeRoute)
            ? window.WORKBENCH_CONFIG.billBaseFeeRoute
            : wbUrl(`/patient-procedures/${procId}/bill-base-fee`);

        const total = parseFloat($('#bbf_total_price').val()) || 0;
        const payable = parseFloat($('#bbf_payable_amount').val()) || 0;
        const claims = parseFloat($('#bbf_claims_amount').val()) || 0;
        const mode = $('#bbf_coverage_mode').val();
        const authCode = $('#bbf_auth_code').val();

        if (total < 0 || payable < 0 || claims < 0) {
            alertArea.html('<div class="alert alert-danger py-2 mb-3">Prices cannot be negative.</div>');
            return;
        }

        const submitBtn = $('#bbf_submit_btn');
        submitBtn.prop('disabled', true);
        $('#bbf_submit_text').text('Processing...');

        $.ajax({
            url: billRoute,
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content') || (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.csrf : ''),
                coverage_mode: mode,
                payable_amount: payable,
                claims_amount: claims,
                auth_code: authCode
            },
            success: function(resp) {
                submitBtn.prop('disabled', false);
                $('#bbf_submit_text').text('Confirm & Bill Base Fee');

                if (resp && resp.success) {
                    $('#billBaseFeeModal').modal('hide');

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Billed Successfully',
                            text: resp.message || 'Procedure base fee has been billed.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        window.location.reload();
                    }
                } else {
                    alertArea.html(`<div class="alert alert-danger py-2 mb-3">${resp.message || 'An error occurred.'}</div>`);
                }
            },
            error: function(xhr) {
                submitBtn.prop('disabled', false);
                $('#bbf_submit_text').text('Confirm & Bill Base Fee');
                const err = xhr.responseJSON?.message || 'Error communicating with server.';
                alertArea.html(`<div class="alert alert-danger py-2 mb-3"><i class="fa fa-exclamation-circle mr-1"></i>${err}</div>`);
            }
        });
    }

    // ── Procedure Safety Checklist Verification ──
    function toggleChecklistItem(itemId, isChecked, notes) {
        const toggleRoute = window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.checklistToggleRoute : null;
        if (!toggleRoute) {
            console.error('Checklist toggle route is not configured');
            return;
        }

        const checkbox = $(`#chk-item-${itemId}`);
        const row = $(`#chk-row-${itemId}`);
        const auditSpan = $(`#chk-audit-${itemId}`);

        // Visual feedback
        row.css('opacity', '0.6');

        $.ajax({
            url: toggleRoute,
            type: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content') || (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.csrf : ''),
                item_id: itemId,
                is_completed: isChecked ? 1 : 0,
                notes: notes || ''
            },
            success: function(resp) {
                row.css('opacity', '1');
                if (resp && resp.success) {
                    if (isChecked) {
                        row.addClass('item-checked');
                        auditSpan.html(`<i class="fa fa-check-circle mr-1"></i>Verified by <strong>${resp.completed_by_name || 'Staff'}</strong> <span class="text-muted ml-1">${resp.completed_at || 'Just now'}</span>`).show();
                    } else {
                        row.removeClass('item-checked');
                        auditSpan.html('').hide();
                    }

                    // Update summary progress
                    if (resp.progress) {
                        $('#chk-progress-count').text(`${resp.progress.completed}/${resp.progress.total}`);
                        $('#chk-progress-bar').css('width', `${resp.progress.percent}%`).attr('aria-valuenow', resp.progress.percent);
                        if (resp.progress.percent === 100) {
                            $('#chk-badge-status').html('<span class="badge badge-success"><i class="fa fa-check-circle mr-1"></i>Checklist Complete</span>');
                        } else if (resp.progress.completed > 0) {
                            $('#chk-badge-status').html(`<span class="badge badge-warning">${resp.progress.completed}/${resp.progress.total} Verified</span>`);
                        } else {
                            $('#chk-badge-status').html('');
                        }
                    }

                    if (typeof toastr !== 'undefined') {
                        toastr.success(resp.message || 'Checklist updated');
                    }
                } else {
                    checkbox.prop('checked', !isChecked);
                    if (typeof toastr !== 'undefined') {
                        toastr.error(resp.message || 'Failed to update checklist item');
                    }
                }
            },
            error: function(xhr) {
                row.css('opacity', '1');
                checkbox.prop('checked', !isChecked);
                const msg = xhr.responseJSON?.message || 'Error updating checklist item.';
                if (typeof toastr !== 'undefined') {
                    toastr.error(msg);
                } else {
                    alert(msg);
                }
            }
        });
    }

    // Expose functions to window
    window.openBillBaseFeeModal = openBillBaseFeeModal;
    window.applyTariffBenchmark = applyTariffBenchmark;
    window.onBbfCoverageModeChange = onBbfCoverageModeChange;
    window.onBbfTotalOrSplitChange = onBbfTotalOrSplitChange;
    window.setBbfPreset = setBbfPreset;
    window.submitBillBaseFee = submitBillBaseFee;
    window.toggleChecklistItem = toggleChecklistItem;
