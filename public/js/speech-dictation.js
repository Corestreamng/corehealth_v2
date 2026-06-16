/**
 * SpeechDictationKit - Premium, Reusable Web Speech API Integration
 * Handles Speech-to-Text conversion for textareas and CKEditor instances.
 * Performs real-time & manual NLP clinical polishing using offline Retext JS.
 */

class RetextScribe {
    constructor() {
        this.processor = null;
        this.initialized = false;
    }

    async init() {
        if (this.initialized) return;

        try {
            // Load all NLP deps from a single monolithic offline bundle
            // Built with esbuild from local node_modules — no CDN dependency
            const base = window.SPEECH_ASSET_BASE || '/assets/js';
            const {
                unified,
                retextEnglish,
                retextStringify,
                retextRepeatedWords,
                retextIndefiniteArticle,
                visit
            } = await import(`${base}/retext-bundle.min.js`);

            // Custom clinical abbreviation expander plugin
            const clinicalAbbrPlugin = () => {
                const abbreviations = {
                    'htn': 'hypertension',
                    'bp': 'blood pressure',
                    'prn': 'as-needed',
                    'bid': 'twice daily',
                    'tid': 'three times daily',
                    'qid': 'four times daily',
                    'hx': 'history',
                    'dx': 'diagnosis',
                    'rx': 'prescription',
                    'sob': 'shortness of breath'
                };

                return (tree) => {
                    // nlcst: WordNode is a parent container; TextNode carries the actual string value
                    visit(tree, 'TextNode', (node) => {
                        if (!node.value) return;
                        const word = node.value.toLowerCase();
                        if (abbreviations[word]) {
                            node.value = abbreviations[word];
                        }
                    });
                };
            };

            this.processor = unified()
                .use(retextEnglish)
                .use(retextRepeatedWords)
                .use(retextIndefiniteArticle)
                .use(clinicalAbbrPlugin)
                .use(retextStringify);

            this.initialized = true;
        } catch (e) {
            console.error('Failed to initialize RetextScribe offline:', e);
        }
    }

    async process(text) {
        await this.init();
        if (!this.processor) return text;
        const file = await this.processor.process(text);
        return String(file);
    }
}

class SpeechDictationKit {
    constructor(options = {}) {
        this.button = document.querySelector(options.buttonSelector);
        this.target = document.querySelector(options.targetSelector);
        this.previewBubble = document.querySelector(options.previewBubbleSelector);
        this.overlayStopBtn = document.querySelector(options.overlayStopBtnSelector);
        this.previewText = document.querySelector(options.previewTextSelector);
        this.historyText = document.querySelector(options.historyTextSelector);
        this.langSelect = document.querySelector(options.langSelectSelector);
        this.formatButton = document.querySelector(options.formatButtonSelector);

        this.editorType = options.editorType || 'textarea'; // 'textarea' or 'ckeditor'
        this.defaultLang = options.defaultLang || 'en-US';
        this.onResultCallback = options.onResultCallback || null;

        this.recognition = null;
        this.isListening = false;
        this.ignoreNextStart = false;
        this.isFirstInsertOfSession = true; // reset each time start() is called
        this.scribe = new RetextScribe();

        // Voice visualization properties
        this.audioContext = null;
        this.audioAnalyser = null;
        this.audioStream = null;
        this.audioAnimId = null;

        if (!this.button || !this.target) {
            console.error('SpeechDictationKit: Button or target element not found.');
            return;
        }

        this.init();
    }

    init() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (!SpeechRecognition) {
            this.markUnsupported();
            return;
        }

        // Setup Web Speech API
        this.recognition = new SpeechRecognition();
        this.recognition.continuous = true;
        this.recognition.interimResults = true;
        this.recognition.lang = this.getLanguage();

        this.bindEvents();
    }

    getLanguage() {
        if (this.langSelect) {
            return this.langSelect.value;
        }
        return this.defaultLang;
    }

    markUnsupported() {
        this.button.disabled = true;
        this.button.classList.add('btn-dictate-unsupported');
        this.button.innerHTML = '<i class="fa fa-microphone-slash"></i> Unsupported';
        this.button.title = 'Speech recognition is not supported in this browser (Use Chrome or Safari).';
    }

    bindEvents() {
        // Toggle Dictation on Click
        this.button.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleDictation();
        });

        // Overlay Stop Button Click
        if (this.overlayStopBtn) {
            this.overlayStopBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.stop();
            });
        }

        // Manual Polish Note Click
        if (this.formatButton) {
            this.formatButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.manualFormat();
            });
        }

        // Handle Language Change
        if (this.langSelect) {
            this.langSelect.addEventListener('change', () => {
                if (this.recognition) {
                    this.recognition.lang = this.getLanguage();
                    if (this.isListening) {
                        this.restartRecognition();
                    }
                }
            });
        }

        // Speech Recognition Lifecycle
        this.recognition.onstart = () => {
            this.isListening = true;
            this.updateUI('listening');
        };

        this.recognition.onend = () => {
            if (this.ignoreNextStart) {
                this.ignoreNextStart = false;
                this.recognition.start();
                return;
            }
            this.isListening = false;
            this.updateUI('idle');
        };

        this.recognition.onerror = (event) => {
            console.error('Speech recognition error:', event.error);
            this.handleError(event.error);
        };

        this.recognition.onresult = async (event) => {
            let interimTranscript = '';
            let finalTranscript = '';

            for (let i = event.resultIndex; i < event.results.length; ++i) {
                const transcriptPiece = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalTranscript += transcriptPiece;
                } else {
                    interimTranscript += transcriptPiece;
                }
            }

            // Render Live Preview
            if (interimTranscript && this.previewText) {
                this.previewText.innerHTML = `<em>${interimTranscript}</em>`;
                this.previewText.scrollLeft = this.previewText.scrollWidth;
                if (this.previewBubble) this.previewBubble.classList.remove('d-none');
            }

            // Insert finalized text
            if (finalTranscript) {
                // Pass raw speech output through Retext NLP Engine
                const cleanedText = await this.scribe.process(finalTranscript);
                const formattedText = this.formatSpeech(cleanedText);

                if (formattedText) {
                    if (this.historyText) {
                        this.historyText.classList.remove('d-none');
                        const span = document.createElement('span');
                        span.textContent = formattedText + ' ';
                        this.historyText.appendChild(span);
                        this.historyText.scrollTop = this.historyText.scrollHeight;
                    }
                    
                    this.insertText(formattedText);
                    if (this.onResultCallback) {
                        this.onResultCallback(formattedText);
                    }
                }

                // Clear active preview
                if (this.previewText) {
                    this.previewText.textContent = 'Listening...';
                    this.previewText.scrollLeft = 0;
                }
            }
        };
    }

    toggleDictation() {
        if (this.isListening) {
            this.stop();
        } else {
            this.start();
        }
    }

    start() {
        if (this.recognition && !this.isListening) {
            this.recognition.lang = this.getLanguage();
            this.isFirstInsertOfSession = true; // new session — next insert goes to new paragraph
            
            // Clear history for new dictation session
            if (this.historyText) {
                this.historyText.innerHTML = '';
                this.historyText.classList.add('d-none');
            }

            this.updateUI('connecting');
            try {
                this.recognition.start();
            } catch (e) {
                console.error('Failed to start speech recognition:', e);
            }
        }
    }

    stop() {
        if (this.recognition && this.isListening) {
            this.recognition.stop();
        }
    }

    restartRecognition() {
        this.ignoreNextStart = true;
        this.recognition.stop();
    }

    updateUI(state) {
        if (state === 'listening') {
            this.button.className = 'btn btn-speech-kit btn-speech-listening d-flex align-items-center gap-2';
            this.button.innerHTML = '<span class="speech-mic-icon-wrapper"><i class="fa fa-microphone"></i></span> Stop Dictation';

            if (this.previewBubble) {
                this.previewBubble.classList.remove('d-none');
            }
            if (this.previewText) {
                this.previewText.textContent = 'Listening...';
            }
            
            // Activate voice volume responsiveness
            this.startVoiceVisualizer();
        } else if (state === 'connecting') {
            this.button.className = 'btn btn-speech-kit btn-speech-connecting d-flex align-items-center gap-2';
            this.button.innerHTML = '<span class="speech-mic-icon-wrapper"><i class="fa fa-spinner fa-spin"></i></span> Connecting...';
        } else {
            // Idle State
            this.button.className = 'btn btn-speech-kit btn-speech-idle d-flex align-items-center gap-2';
            this.button.innerHTML = '<span class="speech-mic-icon-wrapper"><i class="fa fa-microphone"></i></span> Start Dictation';

            if (this.previewBubble) {
                this.previewBubble.classList.add('d-none');
            }
            
            // Shutdown visualizer and reset sizes
            this.stopVoiceVisualizer();
        }
    }

    async startVoiceVisualizer() {
        this.stopVoiceVisualizer(); // Safety cleanup first

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.audioStream = stream;

            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            this.audioContext = new AudioContextClass();
            this.audioAnalyser = this.audioContext.createAnalyser();
            this.audioAnalyser.fftSize = 64; // small buffer for volume monitoring

            const source = this.audioContext.createMediaStreamSource(stream);
            source.connect(this.audioAnalyser);

            const bufferLength = this.audioAnalyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);

            const waveBars = this.previewBubble ? this.previewBubble.querySelectorAll('.wave-bar') : [];

            const draw = () => {
                if (!this.audioContext || !this.isListening) return;
                this.audioAnimId = requestAnimationFrame(draw);

                this.audioAnalyser.getByteFrequencyData(dataArray);

                if (waveBars.length > 0) {
                    const step = Math.floor(bufferLength / waveBars.length);
                    waveBars.forEach((bar, index) => {
                        const val = dataArray[index * step] || 0;
                        // Map frequency amplitude (0-255) to height (15px - 140px)
                        const height = Math.max(15, (val / 255) * 130);
                        bar.style.height = `${height}px`;
                    });
                }
            };

            draw();
        } catch (e) {
            console.warn('Speech Voice Visualizer failed to start:', e);
        }
    }

    stopVoiceVisualizer() {
        if (this.audioAnimId) {
            cancelAnimationFrame(this.audioAnimId);
            this.audioAnimId = null;
        }

        if (this.audioContext) {
            try {
                if (this.audioContext.state !== 'closed') {
                    this.audioContext.close();
                }
            } catch (e) {
                console.error(e);
            }
            this.audioContext = null;
        }

        if (this.audioStream) {
            try {
                this.audioStream.getTracks().forEach(track => track.stop());
            } catch (e) {
                console.error(e);
            }
            this.audioStream = null;
        }
        
        // Reset element styles back to CSS defaults
        if (this.previewBubble) {
            const waveBars = this.previewBubble.querySelectorAll('.wave-bar');
            waveBars.forEach(bar => {
                bar.style.height = '';
            });
        }
    }

    handleError(error) {
        let msg = 'Speech recognition error occurred.';
        if (error === 'not-allowed') {
            msg = 'Microphone access denied. Please verify your browser settings.';
        } else if (error === 'no-speech') {
            msg = 'No voice detected. Please speak clearly into your microphone.';
        }

        if (this.previewText) {
            this.previewText.innerHTML = `<span class="text-danger"><i class="fa fa-exclamation-circle"></i> ${msg}</span>`;
            setTimeout(() => {
                if (!this.isListening && this.previewBubble) {
                    this.previewBubble.classList.add('d-none');
                }
            }, 5000);
        }

        this.updateUI('idle');
        this.isListening = false;
    }

    formatSpeech(text) {
        let result = text;

        // Punctuation command replacements
        const commands = [
            { pattern: /\b(period|full stop|fullstop)\b/gi, replacement: '.' },
            { pattern: /\b(comma)\b/gi, replacement: ',' },
            { pattern: /\b(question mark)\b/gi, replacement: '?' },
            { pattern: /\b(exclamation mark|exclamation point)\b/gi, replacement: '!' },
            { pattern: /\b(new line|next line|newline)\b/gi, replacement: '\n' },
            { pattern: /\b(new paragraph|next paragraph|paragraph)\b/gi, replacement: '\n\n' },
            { pattern: /\b(colon)\b/gi, replacement: ':' },
            { pattern: /\b(semicolon)\b/gi, replacement: ';' },
            { pattern: /\b(hyphen|dash)\b/gi, replacement: '-' },
            { pattern: /\b(open parenthesis|open bracket)\b/gi, replacement: ' (' },
            { pattern: /\b(close parenthesis|close bracket)\b/gi, replacement: ') ' }
        ];

        commands.forEach(cmd => {
            result = result.replace(cmd.pattern, cmd.replacement);
        });

        // Clean double spaces and punctuation spacing
        result = result.replace(/\s+/g, ' ');
        result = result.replace(/\s+([.,?!:;])/g, '$1');

        return result.trim();
    }

    getEditorInstance() {
        const editorElement = this.target;

        // 1. Check WorkbenchNotesKit (nursing / maternity shared editor registry)
        if (window.WorkbenchNotesKit && window.WorkbenchNotesKit.editors) {
            for (const prefix in window.WorkbenchNotesKit.editors) {
                const ed = window.WorkbenchNotesKit.editors[prefix];
                if (ed && ed.sourceElement && editorElement &&
                    (ed.sourceElement === editorElement || ed.sourceElement.id === editorElement.id)) {
                    return ed;
                }
            }
        }

        // 2. Global window.editor (doctor encounter page)
        if (window.editor && typeof window.editor.setData === 'function') {
            return window.editor;
        }

        // 3. CKEditor instance attached directly on the element
        if (editorElement && editorElement.ckeditorInstance) {
            return editorElement.ckeditorInstance;
        }

        // 4. Legacy classicEditors array
        if (typeof window.classicEditors !== 'undefined') {
            let instance = null;
            window.classicEditors.forEach(ed => {
                if (ed.sourceElement && ed.sourceElement.id === editorElement.id) instance = ed;
            });
            return instance;
        }

        return null;
    }

    async manualFormat() {
        if (!this.formatButton) return;

        const originalHTML = this.formatButton.innerHTML;
        this.formatButton.disabled = true;
        this.formatButton.innerHTML = '<span class="speech-format-icon-wrapper"><i class="fa fa-spinner fa-spin"></i></span> Polishing...';

        try {
            let text = '';
            if (this.editorType === 'ckeditor') {
                const editor = this.getEditorInstance();
                if (editor) text = editor.getData();
            } else {
                text = this.target.value;
            }

            if (text && text.trim() !== '') {
                // SOAP/Clinical Auto-paragraphing
                let polished = this.formatMedicalStructure(text);

                // Check if we should use LLM or fallback to local NLP
                let usedLlm = false;
                // Rely on globals set by the pages incorporating the dictation kit
                let encId = window.currentEncounterId || (window.patientSummary ? window.patientSummary.encounterId : null);
                let patId = window.currentPatientId || (window.patientSummary ? window.patientSummary.patientId : null);

                if (patId && encId) {
                    try {
                        const response = await fetch('/llm/polish-note', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                note_content: polished,
                                patient_id: patId,
                                encounter_id: encId
                            })
                        });
                        const data = await response.json();
                        if (data.success && data.polished_content) {
                            if (this.editorType === 'ckeditor') {
                                // Basic markdown to HTML (since LLM might return Markdown)
                                polished = data.polished_content
                                    .replace(/^### (.*$)/gim, '<h3>$1</h3>')
                                    .replace(/^## (.*$)/gim, '<h2>$1</h2>')
                                    .replace(/^# (.*$)/gim, '<h1>$1</h1>')
                                    .replace(/\*\*(.*)\*\*/gim, '<strong>$1</strong>')
                                    .replace(/\*(.*)\*/gim, '<em>$1</em>')
                                    .replace(/^- (.*$)/gim, '<ul><li>$1</li></ul>')
                                    .replace(/<\/ul>\n<ul>/gim, '')
                                    .replace(/\n/gim, '<br>');
                            } else {
                                polished = data.polished_content;
                            }
                            usedLlm = true;
                        } else {
                            throw new Error(data.message || 'LLM polishing failed');
                        }
                    } catch (llmError) {
                        console.warn('LLM Polish Note failed, falling back to local NLP:', llmError);
                        polished = await this.scribe.process(polished);
                    }
                } else {
                    // Offline / Local processing fallback
                    polished = await this.scribe.process(polished);
                }

                // Clean general spacing and punctuation issues ONLY if using local NLP
                if (!usedLlm) {
                    polished = this.cleanSpacingAndCasing(polished);
                }

                if (this.editorType === 'ckeditor') {
                    const editor = this.getEditorInstance();
                    if (editor) editor.setData(polished);
                } else {
                    this.target.value = polished;
                }

                // Dispatch input events for autosave
                this.target.dispatchEvent(new Event('change', { bubbles: true }));
                this.target.dispatchEvent(new Event('input', { bubbles: true }));

                if (typeof window.autosavenotes === 'function') {
                    window.autosavenotes();
                }

                if (window.toastr) {
                    if (usedLlm) {
                        toastr.success('✨ Clinical Note AI-polished successfully!');
                    } else {
                        toastr.success('✨ Clinical Note formatted & NLP-polished successfully!');
                    }
                }
            } else {
                if (window.toastr) {
                    toastr.info('Clinical note is empty. Add some text first!');
                }
            }
        } catch (e) {
            console.error('Formatting error:', e);
            if (window.toastr) toastr.error('Failed to auto-format clinical notes.');
        } finally {
            this.formatButton.disabled = false;
            this.formatButton.innerHTML = originalHTML;
        }
    }

    formatMedicalStructure(text) {
        const sections = [
            'Subjective', 'Objective', 'Assessment', 'Plan',
            'History', 'Vitals', 'Examination', 'Diagnosis', 'Rx',
            'Chief Complaint', 'Present Illness', 'Past History',
            'Treatment Plan', 'Clinical Notes'
        ];

        if (this.editorType === 'ckeditor') {
            // Process as HTML content for CKEditor 5
            sections.forEach(sec => {
                const regex = new RegExp(`\\b(${sec})\\s*[:\\-]\\s*`, 'gi');
                text = text.replace(regex, `<br><br><strong>$1:</strong> `);
            });
            text = text.replace(/(<br\s*\/?>\s*){3,}/gi, '<br><br>');
            text = text.replace(/^(\s*<br\s*\/?>\s*)+/gi, '');
        } else {
            // Process as plain text for textareas
            sections.forEach(sec => {
                const regex = new RegExp(`\\b(${sec})\\s*[:\\-]\\s*`, 'gi');
                text = text.replace(regex, `\n\n$1: `);
            });
            text = text.replace(/\n{3,}/g, '\n\n');
        }

        return text;
    }

    cleanSpacingAndCasing(text) {
        // Clean duplicate spaces
        text = text.replace(/[ \t]+/g, ' ');

        // Clean spaces before punctuation
        text = text.replace(/\s+([.,?!:;])/g, '$1');

        // Ensure clean space after punctuation (avoiding decimal points like 37.5)
        text = text.replace(/([.,?!:;])([A-Za-z])/g, '$1 $2');

        if (this.editorType !== 'ckeditor') {
            // Capitalize plain text sentence beginnings
            text = text.replace(/(^\s*|[.!?]\s+)([a-z])/g, (match, separator, letter) => {
                return separator + letter.toUpperCase();
            });
        }

        return text;
    }

    insertText(text) {
        if (this.editorType === 'ckeditor') {
            const editor = this.getEditorInstance();

            if (editor) {
                if (text === '\n' || text === '\n\n') {
                    editor.execute('enter');
                    return;
                }

                // Each finalized result starts in its own paragraph if editor already has content
                if (editor.getData().trim()) {
                    editor.execute('enter');
                }

                try {
                    const viewFragment = editor.data.processor.toView(text);
                    const modelFragment = editor.data.toModel(viewFragment);
                    editor.model.insertContent(modelFragment, editor.model.document.selection);
                } catch (e) {
                    const currentData = editor.getData();
                    editor.setData(currentData + '<p>' + text + '</p>');
                }
            } else {
                this.insertAtCursor(this.target, ' ' + text);
            }
        } else {
            this.insertAtCursor(this.target, ' ' + text);
        }
    }

    insertAtCursor(el, text) {
        if (el.selectionStart || el.selectionStart === 0) {
            const startPos = el.selectionStart;
            const endPos = el.selectionEnd;
            el.value = el.value.substring(0, startPos) + text + el.value.substring(endPos, el.value.length);
            el.selectionStart = startPos + text.length;
            el.selectionEnd = startPos + text.length;
            el.focus();
        } else {
            el.value += text;
            el.focus();
        }

        const event = new Event('change', { bubbles: true });
        el.dispatchEvent(event);
    }
}
