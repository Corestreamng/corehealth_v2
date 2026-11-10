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
        this.workspaceGrid = document.getElementById('scribe-workspace-grid');
        this.cardDictation = document.getElementById('scribe-card-dictation');
        this.cardNote = document.getElementById('scribe-card-note');
        this.stepNav = document.getElementById('scribe-step-nav');
        this.summaryDeck = document.getElementById('scribe-summary-deck');
        this.summaryContent = document.getElementById('scribe-summary-content');
        this.summaryPreviewTicker = document.getElementById('scribe-summary-preview-ticker');
        this.btnSummaryAudio = document.getElementById('btn-summary-audio-narrate');
        this.transcriptContainer = document.getElementById('scribe-live-transcript');
        this.waveformContainer = document.getElementById('scribe-waveform-container');
        this.recordingBadge = document.getElementById('scribe-recording-badge');
        this.recordingTimerEl = document.getElementById('scribe-recording-timer');
        this.sectionsContainer = document.getElementById('scribe-sections-container');
        this.ordersTableBody = document.getElementById('scribe-orders-tbody');
        this.ordersDeck = document.getElementById('scribe-orders-deck');
        this.ordersCountBadge = document.getElementById('scribe-orders-count-badge');
        this.ordersFreeformCount = document.getElementById('scribe-orders-freeform-count');
        this.ordersPreviewTicker = document.getElementById('scribe-orders-preview-ticker');
        this.stepOrdersCount = document.getElementById('scribe-step-orders-count');
        this.matcherModal = document.getElementById('scribe-catalog-matcher-modal');
        this.matcherSearchInput = document.getElementById('scribe-matcher-search-input');
        this.matcherResultsContainer = document.getElementById('scribe-matcher-results');
        this.matcherSubtitle = document.getElementById('scribe-matcher-subtitle');
        this.safetyWarningsContainer = document.getElementById('scribe-safety-warnings');
        this.draftNotice = document.getElementById('scribe-draft-notice');
        this.langSelect = document.getElementById('scribe-lang-select');
        this.headlineEl = document.getElementById('scribe-headline-text');
        this.briefStrip = document.getElementById('scribe-brief-strip');
        this.briefHighlight = document.getElementById('scribe-brief-highlight');
        this.briefExpandedPanel = document.getElementById('scribe-brief-expanded-panel');
        this.briefExpandedContent = document.getElementById('scribe-brief-expanded-content');
        this.btnToggleBrief = document.getElementById('btn-toggle-brief-expand');
        this.btnCollapseBrief = document.getElementById('btn-collapse-brief-panel');

        // State
        this.cockpitMode = 'dictate'; // 'summary', 'dictate', 'note', 'orders', 'balanced'
        this.summaryDeckState = 'docked'; // 'docked', 'expanded', 'maximized'
        this.ordersDeckState = 'docked'; // 'docked', 'expanded', 'maximized'
        this.cardFoldStates = { dictate: false, note: false };
        this.isNarrating = false;
        this.speechUtterance = null;
        this.activeMatchingOrderIndex = null;
        this.activeMatcherFilter = 'all';
        this.matcherDebounceTimer = null;
        this._currentSearchResults = [];
        this.isListening = false;
        this.isPaused = false;
        this.isNetworkError = false;
        this.networkRetryAttempts = 0;
        this.restartTimer = null;
        this.recognition = null;
        this.audioContext = null;
        this.audioAnalyser = null;
        this.audioStream = null;
        this.animFrameId = null;
        this.timerInterval = null;
        this.secondsRecorded = 0;
        this.finalTranscript = '';
        this.baselineEditorContent = '';
        this.cachedPatientBrief = '';
        this.dispatchedOrderKeys = new Set();
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
        this.syncPatientBriefFromCache();
    }

    init() {
        if (!this.overlay) return;

        this.initSpeechRecognition();
        this.bindEvents();
        this.updateTaxonomyPills();
        this.setCockpitMode(this.cockpitMode);
        this.setSummaryDeckState(this.summaryDeckState);
        this.setOrdersDeckState(this.ordersDeckState);
        this.updateSignButtonBadges();
        this.restoreDraftIfAvailable();
        this.syncPatientBriefFromCache();
    }

    syncPatientBriefFromCache() {
        const pId = this.patientId || window.currentPatientId || window.patientId || (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : null);
        const cached = window.cachedPatientSummary || (pId ? sessionStorage.getItem(`patient_brief_${pId}`) : null);
        if (cached) {
            this.setPatientBrief(cached);
        }
    }

    setPatientBrief(briefText) {
        if (!briefText || !briefText.trim()) return;
        this.cachedPatientBrief = briefText;
        if (this.summaryDeck) {
            this.summaryDeck.style.display = 'block';
        }
        if (this.briefStrip) {
            this.briefStrip.style.display = 'flex';
        }
        // Extract 1-2 highlight lines for summary deck ticker and brief strip
        const cleanForTicker = (str) => {
            return str.replace(/[*#_~`>-]/g, '').replace(/\s+/g, ' ').trim();
        };
        const lines = briefText.split('\n')
            .map(l => cleanForTicker(l))
            .filter(l => l.length > 5);
        const highlightText = lines.slice(0, 2).join(' • ') || 'Longitudinal EHR history linked';
        if (this.summaryPreviewTicker) {
            this.summaryPreviewTicker.textContent = highlightText;
            this.summaryPreviewTicker.title = highlightText;
        }
        if (this.summaryContent) {
            this.summaryContent.innerHTML = this.formatMarkdown(briefText);
        }
        if (this.briefHighlight) {
            this.briefHighlight.textContent = highlightText;
            this.briefHighlight.title = highlightText;
        }
        if (this.briefExpandedContent) {
            this.briefExpandedContent.innerHTML = this.formatMarkdown(briefText);
        }
    }

    formatMarkdown(text) {
        if (!text) return '';
        if (typeof marked !== 'undefined' && typeof marked.parse === 'function') {
            return marked.parse(text);
        }
        return text
            .replace(/^### (.*$)/gim, '<h3>$1</h3>')
            .replace(/^## (.*$)/gim, '<h2>$1</h2>')
            .replace(/^# (.*$)/gim, '<h1>$1</h1>')
            .replace(/\*\*(.*)\*\*/gim, '<strong>$1</strong>')
            .replace(/\*(.*)\*/gim, '<em>$1</em>')
            .replace(/^\- (.*$)/gim, '<ul><li>$1</li></ul>')
            .replace(/<\/ul>\n<ul>/gim, '')
            .replace(/\n/gim, '<br>');
    }

    getOrderKey(ord) {
        return `${ord.order_type || 'ORDER'}_${ord.catalog_id || ord.item_name.toLowerCase().trim()}`;
    }

    mergeGroundedOrders(incomingOrders) {
        const map = new Map();
        
        // Preserve existing orders already loaded
        this.groundedOrders.forEach(o => {
            const key = this.getOrderKey(o);
            map.set(key, o);
        });

        // Merge incoming grounded orders deduplicated
        incomingOrders.forEach(incoming => {
            const key = this.getOrderKey(incoming);
            if (map.has(key)) {
                const existing = map.get(key);
                if (this.dispatchedOrderKeys.has(key) || existing.is_dispatched) {
                    existing.is_dispatched = true;
                    existing.is_selected = false;
                } else {
                    if (incoming.dosage) existing.dosage = incoming.dosage;
                    if (incoming.instructions) existing.instructions = incoming.instructions;
                    if (incoming.indication) existing.indication = incoming.indication;
                }
            } else {
                const isDispatched = this.dispatchedOrderKeys.has(key);
                incoming.is_dispatched = isDispatched;
                incoming.is_selected = !isDispatched;
                map.set(key, incoming);
            }
        });

        this.groundedOrders = Array.from(map.values());
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
            this.isPaused = false;
            if (this.isNetworkError) {
                this.isNetworkError = false;
                this.networkRetryAttempts = 0;
                if (typeof toastr !== 'undefined') {
                    toastr.success('Voice dictation network connection restored.', 'Voice Reconnected');
                }
            }
            this.updateRecordingUI(true);
            this.startTimer();
            this.startWaveformVisualizer();
        };

        this.recognition.onend = () => {
            if (this.restartTimer) {
                clearTimeout(this.restartTimer);
                this.restartTimer = null;
            }

            if (this.isListening && !this.isPaused) {
                // If ending due to a network glitch, retry with gradual backoff; otherwise restart promptly for next sentence
                const delay = this.isNetworkError
                    ? Math.min(1200 * Math.pow(1.5, Math.max(0, this.networkRetryAttempts - 1)), 5000)
                    : 180;

                this.restartTimer = setTimeout(() => {
                    if (this.isListening && !this.isPaused) {
                        try {
                            this.recognition.start();
                        } catch (e) {
                            console.warn('Deferred speech recognition restart:', e);
                            setTimeout(() => {
                                if (this.isListening && !this.isPaused) {
                                    try { this.recognition.start(); } catch (err) {}
                                }
                            }, 300);
                        }
                    }
                }, delay);
            } else {
                this.updateRecordingUI(false);
                this.stopTimer();
                this.stopWaveformVisualizer();
            }
        };

        this.recognition.onerror = (e) => {
            console.warn('Speech recognition error:', e.error);

            if (e.error === 'network') {
                this.isNetworkError = true;
                this.networkRetryAttempts++;
                if (this.networkRetryAttempts <= 1 && typeof toastr !== 'undefined') {
                    toastr.warning('Speech recognition service network glitch. Automatically reconnecting...', 'Voice Network Error');
                }
                if (this.transcriptContainer && !this.finalTranscript.trim()) {
                    this.transcriptContainer.innerHTML = '<span class="text-warning"><i class="mdi mdi-wifi-off"></i> Speech recognition service connection interrupted. Reconnecting...<br><small class="text-muted">You can also type your notes directly here.</small></span>';
                }
            } else if (e.error === 'not-allowed' || e.error === 'service-not-allowed') {
                this.isListening = false;
                this.updateRecordingUI(false);
                this.stopTimer();
                this.stopWaveformVisualizer();
                if (typeof toastr !== 'undefined') {
                    toastr.error('Microphone access blocked. Please enable microphone permissions in your browser.', 'Microphone Blocked');
                }
                if (this.transcriptContainer && !this.finalTranscript.trim()) {
                    this.transcriptContainer.innerHTML = '<span class="text-danger"><i class="mdi mdi-microphone-off"></i> Microphone access blocked. Please allow microphone access or type notes directly.</span>';
                }
            } else if (e.error === 'audio-capture') {
                this.isListening = false;
                this.updateRecordingUI(false);
                this.stopTimer();
                this.stopWaveformVisualizer();
                if (typeof toastr !== 'undefined') {
                    toastr.warning('Microphone unavailable or in use by another application.', 'Audio Capture');
                }
            } else if (e.error === 'no-speech') {
                // Prolonged silence detected during consultation. onend will fire and restart with 180ms delay.
            }
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

        // Master & Card Accept & Sign Buttons (Always Available & Synchronized)
        document.querySelectorAll('.btn-scribe-sign, #btn-master-sign, #btn-scribe-sign').forEach(btn => {
            btn.addEventListener('click', () => this.acceptAndSign());
        });

        // Master & Card Edit Toggle Buttons
        document.querySelectorAll('.btn-scribe-edit, #btn-master-edit, #btn-scribe-edit').forEach(btn => {
            btn.addEventListener('click', () => this.toggleInlineEdit());
        });

        // Master & Card Discard Buttons
        document.querySelectorAll('.btn-scribe-reject, #btn-master-reject, #btn-scribe-reject').forEach(btn => {
            btn.addEventListener('click', () => this.discardDraft());
        });

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

        // Pillar 1: Summary Deck Toggle & Maximize
        const btnToggleSummaryHeader = document.getElementById('btn-toggle-summary-deck-header');
        if (btnToggleSummaryHeader) {
            btnToggleSummaryHeader.addEventListener('click', () => this.toggleSummaryDeck());
        }

        const btnFoldSummaryDeck = document.getElementById('btn-fold-summary-deck');
        if (btnFoldSummaryDeck) {
            btnFoldSummaryDeck.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleSummaryDeck();
            });
        }

        const btnMaximizeSummaryDeck = document.getElementById('btn-maximize-summary-deck');
        if (btnMaximizeSummaryDeck) {
            btnMaximizeSummaryDeck.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleMaximizeSummaryDeck();
            });
        }

        const btnSummaryAudio = document.getElementById('btn-summary-audio-narrate');
        if (btnSummaryAudio) {
            btnSummaryAudio.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleSummaryAudioNarration();
            });
        }

        // Patient Brief Toggle & Events
        if (this.btnToggleBrief && this.briefExpandedPanel) {
            this.btnToggleBrief.addEventListener('click', () => {
                this.briefExpandedPanel.classList.toggle('open');
            });
        }

        if (this.btnCollapseBrief && this.briefExpandedPanel) {
            this.btnCollapseBrief.addEventListener('click', () => {
                this.briefExpandedPanel.classList.remove('open');
            });
        }

        // Workflow Step Navigation
        if (this.stepNav) {
            this.stepNav.querySelectorAll('.scribe-step-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const step = btn.getAttribute('data-step');
                    if (step) this.setCockpitMode(step);
                });
            });
        }

        // Card Focus & Fold Controls
        const btnFocusDictate = document.getElementById('btn-focus-dictate');
        if (btnFocusDictate) {
            btnFocusDictate.addEventListener('click', (e) => {
                e.stopPropagation();
                this.setCockpitMode('dictate');
            });
        }

        const btnFoldDictate = document.getElementById('btn-fold-dictate');
        if (btnFoldDictate) {
            btnFoldDictate.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleCardFold('dictate');
            });
        }

        if (this.cardDictation) {
            this.cardDictation.addEventListener('click', () => {
                if (this.cardFoldStates.dictate) {
                    this.toggleCardFold('dictate');
                }
            });
        }

        const btnFocusNote = document.getElementById('btn-focus-note');
        if (btnFocusNote) {
            btnFocusNote.addEventListener('click', (e) => {
                e.stopPropagation();
                this.setCockpitMode('note');
            });
        }

        const btnFoldNote = document.getElementById('btn-fold-note');
        if (btnFoldNote) {
            btnFoldNote.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleCardFold('note');
            });
        }

        if (this.cardNote) {
            this.cardNote.addEventListener('click', () => {
                if (this.cardFoldStates.note) {
                    this.toggleCardFold('note');
                }
            });
        }

        // Orders Deck Toggle & Maximize
        const btnToggleDeckHeader = document.getElementById('btn-toggle-orders-deck-header');
        if (btnToggleDeckHeader) {
            btnToggleDeckHeader.addEventListener('click', () => this.toggleOrdersDeck());
        }

        const btnFoldOrdersDeck = document.getElementById('btn-fold-orders-deck');
        if (btnFoldOrdersDeck) {
            btnFoldOrdersDeck.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleOrdersDeck();
            });
        }

        const btnMaximizeOrdersDeck = document.getElementById('btn-maximize-orders-deck');
        if (btnMaximizeOrdersDeck) {
            btnMaximizeOrdersDeck.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleMaximizeOrdersDeck();
            });
        }

        // Catalog Matcher Modal Events
        const btnCloseMatcher = document.getElementById('btn-close-matcher');
        if (btnCloseMatcher) {
            btnCloseMatcher.addEventListener('click', () => this.closeCatalogMatcher());
        }

        const matcherBackdrop = document.getElementById('scribe-matcher-backdrop');
        if (matcherBackdrop) {
            matcherBackdrop.addEventListener('click', () => this.closeCatalogMatcher());
        }

        if (this.matcherSearchInput) {
            this.matcherSearchInput.addEventListener('input', () => {
                clearTimeout(this.matcherDebounceTimer);
                this.matcherDebounceTimer = setTimeout(() => {
                    this.executeCatalogSearch(this.matcherSearchInput.value, this.activeMatcherFilter);
                }, 300);
            });

            this.matcherSearchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(this.matcherDebounceTimer);
                    this.executeCatalogSearch(this.matcherSearchInput.value, this.activeMatcherFilter);
                }
            });
        }

        const btnMatcherSearchExec = document.getElementById('btn-matcher-search-exec');
        if (btnMatcherSearchExec) {
            btnMatcherSearchExec.addEventListener('click', () => {
                if (this.matcherSearchInput) {
                    this.executeCatalogSearch(this.matcherSearchInput.value, this.activeMatcherFilter);
                }
            });
        }

        document.querySelectorAll('.matcher-filter-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('.matcher-filter-chip').forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
                this.activeMatcherFilter = chip.getAttribute('data-filter') || 'all';
                const q = this.matcherSearchInput ? this.matcherSearchInput.value : '';
                this.executeCatalogSearch(q, this.activeMatcherFilter);
            });
        });

        // Live transcript direct typing & manual editing sync
        if (this.transcriptContainer) {
            this.transcriptContainer.addEventListener('input', () => {
                const clone = this.transcriptContainer.cloneNode(true);
                clone.querySelectorAll('.transcript-interim, .transcript-caret').forEach(el => el.remove());
                let text = clone.innerText || '';
                text = text.replace(/Listening for consultation dialogue\.\.\./g, '').trim();
                this.finalTranscript = text;
                this.scheduleDebouncedDraftSave();
            });
        }

        window.addEventListener('patient-brief-loaded', (e) => {
            if (e.detail && e.detail.summary) {
                this.setPatientBrief(e.detail.summary);
            }
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

        // Sync latest patient brief context
        this.syncPatientBriefFromCache();

        this.overlay.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Apply active cockpit step layout, summary deck & orders deck state
        this.setCockpitMode(this.cockpitMode || 'dictate');
        this.setSummaryDeckState(this.summaryDeckState || 'docked');
        this.setOrdersDeckState(this.ordersDeckState || (this.groundedOrders.length > 0 ? 'expanded' : 'docked'));
        this.updateOrdersDeckSummaryTicker();
        this.updateSignButtonBadges();

        // Auto-start dictation if supported
        this.startRecording();
    }

    close() {
        if (!this.overlay) return;
        this.stopRecording();
        if (this.isNarrating && window.speechSynthesis) {
            window.speechSynthesis.cancel();
            this.isNarrating = false;
            const textAudio = document.getElementById('text-summary-audio');
            const iconAudio = document.getElementById('icon-summary-audio');
            if (textAudio) textAudio.textContent = 'Listen';
            if (iconAudio) iconAudio.className = 'mdi mdi-volume-high';
        }
        this.overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    startRecording() {
        if (this.restartTimer) {
            clearTimeout(this.restartTimer);
            this.restartTimer = null;
        }
        if (this.recognition && !this.isListening) {
            try {
                this.isPaused = false;
                this.isNetworkError = false;
                this.networkRetryAttempts = 0;
                this.recognition.start();
            } catch (e) {
                console.warn('Recognition start exception:', e);
            }
        }
    }

    stopRecording() {
        if (this.restartTimer) {
            clearTimeout(this.restartTimer);
            this.restartTimer = null;
        }
        this.isListening = false;
        this.isPaused = false;
        this.isNetworkError = false;
        if (this.recognition) {
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

    getRawTranscriptText() {
        if (this.finalTranscript && this.finalTranscript.trim()) {
            return this.finalTranscript.trim();
        }
        if (this.transcriptContainer) {
            const clone = this.transcriptContainer.cloneNode(true);
            clone.querySelectorAll('.transcript-interim, .transcript-caret').forEach(el => el.remove());
            let text = clone.innerText || '';
            text = text.replace(/Listening for consultation dialogue\.\.\./g, '').trim();
            if (text) return text;
        }
        return '';
    }

    renderLiveTranscript(interim = '') {
        if (!this.transcriptContainer) return;
        const combined = this.finalTranscript + (interim ? `<span class="transcript-interim">${interim}</span>` : '');
        this.transcriptContainer.innerHTML = combined
            ? `${combined} <span class="transcript-caret"></span>`
            : '<span class="text-muted">Listening for consultation dialogue... (Speak or type directly here)</span> <span class="transcript-caret"></span>';
        this.transcriptContainer.scrollTop = this.transcriptContainer.scrollHeight;
    }

    async startWaveformVisualizer() {
        if (this.audioContext && this.audioContext.state !== 'closed') {
            if (this.audioContext.state === 'suspended') {
                try { await this.audioContext.resume(); } catch (e) {}
            }
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.audioStream = stream;
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            this.audioContext = new AudioContextClass();
            if (this.audioContext.state === 'suspended') {
                try { await this.audioContext.resume(); } catch (e) {}
            }

            const source = this.audioContext.createMediaStreamSource(stream);
            this.audioAnalyser = this.audioContext.createAnalyser();
            this.audioAnalyser.fftSize = 64;
            this.audioAnalyser.smoothingTimeConstant = 0.45; // Snappy vocal volume and pitch response
            source.connect(this.audioAnalyser);

            const bufferLength = this.audioAnalyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);
            const bars = document.querySelectorAll('.scribe-waveform-bar');
            const totalBars = bars.length || 24;

            const draw = () => {
                if (!this.audioAnalyser || !this.isListening || this.isPaused) return;
                this.audioAnalyser.getByteFrequencyData(dataArray);

                // Sample human voice spectrum (approx bins 1..10)
                let vocalSum = 0;
                let vocalCount = 0;
                for (let k = 1; k < Math.min(12, bufferLength); k++) {
                    vocalSum += dataArray[k];
                    vocalCount++;
                }
                const avgVocal = vocalCount > 0 ? (vocalSum / vocalCount) : 0;

                bars.forEach((bar, index) => {
                    const distFromCenter = Math.abs(index - (totalBars - 1) / 2) / ((totalBars - 1) / 2);
                    const weight = Math.cos(distFromCenter * Math.PI * 0.42); // Center-weighted curve
                    const binIdx = Math.min(bufferLength - 1, Math.floor(1 + (1 - distFromCenter) * 8) + (index % 2));
                    const binVal = dataArray[binIdx] || 0;

                    const combined = (binVal * 0.65) + (avgVocal * 0.35);
                    const scaled = (combined / 255) * weight * 54;
                    const height = Math.max(6, Math.min(52, 6 + scaled));
                    bar.style.height = `${height}px`;
                });

                this.animFrameId = requestAnimationFrame(draw);
            };

            draw();
        } catch (e) {
            console.warn('Web Audio visualizer could not start (falling back to dynamic pulse):', e);
            this.startSyntheticWaveform();
        }
    }

    startSyntheticWaveform() {
        if (this.animFrameId) return;
        const bars = document.querySelectorAll('.scribe-waveform-bar');
        const totalBars = bars.length || 24;
        let step = 0;

        const drawSynth = () => {
            if (!this.isListening || this.isPaused) return;
            step += 0.12;

            bars.forEach((bar, index) => {
                const distFromCenter = Math.abs(index - (totalBars - 1) / 2) / ((totalBars - 1) / 2);
                const weight = Math.cos(distFromCenter * Math.PI * 0.42);
                const wave = Math.sin(step + index * 0.35);
                const height = Math.max(6, 6 + (wave + 1) * 8 * weight);
                bar.style.height = `${height}px`;
            });

            this.animFrameId = requestAnimationFrame(drawSynth);
        };

        this.animFrameId = requestAnimationFrame(drawSynth);
    }

    stopWaveformVisualizer() {
        if (this.animFrameId) {
            cancelAnimationFrame(this.animFrameId);
            this.animFrameId = null;
        }
        if (this.audioStream) {
            try {
                this.audioStream.getTracks().forEach(track => track.stop());
            } catch (e) {}
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
                    patient_id: this.getPatientId(),
                    encounter_id: this.getEncounterId(),
                    role: this.role,
                    taxonomy: this.taxonomy,
                    transcript: this.getRawTranscriptText(),
                    editor_content: this.baselineEditorContent,
                    existing_sections: this.structuredSections,
                    existing_orders: this.groundedOrders,
                    patient_summary: window.cachedPatientSummary || this.cachedPatientBrief || '',
                    language: this.langSelect ? this.langSelect.value : this.defaultLanguage
                })
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to process scribe note.');
            }

            this.structuredSections = data.structured_sections || [];
            
            // Deduplicate incoming orders against existing items and dispatch records
            this.mergeGroundedOrders(data.grounded_orders || []);

            if (this.headlineEl && data.summary_headline) {
                this.headlineEl.textContent = data.summary_headline;
            }

            // Clear the consumed dictation transcript now that it has been synthesized into the structured note
            this.finalTranscript = '';
            this.interimTranscript = '';
            this.secondsRecorded = 0;
            this.renderLiveTranscript('');
            this.updateTimerText();

            this.renderStructuredSections();
            this.renderOrdersDeck();
            this.renderSafetyWarnings(data.safety_warnings || []);
            this.scheduleDebouncedDraftSave();

            // Step-adaptive cockpit focus: expand orders deck and focus note card so both are visible
            if (this.groundedOrders && this.groundedOrders.length > 0) {
                this.setOrdersDeckState('expanded');
                this.setCockpitMode('note');
            } else {
                this.setCockpitMode('note');
            }

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

            let displayContent = sec.content || '';
            if (typeof displayContent === 'string' && (displayContent.startsWith('```json') || displayContent.startsWith('{ "summary_headline"') || displayContent.startsWith('{"summary_headline"'))) {
                try {
                    const cleanJson = displayContent.replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/i, '').trim();
                    const parsedObj = JSON.parse(cleanJson);
                    if (parsedObj.structured_sections && Array.isArray(parsedObj.structured_sections) && parsedObj.structured_sections.length > 0) {
                        this.structuredSections = parsedObj.structured_sections;
                        setTimeout(() => this.renderStructuredSections(), 0);
                        return;
                    }
                } catch (e) {
                    displayContent = displayContent.replace(/```(?:json)?/gi, '')
                        .replace(/"(?:summary_headline|taxonomy_type|key|title|content)"\s*:\s*/g, '')
                        .replace(/[{\[\]}"]/g, '')
                        .trim();
                }
            }

            const text = document.createElement('p');
            text.className = 'scribe-section-text';
            text.textContent = displayContent;
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

        // Toggle visibility of the Free-Form Warning Banner
        const warningBanner = document.getElementById('scribe-free-form-warning-banner');
        const hasFreeForm = this.groundedOrders.some(o => o.is_free_form || !o.catalog_id);
        if (warningBanner) {
            warningBanner.style.display = hasFreeForm ? 'flex' : 'none';
        }

        this.groundedOrders.forEach((ord, idx) => {
            const tr = document.createElement('tr');
            tr.className = 'scribe-order-row' + (ord.is_dispatched ? ' order-row-dispatched' : '');

            // Refined categorical badges mapped to appsettings service types
            let catClass = 'cat-service';
            let catLabel = ord.category_name || ord.order_type;

            if (ord.order_type === 'PRODUCT') {
                catClass = 'cat-product';
                catLabel = 'PRESCRIPTION';
            } else if (ord.order_type === 'REFERRAL') {
                catClass = 'cat-referral';
                catLabel = 'REFERRAL';
            } else if (ord.order_type === 'FOLLOW_UP') {
                catClass = 'cat-followup';
                catLabel = 'REVIEW';
            } else if (ord.order_type === 'SERVICE') {
                if (ord.service_type === 'lab' || ord.target_queue === 'lab_queue') {
                    catClass = 'cat-lab';
                    catLabel = 'LABORATORY';
                } else if (ord.service_type === 'imaging' || ord.target_queue === 'imaging_queue') {
                    catClass = 'cat-imaging';
                    catLabel = 'IMAGING / X-RAY';
                } else if (ord.service_type === 'procedure' || ord.target_queue === 'procedure_queue') {
                    catClass = 'cat-procedure';
                    catLabel = 'PROCEDURE';
                } else if (ord.service_type === 'nursing' || ord.target_queue === 'nursing_queue') {
                    catClass = 'cat-nursing';
                    catLabel = 'NURSING';
                } else if (ord.service_type === 'consultation' || ord.target_queue === 'consultation_queue') {
                    catClass = 'cat-consultation';
                    catLabel = 'CONSULTATION';
                }
            }

            const isProduct = (ord.order_type === 'PRODUCT');
            const isReferral = (ord.order_type === 'REFERRAL');
            const isFollowUp = (ord.order_type === 'FOLLOW_UP');
            const isDispatched = !!ord.is_dispatched;

            let col4Html = '';
            if (isProduct) {
                const val = this.escapeHtml(ord.dosage || '');
                col4Html = `<input type="text" class="order-inline-input order-spec-input" data-idx="${idx}" data-field="dosage" value="${val}" placeholder="500mg | TID | 3 days | Oral" ${isDispatched ? 'disabled' : ''}>`;
            } else if (isReferral) {
                const val = this.escapeHtml(ord.specialization || '');
                col4Html = `<input type="text" class="order-inline-input order-spec-input" data-idx="${idx}" data-field="specialization" value="${val}" placeholder="e.g. General Surgery / Cardiology" ${isDispatched ? 'disabled' : ''}>`;
            } else if (isFollowUp) {
                const val = this.escapeHtml(ord.duration_days ? `${ord.duration_days} days` : '7 days');
                col4Html = `<input type="text" class="order-inline-input order-spec-input" data-idx="${idx}" data-field="duration_days" value="${val}" placeholder="e.g. 7 days / 2 weeks" ${isDispatched ? 'disabled' : ''}>`;
            } else {
                const val = this.escapeHtml(ord.indication || ord.reason || '');
                col4Html = `<input type="text" class="order-inline-input order-spec-input" data-idx="${idx}" data-field="indication" value="${val}" placeholder="e.g. Confirm appendicitis / Rule out anemia" ${isDispatched ? 'disabled' : ''}>`;
            }

            const cleanInst = (ord.instructions && !['instructions...', 'routine', 'none', 'n/a'].includes(ord.instructions.toLowerCase()))
                ? this.escapeHtml(ord.instructions)
                : '';
            const instPlaceholder = isProduct
                ? 'e.g. Take after meals / Stat / Before bed'
                : 'e.g. Fasting sample / Stat / Pelvic prep';
            const col5Html = `<input type="text" class="order-inline-input order-instruction-input" data-idx="${idx}" data-field="instructions" value="${cleanInst}" placeholder="${instPlaceholder}" ${isDispatched ? 'disabled' : ''}>`;

            const statusBadge = isDispatched
                ? `<span class="badge bg-success-subtle text-success border border-success-subtle px-1 py-0 ms-1" style="font-size: 0.65rem; font-weight: 700;"><i class="mdi mdi-check-all"></i> DISPATCHED</span>`
                : `<span class="badge bg-light text-muted border px-1 py-0 ms-1" style="font-size: 0.65rem;">Pending</span>`;

            // Explicit Free-Form vs Matched Catalog indicator
            const isFreeForm = !!(ord.is_free_form || !ord.catalog_id);
            const catalogPill = !isFreeForm
                ? `<span class="badge bg-success-subtle text-success border border-success-subtle ms-1" title="Hospital Catalog ID: #${this.escapeHtml(String(ord.catalog_id))}"><i class="mdi mdi-check-circle"></i> #${this.escapeHtml(String(ord.catalog_id))}</span> <button type="button" class="btn btn-xs btn-link text-muted p-0 ms-1 btn-open-catalog-matcher" data-idx="${idx}" title="Change catalog item link"><i class="mdi mdi-swap-horizontal"></i> Change</button>`
                : `<span class="badge bg-warning text-dark border border-warning ms-1 fw-bold" title="Free-form item: Not found in hospital catalog"><i class="mdi mdi-alert-circle"></i> FREE-FORM</span> <button type="button" class="btn btn-xs btn-outline-primary rounded-pill py-0 px-2 ms-2 btn-open-catalog-matcher" data-idx="${idx}" title="Search and link to official hospital catalog item"><i class="mdi mdi-database-search"></i> Match Catalog</button>`;

            const queueFriendly = ord.target_queue === 'lab_queue' ? 'Laboratory Queue'
                : (ord.target_queue === 'imaging_queue' ? 'Radiology / Imaging Queue'
                : (ord.target_queue === 'procedure_queue' ? 'Procedure Suite'
                : (ord.target_queue === 'dispensing_queue' ? 'Pharmacy Dispensing'
                : (ord.target_queue === 'referral_queue' ? 'Specialist Referral'
                : (ord.target_queue === 'appointment_queue' ? 'Appointment Queue' : 'General Queue')))));

            const chkHtml = isDispatched
                ? `<input type="checkbox" class="order-chk" data-idx="${idx}" disabled checked title="Order already dispatched to hospital queue">`
                : `<input type="checkbox" class="order-chk" data-idx="${idx}" ${ord.is_selected ? 'checked' : ''}>`;

            const delHtml = isDispatched
                ? `<span class="text-muted small" title="Dispatched"><i class="mdi mdi-lock-outline"></i></span>`
                : `<button type="button" class="btn-order-action btn-del-order" data-idx="${idx}" title="Remove Order"><i class="mdi mdi-close"></i></button>`;

            tr.innerHTML = `
                <td style="width: 35px; text-align: center;">
                    ${chkHtml}
                </td>
                <td style="width: 130px;">
                    <span class="scribe-order-cat-badge ${catClass}">${catLabel}</span>
                </td>
                <td style="min-width: 180px;">
                    <div class="d-flex align-items-center">
                        <strong class="text-dark">${this.escapeHtml(ord.item_name)}</strong>
                        ${statusBadge}
                    </div>
                    <small class="text-muted d-block">${catalogPill} · ${queueFriendly}</small>
                </td>
                <td style="min-width: 240px; width: 280px;">
                    ${col4Html}
                </td>
                <td style="min-width: 260px; width: 320px;">
                    ${col5Html}
                </td>
                <td style="width: 45px; text-align: center;">
                    ${delHtml}
                </td>
            `;

            this.ordersTableBody.appendChild(tr);
        });

        // Select All checkbox (affects non-dispatched pending items only)
        const chkAll = document.getElementById('chk-all-orders');
        const pendingOrders = this.groundedOrders.filter(o => !o.is_dispatched);
        if (chkAll) {
            chkAll.checked = pendingOrders.length > 0 && pendingOrders.every(o => o.is_selected);
            chkAll.disabled = pendingOrders.length === 0;
            chkAll.onchange = (e) => {
                const checked = e.target.checked;
                pendingOrders.forEach(o => o.is_selected = checked);
                this.ordersTableBody.querySelectorAll('.order-chk:not([disabled])').forEach(c => c.checked = checked);
                this.updateSignButtonBadges();
                this.scheduleDebouncedDraftSave();
            };
        }

        // Bind interactive events
        this.ordersTableBody.querySelectorAll('.order-chk:not([disabled])').forEach(chk => {
            chk.addEventListener('change', (e) => {
                const i = parseInt(e.target.getAttribute('data-idx'));
                if (this.groundedOrders[i] && !this.groundedOrders[i].is_dispatched) {
                    this.groundedOrders[i].is_selected = e.target.checked;
                    if (chkAll) chkAll.checked = pendingOrders.every(o => o.is_selected);
                    this.updateSignButtonBadges();
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

        // Catalog Matcher Popover Triggers for Free-Form items
        this.ordersTableBody.querySelectorAll('.btn-open-catalog-matcher').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const i = parseInt(btn.getAttribute('data-idx'));
                this.openCatalogMatcher(i);
            });
        });

        this.updateOrdersDeckSummaryTicker();
        this.updateSignButtonBadges();
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
        document.querySelectorAll('.btn-scribe-edit, #btn-master-edit, #btn-scribe-edit').forEach(btn => {
            btn.textContent = this.isEditing ? 'Done Editing' : 'Edit';
            btn.classList.toggle('btn-primary', this.isEditing);
        });
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

        const signBtns = document.querySelectorAll('.btn-scribe-sign, #btn-master-sign, #btn-scribe-sign');
        signBtns.forEach(b => {
            if (!b.getAttribute('data-original-label')) {
                const labelSpan = b.querySelector('.sign-btn-label') || b.querySelector('span');
                if (labelSpan && labelSpan.textContent.trim()) {
                    b.setAttribute('data-original-label', labelSpan.textContent.trim());
                }
            }
            b.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Committing...';
            b.disabled = true;
        });

        try {
            // Build semantic HTML string for target CKEditor
            let finalHtml = '';
            this.structuredSections.forEach(sec => {
                finalHtml += `<h3>${this.escapeHtml(sec.title || sec.key)}</h3><p>${sec.content}</p>`;
            });

            // Filter newly selected orders to dispatch and deduplicate against already dispatched items
            const newlySelectedOrders = this.groundedOrders.filter(o => o.is_selected && !o.is_dispatched);
            const allDispatchedOrders = this.groundedOrders.filter(o => o.is_dispatched || (o.is_selected && !o.is_dispatched));

            // Dispatched Orders & Requests manifest appended below a dividing rule
            if (allDispatchedOrders.length > 0) {
                finalHtml += '<hr><h4 style="margin-top: 1.25rem; color: #1e293b;">Dispatched Clinical Orders & Requests:</h4><ul>';
                allDispatchedOrders.forEach(ord => {
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

            // Dispatch only newly selected orders through CoreHealth standard workbench APIs
            if (newlySelectedOrders.length > 0) {
                await this.dispatchOrdersThroughStandardApis(newlySelectedOrders);
                newlySelectedOrders.forEach(ord => {
                    const key = this.getOrderKey(ord);
                    ord.is_dispatched = true;
                    ord.is_selected = false;
                    this.dispatchedOrderKeys.add(key);
                });
                this.renderOrdersDeck();
            }

            // Record signed audit trail
            await fetch('/llm/clinical-scribe/sign', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    patient_id: this.getPatientId(),
                    encounter_id: this.getEncounterId(),
                    role: this.role,
                    taxonomy_type: this.taxonomy,
                    final_signed_note: finalHtml,
                    original_transcript: this.getRawTranscriptText(),
                    baseline_editor_content: this.baselineEditorContent,
                    structured_note_json: this.structuredSections,
                    orders_dispatched_json: allDispatchedOrders
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
            const signBtns = document.querySelectorAll('.btn-scribe-sign, #btn-master-sign, #btn-scribe-sign');
            signBtns.forEach(b => {
                const origLabel = b.getAttribute('data-original-label') || 'Accept & sign';
                b.innerHTML = `<i class="mdi mdi-check-circle"></i> <span class="sign-btn-label">${origLabel}</span> <span class="badge bg-white text-success rounded-pill ms-1 scribe-sign-orders-badge" style="display: none;">0</span>`;
                b.disabled = false;
            });
            this.updateSignButtonBadges();
        }
    }

    getEncounterId() {
        return this.encounterId ||
            window.currentEncounterId ||
            window.encounterId ||
            (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.encounterId : null) ||
            (typeof encounterId !== 'undefined' ? encounterId : null) ||
            (window.$ ? ($('#encounter_id__').val() || $('#editEncounterId').val()) : null) ||
            null;
    }

    getPatientId() {
        return this.patientId ||
            window.currentPatientId ||
            window.patientId ||
            (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : null) ||
            (typeof patientId !== 'undefined' ? patientId : null) ||
            (window.$ ? $('#patient_id__').val() : null) ||
            null;
    }

    async dispatchOrdersThroughStandardApis(orders) {
        if (!orders || orders.length === 0) return;

        const encId = this.getEncounterId();
        const patId = this.getPatientId();
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.csrf : '');

        for (const ord of orders) {
            try {
                const targetRefId = ord.catalog_id ? ord.catalog_id : ('FF_' + ord.item_name);

                if (this.role === 'doctor') {
                    if (ord.order_type === 'PRODUCT') {
                        // 1. Transactional Prescription subtab integration (creates ProductRequest & populates #selected-products)
                        if (typeof window.setSearchValProd === 'function') {
                            window.setSearchValProd(
                                ord.item_name,
                                targetRefId,
                                ord.unit_price || 0,
                                null,
                                null,
                                null,
                                ord.dosage || ''
                            );
                        } else if (encId) {
                            const resp = await fetch(`/encounters/${encId}/add-prescription`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify({
                                    product_id: String(targetRefId),
                                    dose: ord.dosage || ''
                                })
                            });
                            const result = await resp.json().catch(() => ({}));
                            if (!resp.ok || !result.success) {
                                console.warn('add-prescription warning:', result);
                            }
                        }
                    } else if (ord.order_type === 'SERVICE') {
                        const isLab = (ord.service_type === 'lab' || ord.target_queue === 'lab_queue' || (ord.category_name && ord.category_name.toLowerCase().includes('lab')));
                        const isImaging = (ord.service_type === 'imaging' || ord.target_queue === 'imaging_queue' || (ord.category_name && (ord.category_name.toLowerCase().includes('imag') || ord.category_name.toLowerCase().includes('radio') || ord.category_name.toLowerCase().includes('x-ray'))));

                        if (isLab) {
                            // 2. Transactional Lab subtab integration (creates LabServiceRequest & populates #selected-services)
                            if (typeof window.setSearchValSer === 'function') {
                                window.setSearchValSer(ord.item_name, targetRefId, ord.unit_price || 0);
                                if (ord.instructions || ord.indication) {
                                    setTimeout(() => {
                                        const noteInput = document.querySelector(`#selected-services tr[data-service-id="${targetRefId}"] input[name="consult_invest_note[]"]`);
                                        if (noteInput) {
                                            noteInput.value = ord.instructions || ord.indication;
                                            noteInput.dispatchEvent(new Event('blur'));
                                        }
                                    }, 200);
                                }
                            } else if (encId) {
                                await fetch(`/encounters/${encId}/add-lab`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken
                                    },
                                    body: JSON.stringify({
                                        service_id: String(targetRefId),
                                        note: ord.instructions || ord.indication || ''
                                    })
                                });
                            }
                        } else if (isImaging) {
                            // 3. Transactional Imaging subtab integration (creates LabServiceRequest with is_imaging & populates #selected-imaging-services)
                            if (typeof window.setSearchValImaging === 'function') {
                                window.setSearchValImaging(ord.item_name, targetRefId, ord.unit_price || 0);
                                if (ord.instructions || ord.indication) {
                                    setTimeout(() => {
                                        const noteInput = document.querySelector(`#selected-imaging-services tr[data-service-id="${targetRefId}"] input[name="consult_imaging_note[]"]`);
                                        if (noteInput) {
                                            noteInput.value = ord.instructions || ord.indication;
                                            noteInput.dispatchEvent(new Event('blur'));
                                        }
                                    }, 200);
                                }
                            } else if (encId) {
                                await fetch(`/encounters/${encId}/add-imaging`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken
                                    },
                                    body: JSON.stringify({
                                        service_id: String(targetRefId),
                                        note: ord.instructions || ord.indication || ''
                                    })
                                });
                            }
                        } else {
                            // Procedure or other clinical service
                            if (encId) {
                                await fetch(`/encounters/${encId}/add-procedure`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken
                                    },
                                    body: JSON.stringify({
                                        service_id: String(targetRefId),
                                        priority: 'routine',
                                        pre_notes: ord.instructions || ord.indication || ''
                                    })
                                });
                            }
                        }
                    } else if (ord.order_type === 'REFERRAL' && encId) {
                        await fetch(`/encounters/${encId}/referrals`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                referral_type: 'internal',
                                reason: ord.reason || ord.item_name,
                                urgency: ord.urgency || 'routine',
                                clinical_summary: ord.clinical_summary || ''
                            })
                        });
                    } else if (ord.order_type === 'FOLLOW_UP' && encId) {
                        const targetDate = new Date();
                        targetDate.setDate(targetDate.getDate() + (ord.duration_days || 7));
                        const dateStr = targetDate.toISOString().split('T')[0];

                        await fetch(`/encounters/${encId}/schedule-followup`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                appointment_date: dateStr,
                                reason: ord.reason || 'Routine follow-up'
                            })
                        });
                    }
                } else if (this.role === 'nurse') {
                    // Match Nursing Workbench billing & clinical request APIs
                    if (ord.order_type === 'SERVICE') {
                        await fetch('/nursing-workbench/add-lab', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                patient_id: patId,
                                service_id: targetRefId,
                                note: ord.instructions || ord.indication || ''
                            })
                        });
                    } else if (ord.order_type === 'PRODUCT') {
                        await fetch('/nursing-workbench/clinical-requests/add-prescription', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                patient_id: patId,
                                product_id: targetRefId,
                                dose: ord.dosage || ''
                            })
                        });
                    }
                } else if (this.role === 'maternity') {
                    const enrollmentId = window.enrollmentId || (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.enrollmentId : null);
                    if (enrollmentId) {
                        if (ord.order_type === 'PRODUCT') {
                            await fetch(`/maternity-workbench/enrollment/${enrollmentId}/add-prescription`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify({
                                    product_id: targetRefId,
                                    dose: ord.dosage || ''
                                })
                            });
                        } else if (ord.order_type === 'SERVICE') {
                            await fetch(`/maternity-workbench/enrollment/${enrollmentId}/add-lab`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify({
                                    service_id: targetRefId,
                                    note: ord.instructions || ord.indication || ''
                                })
                            });
                        }
                    }
                }
            } catch (err) {
                console.warn('Dispatch order error for item:', ord.item_name, err);
            }
        }

        // Synchronize and reload all active history DataTables immediately
        if (window.$ && $.fn && $.fn.DataTable) {
            if ($.fn.DataTable.isDataTable('#presc_history_list')) {
                $('#presc_history_list').DataTable().ajax.reload(null, false);
            }
            if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
                $('#investigation_history_list').DataTable().ajax.reload(null, false);
            }
            if ($.fn.DataTable.isDataTable('#imaging_history_list')) {
                $('#imaging_history_list').DataTable().ajax.reload(null, false);
            }
            if ($.fn.DataTable.isDataTable('#procedure_history_list')) {
                $('#procedure_history_list').DataTable().ajax.reload(null, false);
            }
            // Nursing workbench DataTables
            if ($.fn.DataTable.isDataTable('#cr_presc_history_list')) {
                $('#cr_presc_history_list').DataTable().ajax.reload(null, false);
            }
            if ($.fn.DataTable.isDataTable('#cr_lab_history_list')) {
                $('#cr_lab_history_list').DataTable().ajax.reload(null, false);
            }
            if ($.fn.DataTable.isDataTable('#cr_imaging_history_list')) {
                $('#cr_imaging_history_list').DataTable().ajax.reload(null, false);
            }
        }

        // Notify workbench listeners
        if (window.$) {
            $(document).trigger('clinical-orders-dispatched', [orders]);
        }
        window.dispatchEvent(new CustomEvent('clinical-orders-dispatched', { detail: { orders } }));
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
            cockpitMode: this.cockpitMode,
            summaryDeckState: this.summaryDeckState,
            ordersDeckState: this.ordersDeckState,
            cardFoldStates: this.cardFoldStates,
            structuredSections: this.structuredSections,
            groundedOrders: this.groundedOrders,
            dispatchedOrderKeys: Array.from(this.dispatchedOrderKeys)
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
            if (state && (state.finalTranscript || (state.structuredSections && state.structuredSections.length > 0) || (state.groundedOrders && state.groundedOrders.length > 0))) {
                this.finalTranscript = state.finalTranscript || '';
                this.secondsRecorded = state.secondsRecorded || 0;
                if (state.taxonomy) this.taxonomy = state.taxonomy;
                if (state.cockpitMode) this.cockpitMode = state.cockpitMode;
                if (state.summaryDeckState) this.summaryDeckState = state.summaryDeckState;
                if (state.ordersDeckState) this.ordersDeckState = state.ordersDeckState;
                if (state.cardFoldStates) this.cardFoldStates = state.cardFoldStates;
                this.structuredSections = state.structuredSections || [];
                this.groundedOrders = state.groundedOrders || [];
                if (state.dispatchedOrderKeys && Array.isArray(state.dispatchedOrderKeys)) {
                    this.dispatchedOrderKeys = new Set(state.dispatchedOrderKeys);
                }

                this.renderLiveTranscript('');
                this.updateTimerText();
                this.updateTaxonomyPills();
                this.setCockpitMode(this.cockpitMode);
                this.setSummaryDeckState(this.summaryDeckState);
                this.setOrdersDeckState(this.ordersDeckState);
                this.updateSignButtonBadges();
                if (this.cardFoldStates.dictate) this.cardDictation?.classList.add('card-folded');
                if (this.cardFoldStates.note) this.cardNote?.classList.add('card-folded');
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
        if (this.isNarrating && window.speechSynthesis) {
            window.speechSynthesis.cancel();
            this.isNarrating = false;
            const textAudio = document.getElementById('text-summary-audio');
            const iconAudio = document.getElementById('icon-summary-audio');
            if (textAudio) textAudio.textContent = 'Listen';
            if (iconAudio) iconAudio.className = 'mdi mdi-volume-high';
        }
        this.finalTranscript = '';
        this.structuredSections = [];
        this.groundedOrders = [];
        this.secondsRecorded = 0;
        this.cardFoldStates = { dictate: false, note: false };
        this.cardDictation?.classList.remove('card-folded');
        this.cardNote?.classList.remove('card-folded');
        this.setCockpitMode('dictate');
        this.setSummaryDeckState('docked');
        this.setOrdersDeckState('docked');
        this.updateSignButtonBadges();
        this.renderLiveTranscript('');
        this.updateTimerText();
        this.renderStructuredSections();
        this.renderOrdersDeck();
        if (this.draftNotice) this.draftNotice.textContent = '';
        if (typeof toastr !== 'undefined') {
            toastr.info('Draft cleared.');
        }
    }

    setCockpitMode(mode = 'dictate') {
        this.cockpitMode = mode;
        if (!this.workspaceGrid) this.workspaceGrid = document.getElementById('scribe-workspace-grid');
        if (!this.workspaceGrid) return;

        // Reset folded cards if user switches step mode
        if (this.cardFoldStates.dictate) {
            this.cardFoldStates.dictate = false;
            this.cardDictation?.classList.remove('card-folded');
        }
        if (this.cardFoldStates.note) {
            this.cardFoldStates.note = false;
            this.cardNote?.classList.remove('card-folded');
        }

        // Update step buttons UI
        document.querySelectorAll('.scribe-step-btn').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-step') === mode);
        });

        if (mode === 'summary') {
            this.workspaceGrid.className = 'scribe-workspace-grid grid-balanced';
            this.setSummaryDeckState('maximized');
            if (this.ordersDeckState === 'maximized') {
                this.setOrdersDeckState('docked');
            }
        } else if (mode === 'dictate') {
            this.workspaceGrid.className = 'scribe-workspace-grid grid-focus-dictate';
            if (this.summaryDeckState === 'maximized') {
                this.setSummaryDeckState('docked');
            }
            if (this.ordersDeckState === 'maximized') {
                this.setOrdersDeckState('docked');
            }
        } else if (mode === 'note') {
            this.workspaceGrid.className = 'scribe-workspace-grid grid-focus-note';
            if (this.summaryDeckState === 'maximized') {
                this.setSummaryDeckState('docked');
            }
            if (this.ordersDeckState === 'maximized') {
                this.setOrdersDeckState(this.groundedOrders.length > 0 ? 'expanded' : 'docked');
            }
        } else if (mode === 'orders') {
            this.workspaceGrid.className = 'scribe-workspace-grid grid-balanced';
            if (this.summaryDeckState === 'maximized') {
                this.setSummaryDeckState('docked');
            }
            this.setOrdersDeckState('maximized');
        } else if (mode === 'balanced') {
            this.workspaceGrid.className = 'scribe-workspace-grid grid-balanced';
            if (this.summaryDeckState === 'maximized') {
                this.setSummaryDeckState('docked');
            }
            if (this.ordersDeckState === 'maximized') {
                this.setOrdersDeckState(this.groundedOrders.length > 0 ? 'expanded' : 'docked');
            }
        }
        this.scheduleDebouncedDraftSave();
    }

    toggleCardFold(cardKey = 'dictate') {
        if (!this.workspaceGrid) this.workspaceGrid = document.getElementById('scribe-workspace-grid');
        if (!this.workspaceGrid) return;

        if (cardKey === 'dictate') {
            this.cardFoldStates.dictate = !this.cardFoldStates.dictate;
            if (this.cardDictation) {
                this.cardDictation.classList.toggle('card-folded', this.cardFoldStates.dictate);
            }
            if (this.cardFoldStates.dictate) {
                this.cardFoldStates.note = false;
                this.cardNote?.classList.remove('card-folded');
                this.workspaceGrid.className = 'scribe-workspace-grid grid-dictate-folded';
            } else {
                this.setCockpitMode(this.cockpitMode);
            }
        } else if (cardKey === 'note') {
            this.cardFoldStates.note = !this.cardFoldStates.note;
            if (this.cardNote) {
                this.cardNote.classList.toggle('card-folded', this.cardFoldStates.note);
            }
            if (this.cardFoldStates.note) {
                this.cardFoldStates.dictate = false;
                this.cardDictation?.classList.remove('card-folded');
                this.workspaceGrid.className = 'scribe-workspace-grid grid-note-folded';
            } else {
                this.setCockpitMode(this.cockpitMode);
            }
        }
        this.scheduleDebouncedDraftSave();
    }

    setSummaryDeckState(state = 'docked') {
        this.summaryDeckState = state;
        if (!this.summaryDeck) this.summaryDeck = document.getElementById('scribe-summary-deck');
        if (!this.summaryDeck) return;

        this.summaryDeck.classList.remove('summary-docked', 'summary-expanded', 'summary-maximized');
        this.summaryDeck.classList.add(`summary-${state}`);

        const textFold = document.getElementById('text-fold-summary-deck');
        const iconFold = document.getElementById('icon-fold-summary-deck');
        const iconMax = document.getElementById('icon-max-summary-deck');

        if (this.overlay) {
            this.overlay.classList.toggle('cockpit-summary-focus', state === 'maximized');
        }

        if (state === 'docked') {
            if (textFold) textFold.textContent = 'Expand';
            if (iconFold) iconFold.className = 'mdi mdi-chevron-down';
            if (iconMax) iconMax.className = 'mdi mdi-arrow-expand';
            if (this.cockpitMode === 'summary') {
                this.setCockpitMode('dictate');
            }
        } else if (state === 'expanded') {
            if (textFold) textFold.textContent = 'Dock';
            if (iconFold) iconFold.className = 'mdi mdi-chevron-up';
            if (iconMax) iconMax.className = 'mdi mdi-arrow-expand';
            if (this.cockpitMode === 'summary') {
                this.setCockpitMode('dictate');
            }
        } else if (state === 'maximized') {
            if (textFold) textFold.textContent = 'Dock';
            if (iconFold) iconFold.className = 'mdi mdi-chevron-up';
            if (iconMax) iconMax.className = 'mdi mdi-arrow-collapse';
            document.querySelectorAll('.scribe-step-btn').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-step') === 'summary');
            });
        }

        this.scheduleDebouncedDraftSave();
    }

    toggleSummaryDeck() {
        if (this.summaryDeckState === 'docked') {
            this.setSummaryDeckState('expanded');
        } else {
            this.setSummaryDeckState('docked');
        }
    }

    toggleMaximizeSummaryDeck() {
        if (this.summaryDeckState === 'maximized') {
            this.setSummaryDeckState('expanded');
        } else {
            this.setSummaryDeckState('maximized');
        }
    }

    toggleSummaryAudioNarration() {
        if (!('speechSynthesis' in window)) {
            if (typeof toastr !== 'undefined') {
                toastr.warning('Speech synthesis is not supported by your browser.');
            }
            return;
        }

        const textAudio = document.getElementById('text-summary-audio');
        const iconAudio = document.getElementById('icon-summary-audio');

        if (this.isNarrating) {
            window.speechSynthesis.cancel();
            this.isNarrating = false;
            if (textAudio) textAudio.textContent = 'Listen';
            if (iconAudio) iconAudio.className = 'mdi mdi-volume-high';
            return;
        }

        let summaryText = this.cachedPatientBrief || (this.summaryContent ? this.summaryContent.innerText : '');
        if (!summaryText || !summaryText.trim()) {
            if (typeof toastr !== 'undefined') {
                toastr.info('No patient summary content available to narrate.');
            }
            return;
        }

        // Clean markdown tokens from text before speaking
        summaryText = summaryText
            .replace(/[#*_~`>-]/g, ' ')
            .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
            .replace(/\s+/g, ' ')
            .trim();

        window.speechSynthesis.cancel();
        this.speechUtterance = new SpeechSynthesisUtterance(summaryText);
        this.speechUtterance.lang = this.langSelect?.value || 'en-NG';
        this.speechUtterance.rate = 1.0;

        this.speechUtterance.onstart = () => {
            this.isNarrating = true;
            if (textAudio) textAudio.textContent = 'Stop';
            if (iconAudio) iconAudio.className = 'mdi mdi-stop-circle text-danger';
        };

        this.speechUtterance.onend = () => {
            this.isNarrating = false;
            if (textAudio) textAudio.textContent = 'Listen';
            if (iconAudio) iconAudio.className = 'mdi mdi-volume-high';
        };

        this.speechUtterance.onerror = (e) => {
            console.warn('Speech synthesis error:', e);
            this.isNarrating = false;
            if (textAudio) textAudio.textContent = 'Listen';
            if (iconAudio) iconAudio.className = 'mdi mdi-volume-high';
        };

        window.speechSynthesis.speak(this.speechUtterance);
    }

    setOrdersDeckState(state = 'docked') {
        this.ordersDeckState = state;
        if (!this.ordersDeck) this.ordersDeck = document.getElementById('scribe-orders-deck');
        if (!this.ordersDeck) return;

        this.ordersDeck.classList.remove('orders-docked', 'orders-expanded', 'orders-maximized');
        this.ordersDeck.classList.add(`orders-${state}`);

        const textFold = document.getElementById('text-fold-orders-deck');
        const iconFold = document.getElementById('icon-fold-orders-deck');
        const iconMax = document.getElementById('icon-max-orders-deck');

        if (this.overlay) {
            this.overlay.classList.toggle('cockpit-orders-focus', state === 'maximized');
        }

        if (state === 'docked') {
            if (textFold) textFold.textContent = 'Expand';
            if (iconFold) iconFold.className = 'mdi mdi-chevron-up';
            if (iconMax) iconMax.className = 'mdi mdi-arrow-expand';
            if (this.cockpitMode === 'orders') {
                this.setCockpitMode('note');
            }
        } else if (state === 'expanded') {
            if (textFold) textFold.textContent = 'Dock';
            if (iconFold) iconFold.className = 'mdi mdi-chevron-down';
            if (iconMax) iconMax.className = 'mdi mdi-arrow-expand';
            if (this.cockpitMode === 'orders') {
                this.setCockpitMode('note');
            }
        } else if (state === 'maximized') {
            if (textFold) textFold.textContent = 'Dock';
            if (iconFold) iconFold.className = 'mdi mdi-chevron-down';
            if (iconMax) iconMax.className = 'mdi mdi-arrow-collapse';
            document.querySelectorAll('.scribe-step-btn').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-step') === 'orders');
            });
        }

        this.updateOrdersDeckSummaryTicker();
        this.scheduleDebouncedDraftSave();
    }

    toggleOrdersDeck() {
        if (this.ordersDeckState === 'docked') {
            this.setOrdersDeckState('expanded');
        } else {
            this.setOrdersDeckState('docked');
        }
    }

    toggleMaximizeOrdersDeck() {
        if (this.ordersDeckState === 'maximized') {
            this.setOrdersDeckState('expanded');
        } else {
            this.setOrdersDeckState('maximized');
        }
    }

    updateOrdersDeckSummaryTicker() {
        const count = this.groundedOrders ? this.groundedOrders.length : 0;
        const freeFormCount = this.groundedOrders ? this.groundedOrders.filter(o => o.is_free_form || !o.catalog_id).length : 0;

        if (this.ordersCountBadge) {
            this.ordersCountBadge.textContent = `${count} RECOMMENDED`;
        }
        if (this.stepOrdersCount) {
            this.stepOrdersCount.textContent = `${count}`;
        }
        if (this.ordersFreeformCount) {
            if (freeFormCount > 0) {
                this.ordersFreeformCount.style.display = 'inline-block';
                this.ordersFreeformCount.textContent = `${freeFormCount} Free-form`;
            } else {
                this.ordersFreeformCount.style.display = 'none';
            }
        }
        if (this.ordersPreviewTicker) {
            if (count === 0) {
                this.ordersPreviewTicker.textContent = 'Docked · No orders';
            } else {
                const itemNames = this.groundedOrders.slice(0, 3).map(o => o.item_name).join(', ');
                const moreSuffix = count > 3 ? ` +${count - 3} more` : '';
                const stateNotice = this.ordersDeckState === 'docked' ? 'Click to expand' : (this.ordersDeckState === 'maximized' ? 'Orders focused' : 'Expanded');
                this.ordersPreviewTicker.textContent = `${itemNames}${moreSuffix} · ${stateNotice}`;
            }
        }
    }

    updateSignButtonBadges() {
        const count = this.groundedOrders ? this.groundedOrders.filter(o => o.is_selected && !o.is_dispatched).length : 0;
        document.querySelectorAll('.scribe-sign-orders-badge').forEach(badge => {
            if (count > 0) {
                badge.textContent = `+${count} order${count === 1 ? '' : 's'}`;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        });
    }

    openCatalogMatcher(orderIndex) {
        this.activeMatchingOrderIndex = orderIndex;
        const ord = this.groundedOrders[orderIndex];
        if (!ord) return;

        if (!this.matcherModal) {
            this.matcherModal = document.getElementById('scribe-catalog-matcher-modal');
        }
        if (!this.matcherModal) return;

        const sub = document.getElementById('scribe-matcher-subtitle');
        if (sub) {
            sub.textContent = `Linking: "${ord.item_name}" (${ord.category_name || ord.order_type})`;
        }

        let initFilter = 'all';
        if (ord.order_type === 'PRODUCT') {
            initFilter = 'product';
        } else if (ord.service_type === 'lab') {
            initFilter = 'lab';
        } else if (ord.service_type === 'imaging') {
            initFilter = 'imaging';
        } else if (ord.service_type === 'procedure') {
            initFilter = 'procedure';
        }
        this.activeMatcherFilter = initFilter;

        document.querySelectorAll('.matcher-filter-chip').forEach(chip => {
            chip.classList.toggle('active', chip.getAttribute('data-filter') === initFilter);
        });

        if (this.matcherSearchInput) {
            this.matcherSearchInput.value = ord.item_name;
        }

        this.matcherModal.style.display = 'flex';
        this.executeCatalogSearch(ord.item_name, initFilter);

        setTimeout(() => {
            if (this.matcherSearchInput) {
                this.matcherSearchInput.focus();
                this.matcherSearchInput.select();
            }
        }, 100);
    }

    closeCatalogMatcher() {
        if (this.matcherModal) {
            this.matcherModal.style.display = 'none';
        }
        this.activeMatchingOrderIndex = null;
        this._currentSearchResults = [];
    }

    async executeCatalogSearch(query = '', filter = 'all') {
        if (!this.matcherResultsContainer) {
            this.matcherResultsContainer = document.getElementById('scribe-matcher-results');
        }
        if (!this.matcherResultsContainer) return;

        this.matcherResultsContainer.innerHTML = '<div class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin me-2"></i> Searching hospital catalog...</div>';

        const pId = this.getPatientId();
        const cleanQuery = (query || '').trim();

        try {
            const promises = [];

            // Services search (lab, imaging, procedure, or all)
            if (filter === 'all' || ['lab', 'imaging', 'procedure'].includes(filter)) {
                const contextParam = filter === 'all' ? 'all' : filter;
                const url = `/live-search-services?term=${encodeURIComponent(cleanQuery)}&context=${contextParam}${pId ? `&patient_id=${pId}` : ''}`;
                promises.push(
                    fetch(url)
                        .then(r => r.json())
                        .then(items => (Array.isArray(items) ? items : []).map(item => ({ ...item, _type: 'service' })))
                        .catch(err => {
                            console.warn('live-search-services error:', err);
                            return [];
                        })
                );
            }

            // Products search (medications / pharmacy)
            if (filter === 'all' || filter === 'product') {
                const url = `/live-search-products?term=${encodeURIComponent(cleanQuery)}&type=drug${pId ? `&patient_id=${pId}` : ''}`;
                promises.push(
                    fetch(url)
                        .then(r => r.json())
                        .then(items => (Array.isArray(items) ? items : []).map(item => ({ ...item, _type: 'product' })))
                        .catch(err => {
                            console.warn('live-search-products error:', err);
                            return [];
                        })
                );
            }

            const resultsArrays = await Promise.all(promises);
            const allResults = resultsArrays.flat();

            if (allResults.length === 0) {
                this.matcherResultsContainer.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="mdi mdi-alert-circle-outline fs-3 text-secondary opacity-50 d-block mb-1"></i>
                        No catalog items found matching "<strong>${this.escapeHtml(cleanQuery)}</strong>".
                        <div class="small mt-1">Try another keyword, or keep as a verified free-form order.</div>
                    </div>
                `;
                return;
            }

            let html = '';
            allResults.forEach((item, idx) => {
                const isProd = (item._type === 'product');
                const name = isProd ? item.product_name : item.service_name;
                const code = isProd ? (item.product_code || '') : (item.service_code || '');
                const catName = isProd ? 'Pharmacy / Medication' : (item.category || (item.category_id ? `Category #${item.category_id}` : 'General Service'));
                const priceVal = isProd ? (item.payable_amount ?? item.price?.initial_sale_price ?? 0) : (item.payable_amount ?? item.base_price ?? 0);
                const priceFormatted = Number(priceVal).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                let badgeClass = 'cat-service';
                let badgeLabel = 'SERVICE';
                if (isProd) {
                    badgeClass = 'cat-product';
                    badgeLabel = 'MEDICATION';
                } else if (filter === 'lab' || (catName && catName.toLowerCase().includes('lab'))) {
                    badgeClass = 'cat-lab';
                    badgeLabel = 'LABORATORY';
                } else if (filter === 'imaging' || (catName && (catName.toLowerCase().includes('imag') || catName.toLowerCase().includes('radio') || catName.toLowerCase().includes('x-ray')))) {
                    badgeClass = 'cat-imaging';
                    badgeLabel = 'IMAGING';
                } else if (filter === 'procedure' || (catName && (catName.toLowerCase().includes('proc') || catName.toLowerCase().includes('surg')))) {
                    badgeClass = 'cat-procedure';
                    badgeLabel = 'PROCEDURE';
                }

                html += `
                    <div class="matcher-result-item" data-res-idx="${idx}">
                        <div class="matcher-result-meta">
                            <div class="matcher-result-name">
                                <span>${this.escapeHtml(name)}</span>
                                <span class="scribe-order-cat-badge ${badgeClass}" style="font-size: 0.65rem;">${badgeLabel}</span>
                            </div>
                            <div class="matcher-result-sub">
                                <span>${this.escapeHtml(catName)}</span>
                                ${code ? `<span>· Code: ${this.escapeHtml(code)}</span>` : ''}
                                <span class="fw-bold text-success">· ₦${priceFormatted}</span>
                            </div>
                        </div>
                        <div>
                            <button type="button" class="btn-select-catalog-item" data-res-idx="${idx}">
                                <i class="mdi mdi-link-variant"></i> Link & Ground
                            </button>
                        </div>
                    </div>
                `;
            });

            this.matcherResultsContainer.innerHTML = html;
            this._currentSearchResults = allResults;

            this.matcherResultsContainer.querySelectorAll('.btn-select-catalog-item').forEach(btn => {
                btn.addEventListener('click', () => {
                    const rIdx = parseInt(btn.getAttribute('data-res-idx'));
                    const selItem = this._currentSearchResults[rIdx];
                    if (selItem) {
                        this.applyCatalogMatch(selItem, selItem._type);
                    }
                });
            });
        } catch (err) {
            console.error('Catalog search failure:', err);
            this.matcherResultsContainer.innerHTML = `<div class="text-center py-4 text-danger"><i class="mdi mdi-alert"></i> Error searching catalog: ${this.escapeHtml(err.message)}</div>`;
        }
    }

    applyCatalogMatch(selectedItem, itemType) {
        if (this.activeMatchingOrderIndex === null || !this.groundedOrders[this.activeMatchingOrderIndex]) {
            this.closeCatalogMatcher();
            return;
        }

        const ord = this.groundedOrders[this.activeMatchingOrderIndex];
        const isProd = (itemType === 'product');

        ord.catalog_id = selectedItem.id;
        ord.item_name = isProd ? selectedItem.product_name : selectedItem.service_name;
        ord.is_free_form = false;
        ord.unit_price = isProd
            ? (selectedItem.payable_amount ?? selectedItem.price?.initial_sale_price ?? 0)
            : (selectedItem.payable_amount ?? selectedItem.base_price ?? 0);

        if (isProd) {
            ord.order_type = 'PRODUCT';
            ord.target_queue = 'dispensing_queue';
            ord.category_name = 'Prescription';
        } else {
            ord.order_type = 'SERVICE';
            const cat = (selectedItem.category || '').toLowerCase();
            if (cat.includes('lab') || cat.includes('investig')) {
                ord.service_type = 'lab';
                ord.target_queue = 'lab_queue';
                ord.category_name = 'Laboratory Investigation';
            } else if (cat.includes('imag') || cat.includes('radio') || cat.includes('x-ray') || cat.includes('scan')) {
                ord.service_type = 'imaging';
                ord.target_queue = 'imaging_queue';
                ord.category_name = 'Imaging / Radiology';
            } else if (cat.includes('proc') || cat.includes('surg')) {
                ord.service_type = 'procedure';
                ord.target_queue = 'procedure_queue';
                ord.category_name = 'Clinical Procedure';
            } else {
                ord.service_type = 'procedure';
                ord.target_queue = 'procedure_queue';
                ord.category_name = selectedItem.category || 'Hospital Service';
            }
        }

        this.closeCatalogMatcher();
        this.renderOrdersDeck();
        this.scheduleDebouncedDraftSave();

        if (typeof toastr !== 'undefined') {
            toastr.success(`Linked to catalog item: ${ord.item_name} (#${ord.catalog_id})`);
        }
    }

    clearPersistedDraft() {
        const key = this.getDraftStorageKey();
        sessionStorage.removeItem(key);
        localStorage.removeItem(key);
    }
}

// Global trigger bindings & keyboard shortcuts
document.addEventListener('DOMContentLoaded', () => {
    // 1. Studio trigger buttons
    document.addEventListener('click', (e) => {
        const studioBtn = e.target.closest('.btn-speech-studio, #btn-open-scribe-studio, .fab-scribe-btn');
        if (studioBtn) {
            e.preventDefault();
            const targetSelector = studioBtn.getAttribute('data-target-editor');
            if (targetSelector && ClinicalAiScribeStudio.instance) {
                ClinicalAiScribeStudio.instance.targetEditorSelector = targetSelector;
            }
            if (ClinicalAiScribeStudio.instance) {
                ClinicalAiScribeStudio.instance.open();
            }
        }

        // 2. Quick Polish button
        const polishBtn = e.target.closest('.btn-speech-format');
        if (polishBtn) {
            e.preventDefault();
            quickPolishNote(polishBtn);
        }
    });

    // 3. Global hotkeys: F9 or Alt+S toggles Studio, Escape closes it
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9' || (e.altKey && (e.key === 's' || e.key === 'S'))) {
            e.preventDefault();
            if (ClinicalAiScribeStudio.instance) {
                const overlay = ClinicalAiScribeStudio.instance.overlay;
                if (overlay && overlay.classList.contains('active')) {
                    ClinicalAiScribeStudio.instance.close();
                } else {
                    ClinicalAiScribeStudio.instance.open();
                }
            }
        } else if (e.key === 'Escape') {
            if (ClinicalAiScribeStudio.instance) {
                const overlay = ClinicalAiScribeStudio.instance.overlay;
                if (overlay && overlay.classList.contains('active')) {
                    ClinicalAiScribeStudio.instance.close();
                }
            }
        }
    });
});

async function quickPolishNote(btn) {
    if (!ClinicalAiScribeStudio.instance) return;
    const studio = ClinicalAiScribeStudio.instance;
    const currentText = studio.getEditorContent();
    const cleanText = (currentText || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();

    if (!cleanText) {
        if (typeof toastr !== 'undefined') {
            toastr.warning('Please enter or dictate a note to polish.');
        }
        return;
    }

    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Polishing...';
    btn.disabled = true;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch('/llm/polish-note', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                raw_note: currentText,
                modality: studio.role || 'doctor'
            })
        });

        const data = await response.json();
        if (data && data.polished_note) {
            studio.setEditorContent(data.polished_note);
            if (typeof toastr !== 'undefined') {
                toastr.success('Note polished in-place.');
            }
        } else {
            throw new Error(data.message || 'Failed to polish note.');
        }
    } catch (err) {
        console.error('Quick polish error:', err);
        if (typeof toastr !== 'undefined') {
            toastr.error(err.message, 'Polish Error');
        }
    } finally {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }
}

window.ClinicalAiScribeStudio = ClinicalAiScribeStudio;

