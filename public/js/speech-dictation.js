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
            // Dynamic ESM imports from local public public/assets/js folder
            // Fully offline-compatible!
            const { unified } = await import('/assets/js/unified.min.js');
            const { default: retextEnglish } = await import('/assets/js/retext-english.min.js');
            const { default: retextStringify } = await import('/assets/js/retext-stringify.min.js');
            const { default: retextRepeatedWords } = await import('/assets/js/retext-repeated-words.min.js');
            const { default: retextIndefiniteArticle } = await import('/assets/js/retext-indefinite-article.min.js');
            const { visit } = await import('/assets/js/unist-util-visit.min.js');

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
                    visit(tree, 'WordNode', (node) => {
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
        this.previewText = document.querySelector(options.previewTextSelector);
        this.langSelect = document.querySelector(options.langSelectSelector);
        this.formatButton = document.querySelector(options.formatButtonSelector);
        
        this.editorType = options.editorType || 'textarea'; // 'textarea' or 'ckeditor'
        this.defaultLang = options.defaultLang || 'en-US';
        this.onResultCallback = options.onResultCallback || null;
        
        this.recognition = null;
        this.isListening = false;
        this.ignoreNextStart = false;
        this.scribe = new RetextScribe();
        
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
                if (this.previewBubble) this.previewBubble.classList.remove('d-none');
            }
            
            // Insert finalized text
            if (finalTranscript) {
                // Pass raw speech output through Retext NLP Engine
                const cleanedText = await this.scribe.process(finalTranscript);
                const formattedText = this.formatSpeech(cleanedText);
                
                if (formattedText) {
                    this.insertText(formattedText);
                    if (this.onResultCallback) {
                        this.onResultCallback(formattedText);
                    }
                }
                
                // Clear active preview
                if (this.previewText) {
                    this.previewText.textContent = 'Listening...';
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
        if (window.editor && typeof window.editor.setData === 'function') {
            return window.editor;
        } else if (editorElement && editorElement.ckeditorInstance) {
            return editorElement.ckeditorInstance;
        } else if (typeof window.classicEditors !== 'undefined') {
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
                
                // Clean repeated words/articles via Retext NLP Scribe
                polished = await this.scribe.process(polished);
                
                // Clean general spacing and punctuation issues
                polished = this.cleanSpacingAndCasing(polished);
                
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
                    toastr.success('✨ Clinical Note formatted & NLP-polished successfully!');
                }
            } else {
                if (window.toastr) {
                    toastr.info('Clinical note is empty. Add some text first!');
                }
            }
        } catch (e) {
            console.error('Retext offline formatting error:', e);
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
                
                try {
                    const viewFragment = editor.data.processor.toView(' ' + text + ' ');
                    const modelFragment = editor.data.toModel(viewFragment);
                    editor.model.insertContent(modelFragment, editor.model.document.selection);
                } catch (e) {
                    const currentData = editor.getData();
                    editor.setData(currentData + ' ' + text);
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
