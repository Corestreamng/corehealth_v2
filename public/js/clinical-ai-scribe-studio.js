/**
 * Clinical AI Scribe Studio ("Full AI Mode") Controller
 * Unified ambient clinical dictation, taxonomy structuring, lossless conversion,
 * draft persistence, and grounded hospital order routing.
 */

class ClinicalAiScribeStudio {
    constructor(config = {}) {
        if (ClinicalAiScribeStudio.instance) {
            ClinicalAiScribeStudio.instance.updateConfig(config);
            return ClinicalAiScribeStudio.instance;
        }
        ClinicalAiScribeStudio.instance = this;

        this.role = config.role || 'doctor'; // 'doctor', 'nurse', 'maternity'
        this.targetEditorSelector = config.targetEditorSelector || '#doctor_diagnosis_text';
        this.editorType = config.editorType || 'ckeditor'; // 'ckeditor' or 'textarea'
        this.patientId = config.patientId || null;
        this.encounterId = config.encounterId || null;
        this.defaultLanguage = config.defaultLanguage || 'en-NG';
        this.taxonomy = config.taxonomy || (this.role === 'nurse' ? 'dar' : (this.role === 'maternity' ? 'anc' : 'clerking'));

        // DOM elements
        this.overlay = document.getElementById('clinical-ai-scribe-overlay');
        this.transcriptContainer = document.getElementById('scribe-live-transcript');
        this.waveformContainer = document.getElementById('scribe-waveform-container');
        this.recordingBadge = document.getElementById('scribe-recording-badge');
        this.recordingTimerEl = document.getElementById('scribe-recording-timer');
        this.sectionsContainer = document.getElementById('scribe-sections-container');
        this.ordersTableBody = document.getElementById('scribe-orders-tbody');
        this.ordersDeck = document.getElementById('scribe-orders-deck');
        this.ordersCountBadge = document.getElementById('scribe-orders-count-badge');
        this.safetyWarningsContainer = document.getElementById('scribe-safety-warnings');
        this.draftNotice = document.getElementById('scribe-draft-notice');
        this.langSelect = document.getElementById('scribe-lang-select');
        this.headlineEl = document.getElementById('scribe-headline-text');

        // State
        this.isListening = false;
        this.isPaused = false;
        this.recognition = null;
        this.audioContext = null;
        this.audioAnalyser = null;
        this.audioStream = null;
        this.animFrameId = null;
        this.timerInterval = null;
        this.secondsRecorded = 0;
        this.finalTranscript = '';
        this.baselineEditorContent = '';
        this.structuredSections = [];
        this.groundedOrders = [];
        this.isEditing = false;
        this.debounceSaveTimer = null;

        this.init();
    }

    updateConfig(config = {}) {
        if (config.role) this.role = config.role;
        if (config.targetEditorSelector) this.targetEditorSelector = config.targetEditorSelector;
        if (config.editorType) this.editorType = config.editorType;
        if (config.patientId !== undefined) this.patientId = config.patientId;
        if (config.encounterId !== undefined) this.encounterId = config.encounterId;
        if (config.taxonomy) this.taxonomy = config.taxonomy;
        this.updateTaxonomyPills();
    }

    init() {
        if (!this.overlay) return;

        this.initSpeechRecognition();
        this.bindEvents();
        this.updateTaxonomyPills();
        this.restoreDraftIfAvailable();
    }

    initSpeechRecognition() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            console.warn('Speech Recognition not supported in this browser.');
            return;
        }

        this.recognition = new SpeechRecognition();
        this.recognition.continuous = true;
        this.recognition.interimResults = true;
        this.recognition.lang = this.langSelect ? this.langSelect.value : this.defaultLanguage;

        this.recognition.onstart = () => {
            this.isListening = true;
            this.updateRecordingUI(true);
            this.startTimer();
            this.startWaveformVisualizer();
        };

        this.recognition.onend = () => {
            if (this.isListening && !this.isPaused) {
                try { this.recognition.start(); } catch (e) {}
            } else {
                this.updateRecordingUI(false);
                this.stopTimer();
                this.stopWaveformVisualizer();
            }
        };

        this.recognition.onerror = (e) => {
            console.warn('Speech recognition error:', e.error);
        };

        this.recognition.onresult = (event) => {
            let interimTranscript = '';
            for (let i = event.resultIndex; i < event.results.length; ++i) {
                if (event.results[i].isFinal) {
                    this.finalTranscript += event.results[i][0].transcript + ' ';
                } else {
                    interimTranscript += event.results[i][0].transcript;
                }
            }
            this.renderLiveTranscript(interimTranscript);
            this.scheduleDebouncedDraftSave();
        };
    }

    bindEvents() {
        // Close modal
        document.querySelectorAll('.btn-close-scribe').forEach(btn => {
            btn.addEventListener('click', () => this.close());
        });

        // Toggle Recording
        const btnToggleRec = document.getElementById('btn-scribe-toggle-rec');
        if (btnToggleRec) {
            btnToggleRec.addEventListener('click', () => this.toggleRecording());
        }

        // Restart Dictation
        const btnResetRec = document.getElementById('btn-scribe-restart-rec');
        if (btnResetRec) {
            btnResetRec.addEventListener('click', () => this.restartDictation());
        }

        // Process Note Button
        const btnProcess = document.getElementById('btn-scribe-process');
        if (btnProcess) {
            btnProcess.addEventListener('click', () => this.processSession());
        }

        // Accept & Sign Button
        const btnSign = document.getElementById('btn-scribe-sign');
        if (btnSign) {
            btnSign.addEventListener('click', () => this.acceptAndSign());
        }

        // Edit Toggle Button
        const btnEdit = document.getElementById('btn-scribe-edit');
        if (btnEdit) {
            btnEdit.addEventListener('click', () => this.toggleInlineEdit());
        }

        // Reject / Discard Button
        const btnReject = document.getElementById('btn-scribe-reject');
        if (btnReject) {
            btnReject.addEventListener('click', () => this.discardDraft());
        }

        // Language Select
        if (this.langSelect) {
            this.langSelect.addEventListener('change', () => {
                if (this.recognition) {
                    this.recognition.lang = this.langSelect.value;
                    if (this.isListening) {
                        this.recognition.stop();
                        setTimeout(() => this.recognition.start(), 200);
                    }
                }
            });
        }

        // Taxonomy Switcher Pills
        document.querySelectorAll('.taxonomy-pill-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const targetTax = e.target.getAttribute('data-taxonomy');
                if (targetTax && targetTax !== this.taxonomy) {
                    this.switchTaxonomy(targetTax);
                }
            });
        });
    }

    open() {
        if (!this.overlay) return;

        // Sync IDs from globals if available
        this.patientId = this.patientId || window.currentPatientId || window.patientId || (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : null);
        this.encounterId = this.encounterId || window.currentEncounterId || window.encounterId || (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.encounterId : null);

        // Fetch text currently in the active editor
        this.baselineEditorContent = this.getEditorContent();
        this.renderBaselineNoteContext();

        this.overlay.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Auto-start dictation if supported
        this.startRecording();
    }

    close() {
        if (!this.overlay) return;
        this.stopRecording();
        this.overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    startRecording() {
        if (this.recognition && !this.isListening) {
            try {
                this.isPaused = false;
                this.recognition.start();
            } catch (e) {
                console.warn('Recognition start exception:', e);
            }
        }
    }

    stopRecording() {
        if (this.recognition && this.isListening) {
            this.isListening = false;
            this.isPaused = false;
            try { this.recognition.stop(); } catch (e) {}
        }
        this.stopWaveformVisualizer();
        this.stopTimer();
    }

    toggleRecording() {
        if (this.isListening) {
            this.stopRecording();
        } else {
            this.startRecording();
        }
    }

    restartDictation() {
        this.finalTranscript = '';
        this.renderLiveTranscript('');
        this.secondsRecorded = 0;
        this.updateTimerText();
        this.scheduleDebouncedDraftSave();
    }

    updateRecordingUI(active) {
        if (this.recordingBadge) {
            this.recordingBadge.style.display = active ? 'inline-flex' : 'none';
        }
        const btnToggle = document.getElementById('btn-scribe-toggle-rec');
        if (btnToggle) {
            btnToggle.innerHTML = active
                ? '<i class="mdi mdi-pause"></i> Pause'
                : '<i class="mdi mdi-microphone"></i> Resume';
        }
    }

    startTimer() {
        this.stopTimer();
        this.timerInterval = setInterval(() => {
            this.secondsRecorded++;
            this.updateTimerText();
        }, 1000);
    }

    stopTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
    }

    updateTimerText() {
        if (!this.recordingTimerEl) return;
        const mins = Math.floor(this.secondsRecorded / 60).toString().padStart(2, '0');
        const secs = (this.secondsRecorded % 60).toString().padStart(2, '0');
        this.recordingTimerEl.textContent = `${mins}:${secs}`;
    }

    renderLiveTranscript(interim = '') {
        if (!this.transcriptContainer) return;
        const combined = this.finalTranscript + (interim ? `<span class="transcript-interim">${interim}</span>` : '');
        this.transcriptContainer.innerHTML = combined
            ? `${combined} <span class="transcript-caret"></span>`
            : '<span class="text-muted">Listening for consultation dialogue...</span> <span class="transcript-caret"></span>';
        this.transcriptContainer.scrollTop = this.transcriptContainer.scrollHeight;
    }

    async startWaveformVisualizer() {
        if (this.audioContext) return;
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.audioStream = stream;
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            this.audioContext = new AudioContextClass();
            const source = this.audioContext.createMediaStreamSource(stream);
            this.audioAnalyser = this.audioContext.createAnalyser();
            this.audioAnalyser.fftSize = 64;
            source.connect(this.audioAnalyser);

            const bufferLength = this.audioAnalyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);
            const bars = document.querySelectorAll('.scribe-waveform-bar');

            const draw = () => {
                if (!this.audioAnalyser) return;
                this.audioAnalyser.getByteFrequencyData(dataArray);

                bars.forEach((bar, index) => {
                    const val = dataArray[index % bufferLength] || 0;
                    const height = Math.max(6, Math.min(50, (val / 255) * 55));
                    bar.style.height = `${height}px`;
                });

                this.animFrameId = requestAnimationFrame(draw);
            };

            draw();
        } catch (e) {
            console.warn('Web Audio visualizer could not start:', e);
        }
    }

    stopWaveformVisualizer() {
        if (this.animFrameId) {
            cancelAnimationFrame(this.animFrameId);
            this.animFrameId = null;
        }
        if (this.audioStream) {
            this.audioStream.getTracks().forEach(track => track.stop());
            this.audioStream = null;
        }
        if (this.audioContext) {
            this.audioContext.close().catch(() => {});
            this.audioContext = null;
            this.audioAnalyser = null;
        }
        document.querySelectorAll('.scribe-waveform-bar').forEach(bar => {
            bar.style.height = '6px';
        });
    }

    renderBaselineNoteContext() {
        const baselineBox = document.getElementById('scribe-baseline-box');
        const baselineBody = document.getElementById('scribe-baseline-body');
        const contextDivider = document.getElementById('scribe-context-divider');

        if (!baselineBox || !baselineBody) return;

        const raw = (this.baselineEditorContent || '').trim();
        const textOnly = raw.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();

        if (textOnly.length > 0) {
            baselineBody.innerHTML = raw;
            baselineBox.style.display = 'block';
            if (contextDivider) contextDivider.style.display = 'flex';
        } else {
            baselineBox.style.display = 'none';
            if (contextDivider) contextDivider.style.display = 'none';
        }
    }

    getEditorContent() {
        try {
            // 1. Direct window.editor instance (standard for Doctor Encounter - see encounter-page.js line 192)
            if (window.editor && typeof window.editor.getData === 'function') {
                const data = window.editor.getData();
                if (data && data.trim() && data !== '<p>&nbsp;</p>') return data;
            }

            // 2. Element-bound ckeditorInstance
            const el = document.querySelector(this.targetEditorSelector);
            if (el && el.ckeditorInstance && typeof el.ckeditorInstance.getData === 'function') {
                const data = el.ckeditorInstance.getData();
                if (data && data.trim() && data !== '<p>&nbsp;</p>') return data;
            }

            // 3. window.classicEditors array
            if (Array.isArray(window.classicEditors)) {
                for (const ed of window.classicEditors) {
                    if (ed && typeof ed.getData === 'function') {
                        if (!this.targetEditorSelector || (ed.sourceElement && (
                            '#' + ed.sourceElement.id === this.targetEditorSelector ||
                            ed.sourceElement.matches(this.targetEditorSelector)
                        ))) {
                            const data = ed.getData();
                            if (data && data.trim() && data !== '<p>&nbsp;</p>') return data;
                        }
                    }
                }
            }

            // 4. WorkbenchNotesKit editors (Nursing & Maternity)
            if (window.WorkbenchNotesKit && window.WorkbenchNotesKit.editors) {
                for (const key in window.WorkbenchNotesKit.editors) {
                    const ed = window.WorkbenchNotesKit.editors[key];
                    if (ed && typeof ed.getData === 'function') {
                        const val = ed.getData();
                        if (val && val.trim() && val !== '<p>&nbsp;</p>') return val;
                    }
                }
            }

            // 5. Fallback textarea or HTML container
            if (el) {
                if (typeof el.value === 'string' && el.value.trim()) return el.value;
                if (el.innerHTML && el.innerHTML.trim() && el.innerHTML !== '<p>&nbsp;</p>') return el.innerHTML;
            }

            // 6. jQuery fallback
            if (window.$ && this.targetEditorSelector) {
                const jqVal = $(this.targetEditorSelector).val();
                if (jqVal && jqVal.trim()) return jqVal;
            }

            return '';
        } catch (e) {
            console.warn('getEditorContent error:', e);
            return '';
        }
    }

    setEditorContent(content) {
        try {
            let handled = false;

            // 1. Direct window.editor (Doctor encounter-page.js line 192)
            if (window.editor && typeof window.editor.setData === 'function') {
                window.editor.setData(content);
                handled = true;
            }

            // 2. Element-bound ckeditorInstance
            const el = document.querySelector(this.targetEditorSelector);
            if (el && el.ckeditorInstance && typeof el.ckeditorInstance.setData === 'function') {
                el.ckeditorInstance.setData(content);
                handled = true;
            }

            // 3. window.classicEditors array
            if (Array.isArray(window.classicEditors)) {
                window.classicEditors.forEach(ed => {
                    if (ed && typeof ed.setData === 'function') {
                        if (!this.targetEditorSelector || (ed.sourceElement && (
                            '#' + ed.sourceElement.id === this.targetEditorSelector ||
                            ed.sourceElement.matches(this.targetEditorSelector)
                        ))) {
                            ed.setData(content);
                            handled = true;
                        }
                    }
                });
            }

            // 4. WorkbenchNotesKit (Nursing & Maternity)
            if (window.WorkbenchNotesKit && window.WorkbenchNotesKit.editors) {
                for (const key in window.WorkbenchNotesKit.editors) {
                    const ed = window.WorkbenchNotesKit.editors[key];
                    if (ed && typeof ed.setData === 'function') {
                        ed.setData(content);
                        handled = true;
                    }
                }
            }

            // 5. Sync underlying textarea / input
            if (el && 'value' in el) {
                el.value = content;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (window.$ && this.targetEditorSelector) {
                $(this.targetEditorSelector).val(content).trigger('change').trigger('input');
            }

            // 6. Trigger active workbench autosave if available
            if (typeof window.autosavenotes === 'function') {
                setTimeout(() => window.autosavenotes(), 250);
            }
        } catch (e) {
            console.error('Failed to set editor content:', e);
        }
    }

    escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    updateTaxonomyPills() {
        document.querySelectorAll('.taxonomy-pill-btn').forEach(btn => {
            const tax = btn.getAttribute('data-taxonomy');
            if (tax === this.taxonomy) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    async switchTaxonomy(newTaxonomy) {
        const oldTaxonomy = this.taxonomy;
        this.taxonomy = newTaxonomy;
        this.updateTaxonomyPills();

        if (this.structuredSections && this.structuredSections.length > 0) {
            // Lossless translation via API
            try {
                const btnProcess = document.getElementById('btn-scribe-process');
                if (btnProcess) btnProcess.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Converting...';

                const response = await fetch('/llm/clinical-scribe/convert-format', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        current_sections: this.structuredSections,
                        from_taxonomy: oldTaxonomy,
                        to_taxonomy: newTaxonomy,
                        role: this.role
                    })
                });

                const data = await response.json();
                if (data.success && data.structured_sections) {
                    this.structuredSections = data.structured_sections;
                    this.renderStructuredSections();
                    this.scheduleDebouncedDraftSave();
                }
            } catch (e) {
                console.error('Conversion failed:', e);
            } finally {
                const btnProcess = document.getElementById('btn-scribe-process');
                if (btnProcess) btnProcess.innerHTML = '<i class="mdi mdi-auto-fix"></i> Structure & Polish';
            }
        }
    }

    async processSession() {
        const btnProcess = document.getElementById('btn-scribe-process');
        if (btnProcess) btnProcess.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Structuring Note...';

        try {
            const response = await fetch('/llm/clinical-scribe/process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    patient_id: this.patientId,
                    encounter_id: this.encounterId,
                    role: this.role,
                    taxonomy: this.taxonomy,
                    transcript: this.finalTranscript,
                    editor_content: this.baselineEditorContent,
                    language: this.langSelect ? this.langSelect.value : this.defaultLanguage
                })
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to process scribe note.');
            }

            this.structuredSections = data.structured_sections || [];
            this.groundedOrders = data.grounded_orders || [];
            if (this.headlineEl && data.summary_headline) {
                this.headlineEl.textContent = data.summary_headline;
            }

            this.renderStructuredSections();
            this.renderOrdersDeck();
            this.renderSafetyWarnings(data.safety_warnings || []);
            this.scheduleDebouncedDraftSave();

            if (typeof toastr !== 'undefined') {
                toastr.success('Clinical note polished and structured.');
            }
        } catch (error) {
            console.error('Process session error:', error);
            if (typeof toastr !== 'undefined') {
                toastr.error(error.message, 'Structuring Error');
            } else {
                alert('Error: ' + error.message);
            }
        } finally {
            if (btnProcess) btnProcess.innerHTML = '<i class="mdi mdi-auto-fix"></i> Structure & Polish';
        }
    }

    renderStructuredSections() {
        if (!this.sectionsContainer) return;
        this.sectionsContainer.innerHTML = '';

        if (!this.structuredSections || this.structuredSections.length === 0) {
            this.sectionsContainer.innerHTML = '<div class="text-muted p-4 text-center">No structured note generated yet. Dictate speech or click "Structure & Polish".</div>';
            return;
        }

        this.structuredSections.forEach((sec, idx) => {
            const block = document.createElement('div');
            block.className = 'scribe-section-block';

            const tag = document.createElement('div');
            tag.className = 'scribe-section-tag';
            tag.textContent = sec.key || `${idx + 1}`;

            const body = document.createElement('div');
            body.className = 'scribe-section-body';

            const title = document.createElement('div');
            title.className = 'scribe-section-title';
            title.textContent = sec.title || sec.key;

            const text = document.createElement('p');
            text.className = 'scribe-section-text';
            text.textContent = sec.content || '';
            text.setAttribute('contenteditable', this.isEditing ? 'true' : 'false');
            text.addEventListener('blur', () => {
                sec.content = text.textContent;
                this.scheduleDebouncedDraftSave();
            });

            body.appendChild(title);
            body.appendChild(text);
            block.appendChild(tag);
            block.appendChild(body);
            this.sectionsContainer.appendChild(block);
        });
    }

    renderOrdersDeck() {
        if (!this.ordersTableBody || !this.ordersDeck) return;
        this.ordersTableBody.innerHTML = '';

        const count = this.groundedOrders.length;
        if (this.ordersCountBadge) {
            this.ordersCountBadge.textContent = `${count} RECOMMENDED`;
        }

        if (count === 0) {
            this.ordersDeck.style.display = 'none';
            return;
        }

        this.ordersDeck.style.display = 'block';

        this.groundedOrders.forEach((ord, idx) => {
            const tr = document.createElement('tr');
            tr.className = 'scribe-order-row';

            const catClass = ord.order_type === 'PRODUCT' ? 'cat-product' : (ord.order_type === 'REFERRAL' ? 'cat-referral' : (ord.order_type === 'FOLLOW_UP' ? 'cat-followup' : 'cat-service'));
            const isProduct = (ord.order_type === 'PRODUCT');
            const isReferral = (ord.order_type === 'REFERRAL');
            const isFollowUp = (ord.order_type === 'FOLLOW_UP');

            let col4Html = '';
            if (isProduct) {
                const val = this.escapeHtml(ord.dosage || '');
                col4Html = `<input type="text" class="order-inline-input order-spec-input" data-idx="${idx}" data-field="dosage" value="${val}" placeholder="500mg | TID | 3 days | Oral">`;
            } else if (isReferral) {
                const val = this.escapeHtml(ord.specialization || '');
                col4Html = `<input type="text" class="order-inline-input order-spec-input" data-idx="${idx}" data-field="specialization" value="${val}" placeholder="e.g. General Surgery / Cardiology">`;
            } else if (isFollowUp) {
                const val = this.escapeHtml(ord.duration_days ? `${ord.duration_days} days` : '7 days');
                col4Html = `<input type="text" class="order-inline-input order-spec-input" data-idx="${idx}" data-field="duration_days" value="${val}" placeholder="e.g. 7 days / 2 weeks">`;
            } else {
                const val = this.escapeHtml(ord.indication || ord.reason || '');
                col4Html = `<input type="text" class="order-inline-input order-spec-input" data-idx="${idx}" data-field="indication" value="${val}" placeholder="e.g. Confirm appendicitis / Rule out anemia">`;
            }

            const cleanInst = (ord.instructions && !['instructions...', 'routine', 'none', 'n/a'].includes(ord.instructions.toLowerCase()))
                ? this.escapeHtml(ord.instructions)
                : '';
            const instPlaceholder = isProduct
                ? 'e.g. Take after meals / Stat / Before bed'
                : 'e.g. Fasting sample / Stat / Pelvic prep';
            const col5Html = `<input type="text" class="order-inline-input order-instruction-input" data-idx="${idx}" data-field="instructions" value="${cleanInst}" placeholder="${instPlaceholder}">`;

            tr.innerHTML = `
                <td style="width: 35px; text-align: center;">
                    <input type="checkbox" class="order-chk" data-idx="${idx}" ${ord.is_selected ? 'checked' : ''}>
                </td>
                <td style="width: 120px;">
                    <span class="scribe-order-cat-badge ${catClass}">${ord.category_name || ord.order_type}</span>
                </td>
                <td style="min-width: 180px;">
                    <strong class="d-block text-dark">${this.escapeHtml(ord.item_name)}</strong>
                    ${ord.catalog_id ? `<small class="text-muted d-block">ID: #${ord.catalog_id} · ${ord.target_queue}</small>` : ''}
                </td>
                <td style="min-width: 240px; width: 280px;">
                    ${col4Html}
                </td>
                <td style="min-width: 260px; width: 320px;">
                    ${col5Html}
                </td>
                <td style="width: 45px; text-align: center;">
                    <button type="button" class="btn-order-action btn-del-order" data-idx="${idx}" title="Remove Order"><i class="mdi mdi-close"></i></button>
                </td>
            `;

            this.ordersTableBody.appendChild(tr);
        });

        // Select All checkbox
        const chkAll = document.getElementById('chk-all-orders');
        if (chkAll) {
            chkAll.checked = this.groundedOrders.every(o => o.is_selected);
            chkAll.onchange = (e) => {
                const checked = e.target.checked;
                this.groundedOrders.forEach(o => o.is_selected = checked);
                this.ordersTableBody.querySelectorAll('.order-chk').forEach(c => c.checked = checked);
                this.scheduleDebouncedDraftSave();
            };
        }

        // Bind interactive events
        this.ordersTableBody.querySelectorAll('.order-chk').forEach(chk => {
            chk.addEventListener('change', (e) => {
                const i = parseInt(e.target.getAttribute('data-idx'));
                if (this.groundedOrders[i]) {
                    this.groundedOrders[i].is_selected = e.target.checked;
                    if (chkAll) chkAll.checked = this.groundedOrders.every(o => o.is_selected);
                    this.scheduleDebouncedDraftSave();
                }
            });
        });

        this.ordersTableBody.querySelectorAll('.order-inline-input').forEach(inp => {
            inp.addEventListener('input', (e) => {
                const i = parseInt(e.target.getAttribute('data-idx'));
                const f = e.target.getAttribute('data-field');
                if (this.groundedOrders[i]) {
                    this.groundedOrders[i][f] = e.target.value;
                    this.scheduleDebouncedDraftSave();
                }
            });
        });

        this.ordersTableBody.querySelectorAll('.btn-del-order').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const i = parseInt(btn.getAttribute('data-idx'));
                this.groundedOrders.splice(i, 1);
                this.renderOrdersDeck();
                this.scheduleDebouncedDraftSave();
            });
        });
    }

    renderSafetyWarnings(warnings = []) {
        if (!this.safetyWarningsContainer) return;
        this.safetyWarningsContainer.innerHTML = '';
        if (warnings.length === 0) return;

        warnings.forEach(w => {
            const div = document.createElement('div');
            div.className = 'scribe-alert-conflict';
            div.innerHTML = `<i class="mdi mdi-alert-circle"></i> <strong>Safety Warning:</strong> ${w}`;
            this.safetyWarningsContainer.appendChild(div);
        });
    }

    toggleInlineEdit() {
        this.isEditing = !this.isEditing;
        const btnEdit = document.getElementById('btn-scribe-edit');
        if (btnEdit) {
            btnEdit.textContent = this.isEditing ? 'Done Editing' : 'Edit';
            btnEdit.classList.toggle('btn-primary', this.isEditing);
        }
        document.querySelectorAll('.scribe-section-text').forEach(t => {
            t.setAttribute('contenteditable', this.isEditing ? 'true' : 'false');
        });
    }

    async acceptAndSign() {
        if (!this.structuredSections || this.structuredSections.length === 0) {
            if (typeof toastr !== 'undefined') {
                toastr.warning('Please structure or type a note before signing.');
            }
            return;
        }

        const btnSign = document.getElementById('btn-scribe-sign');
        if (btnSign) btnSign.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Committing...';

        try {
            // Build semantic HTML string for target CKEditor
            let finalHtml = '';
            this.structuredSections.forEach(sec => {
                finalHtml += `<h3>${this.escapeHtml(sec.title || sec.key)}</h3><p>${sec.content}</p>`;
            });

            // Dispatched Orders & Requests manifest appended below a dividing rule
            const selectedOrders = this.groundedOrders.filter(o => o.is_selected);
            if (selectedOrders.length > 0) {
                finalHtml += '<hr><h4 style="margin-top: 1.25rem; color: #1e293b;">Dispatched Clinical Orders & Requests:</h4><ul>';
                selectedOrders.forEach(ord => {
                    if (ord.order_type === 'PRODUCT') {
                        const spec = ord.dosage ? ` &mdash; <strong>${this.escapeHtml(ord.dosage)}</strong>` : '';
                        const inst = ord.instructions ? ` (${this.escapeHtml(ord.instructions)})` : '';
                        finalHtml += `<li><strong>[PRESCRIPTION]</strong> ${this.escapeHtml(ord.item_name)}${spec}${inst}</li>`;
                    } else if (ord.order_type === 'SERVICE') {
                        const isLab = (ord.target_queue === 'lab_queue');
                        const isImaging = (ord.target_queue === 'imaging_queue');
                        const tag = isLab ? 'LABORATORY' : (isImaging ? 'IMAGING' : 'PROCEDURE');
                        const ind = ord.instructions || ord.indication || ord.reason ? ` &mdash; ${this.escapeHtml(ord.instructions || ord.indication || ord.reason)}` : '';
                        finalHtml += `<li><strong>[${tag}]</strong> ${this.escapeHtml(ord.item_name)}${ind}</li>`;
                    } else if (ord.order_type === 'REFERRAL') {
                        const spec = ord.specialization ? ` (${this.escapeHtml(ord.specialization)})` : '';
                        const reason = ord.reason ? ` &mdash; ${this.escapeHtml(ord.reason)}` : '';
                        finalHtml += `<li><strong>[SPECIALIST REFERRAL]</strong> ${this.escapeHtml(ord.item_name)}${spec}${reason}</li>`;
                    } else if (ord.order_type === 'FOLLOW_UP') {
                        const days = ord.duration_days ? ` in ${ord.duration_days} days` : '';
                        const reason = ord.reason ? ` &mdash; ${this.escapeHtml(ord.reason)}` : '';
                        finalHtml += `<li><strong>[FOLLOW-UP REVIEW]</strong> ${this.escapeHtml(ord.item_name)}${days}${reason}</li>`;
                    } else {
                        finalHtml += `<li><strong>[ORDER]</strong> ${this.escapeHtml(ord.item_name)}</li>`;
                    }
                });
                finalHtml += '</ul>';
            }

            // Completely replace active Editor content with the unified polished note + manifest
            this.setEditorContent(finalHtml);

            // Dispatch selected orders through CoreHealth standard workbench APIs
            await this.dispatchOrdersThroughStandardApis(selectedOrders);

            // Record signed audit trail
            await fetch('/llm/clinical-scribe/sign', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    patient_id: this.patientId,
                    encounter_id: this.encounterId,
                    role: this.role,
                    taxonomy_type: this.taxonomy,
                    final_signed_note: finalHtml,
                    original_transcript: this.finalTranscript,
                    baseline_editor_content: this.baselineEditorContent,
                    structured_note_json: this.structuredSections,
                    orders_dispatched_json: selectedOrders
                })
            });

            this.clearPersistedDraft();
            this.close();

            if (typeof toastr !== 'undefined') {
                toastr.success('Consultation note signed and orders dispatched to respective hospital queues.');
            }
        } catch (e) {
            console.error('Accept & sign failure:', e);
            if (typeof toastr !== 'undefined') {
                toastr.error(e.message, 'Sign-off Error');
            }
        } finally {
            if (btnSign) btnSign.innerHTML = '<i class="mdi mdi-check-circle"></i> Accept & sign';
        }
    }

    async dispatchOrdersThroughStandardApis(orders) {
        if (!orders || orders.length === 0) return;

        for (const ord of orders) {
            try {
                if (this.role === 'doctor' && this.encounterId) {
                    // Match standard Doctor Encounter manual APIs
                    if (ord.order_type === 'SERVICE' && ord.catalog_id) {
                        const isLab = (ord.target_queue === 'lab_queue');
                        const isImaging = (ord.target_queue === 'imaging_queue');
                        const url = isLab
                            ? `/encounters/${this.encounterId}/add-lab`
                            : (isImaging ? `/encounters/${this.encounterId}/add-imaging` : `/encounters/${this.encounterId}/add-procedure`);

                        await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify({ service_id: ord.catalog_id, note: ord.instructions || ord.indication || '' })
                        });
                    } else if (ord.order_type === 'PRODUCT' && ord.catalog_id) {
                        await fetch(`/encounters/${this.encounterId}/add-prescription`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify({
                                product_id: ord.catalog_id,
                                dose: ord.dosage || '1 unit',
                                frequency: ord.frequency || 'PRN',
                                duration: ord.duration || '3 days',
                                quantity: ord.quantity || 1,
                                note: ord.instructions || ''
                            })
                        });
                    } else if (ord.order_type === 'REFERRAL') {
                        await fetch(`/encounters/${this.encounterId}/referrals`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify({
                                referral_type: 'internal',
                                reason: ord.reason || ord.item_name,
                                urgency: ord.urgency || 'routine',
                                clinical_summary: ord.clinical_summary || ''
                            })
                        });
                    } else if (ord.order_type === 'FOLLOW_UP') {
                        const targetDate = new Date();
                        targetDate.setDate(targetDate.getDate() + (ord.duration_days || 7));
                        const dateStr = targetDate.toISOString().split('T')[0];

                        await fetch(`/encounters/${this.encounterId}/schedule-followup`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify({
                                appointment_date: dateStr,
                                reason: ord.reason || 'Routine follow-up'
                            })
                        });
                    }
                } else if (this.role === 'nurse') {
                    // Match Nursing Workbench billing & task APIs
                    if (ord.order_type === 'SERVICE' && ord.catalog_id) {
                        await fetch('/nursing-workbench/billing/add-service', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify({ patient_id: this.patientId, service_id: ord.catalog_id, quantity: 1 })
                        });
                    } else if (ord.order_type === 'PRODUCT' && ord.catalog_id) {
                        await fetch('/nursing-workbench/billing/add-consumable', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify({ patient_id: this.patientId, product_id: ord.catalog_id, quantity: ord.quantity || 1 })
                        });
                    }
                }
            } catch (err) {
                console.warn('Dispatch order error for item:', ord.item_name, err);
            }
        }
    }

    getDraftStorageKey() {
        return `corehealth_scribe_draft_${this.role}_${this.patientId || 'global'}_${this.encounterId || 'new'}`;
    }

    scheduleDebouncedDraftSave() {
        clearTimeout(this.debounceSaveTimer);
        this.debounceSaveTimer = setTimeout(() => {
            this.saveDraft();
        }, 500);
    }

    saveDraft() {
        const key = this.getDraftStorageKey();
        const state = {
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            finalTranscript: this.finalTranscript,
            secondsRecorded: this.secondsRecorded,
            taxonomy: this.taxonomy,
            structuredSections: this.structuredSections,
            groundedOrders: this.groundedOrders
        };
        try {
            sessionStorage.setItem(key, JSON.stringify(state));
            localStorage.setItem(key, JSON.stringify(state));
            if (this.draftNotice) {
                this.draftNotice.textContent = `Draft auto-saved at ${state.timestamp}`;
            }
        } catch (e) {}
    }

    restoreDraftIfAvailable() {
        const key = this.getDraftStorageKey();
        const raw = sessionStorage.getItem(key) || localStorage.getItem(key);
        if (!raw) return;

        try {
            const state = JSON.parse(raw);
            if (state && (state.finalTranscript || (state.structuredSections && state.structuredSections.length > 0))) {
                this.finalTranscript = state.finalTranscript || '';
                this.secondsRecorded = state.secondsRecorded || 0;
                if (state.taxonomy) this.taxonomy = state.taxonomy;
                this.structuredSections = state.structuredSections || [];
                this.groundedOrders = state.groundedOrders || [];

                this.renderLiveTranscript('');
                this.updateTimerText();
                this.updateTaxonomyPills();
                this.renderStructuredSections();
                this.renderOrdersDeck();

                if (this.draftNotice) {
                    this.draftNotice.innerHTML = `Draft restored from ${state.timestamp || 'previous session'}. <a href="#" id="link-discard-draft" class="text-danger ms-1">Discard</a>`;
                    const discardLink = document.getElementById('link-discard-draft');
                    if (discardLink) {
                        discardLink.addEventListener('click', (e) => {
                            e.preventDefault();
                            this.discardDraft();
                        });
                    }
                }
            }
        } catch (e) {}
    }

    discardDraft() {
        this.clearPersistedDraft();
        this.finalTranscript = '';
        this.structuredSections = [];
        this.groundedOrders = [];
        this.secondsRecorded = 0;
        this.renderLiveTranscript('');
        this.updateTimerText();
        this.renderStructuredSections();
        this.renderOrdersDeck();
        if (this.draftNotice) this.draftNotice.textContent = '';
        if (typeof toastr !== 'undefined') {
            toastr.info('Draft cleared.');
        }
    }

    clearPersistedDraft() {
        const key = this.getDraftStorageKey();
        sessionStorage.removeItem(key);
        localStorage.removeItem(key);
    }
}

window.ClinicalAiScribeStudio = ClinicalAiScribeStudio;

