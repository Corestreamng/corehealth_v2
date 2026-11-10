/**
 * Patient Summary Controller
 * Handles the AI-generated patient briefing overlay and Web Speech API narration.
 */

class PatientSummaryManager {
    constructor(config = {}) {
        if (PatientSummaryManager.instance) {
            PatientSummaryManager.instance.updateConfig(config);
            return PatientSummaryManager.instance;
        }
        PatientSummaryManager.instance = this;

        this.patientId = config.patientId;
        this.encounterId = config.encounterId;
        this.autoOpen = false; // Never auto-pop full drawer, proactively prefetch in background instead
        this.voiceEnabled = config.voiceEnabled !== undefined ? config.voiceEnabled : true;
        this.defaultRate = config.voiceRate || 1.0;
        
        // DOM Elements
        this.overlay = document.getElementById('patient-summary-overlay');
        this.backdrop = document.getElementById('patient-summary-backdrop');
        this.loadingState = document.getElementById('summary-loading');
        this.errorState = document.getElementById('summary-error');
        this.errorText = document.getElementById('summary-error-text');
        this.contentArea = document.getElementById('summary-content');
        this.textContent = document.getElementById('summary-text-container');
        this.capsuleBar = document.getElementById('summary-capsule-bar');
        this.metaInfo = document.getElementById('summary-meta-info');
        this.btnOpenStudio = document.getElementById('btn-open-studio-from-brief');
        
        // Audio Elements
        this.btnPlay = document.getElementById('btn-summary-play');
        this.btnStop = document.getElementById('btn-summary-stop');
        this.rateSelect = document.getElementById('summary-voice-rate');
        this.statusText = document.getElementById('summary-voice-status-text');
        this.statusIcon = document.getElementById('summary-voice-status-icon');
        
        // Other Elements
        this.btnRegenerate = document.getElementById('btn-regenerate-summary');

        // State
        this.synth = window.speechSynthesis;
        this.utterance = null;
        this.isPlaying = false;
        this.summaryText = '';
        this.hasLoaded = false;
        this.isForceRegenerate = false;

        this.init();
    }

    updateConfig(config) {
        this.patientId = config.patientId;
        this.encounterId = config.encounterId;
        this.autoOpen = false;
        this.voiceEnabled = config.voiceEnabled !== undefined ? config.voiceEnabled : true;
        if (config.voiceRate) this.defaultRate = config.voiceRate;
        
        this.hasLoaded = false;
        this.summaryText = '';
        if (this.textContent) this.textContent.innerHTML = '';
        if (this.metaInfo) this.metaInfo.innerHTML = '';

        if (this.patientId) {
            this.prefetchSummary();
        }
    }

    init() {
        if (!this.overlay) return;

        // Bind UI Events
        document.querySelectorAll('.btn-close-summary').forEach(btn => {
            btn.addEventListener('click', () => this.close());
        });

        if (this.backdrop) {
            this.backdrop.addEventListener('click', () => this.close());
        }

        if (this.btnOpenStudio) {
            this.btnOpenStudio.addEventListener('click', () => {
                this.close();
                if (window.ClinicalAiScribeStudio && window.ClinicalAiScribeStudio.instance) {
                    window.ClinicalAiScribeStudio.instance.open();
                } else if (window.aiScribeStudio) {
                    window.aiScribeStudio.open();
                }
            });
        }

        const copyBtn = document.querySelector('.btn-copy-summary');
        if (copyBtn) {
            copyBtn.addEventListener('click', () => this.copyToClipboard());
        }

        if (this.btnRegenerate) {
            this.btnRegenerate.addEventListener('click', () => this.regenerate());
        }

        // Delegated trigger for all AI Brief / Patient Summary buttons across headers & toolbars
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-patient-summary, #btn-ai-summary, #sch-expanded-ai-brief, #sch-compact-ai-brief');
            if (btn) {
                e.preventDefault();
                const pId = btn.getAttribute('data-patient-id');
                if (pId && pId != this.patientId) {
                    this.updateContext(pId, this.encounterId);
                }
                this.openAndLoad();
            }
        });

        // Keyboard navigation (Escape closes drawer)
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.overlay && !this.overlay.classList.contains('d-none')) {
                this.close();
            }
        });

        // Bind Audio Events
        if (this.voiceEnabled && this.synth) {
            if (this.btnPlay) {
                this.btnPlay.addEventListener('click', () => this.togglePlay());
            }
            if (this.btnStop) {
                this.btnStop.addEventListener('click', () => this.stopAudio());
            }
            if (this.rateSelect) {
                this.rateSelect.value = this.defaultRate;
                this.rateSelect.addEventListener('change', (e) => {
                    if (this.utterance) {
                        this.utterance.rate = parseFloat(e.target.value);
                        if (this.isPlaying) {
                            this.stopAudio();
                            this.playAudio();
                        }
                    }
                });
            }
        }

        // Proactively prefetch patient brief in background on page load
        if (this.patientId) {
            setTimeout(() => this.prefetchSummary(), 500);
        }
    }

    prefetchSummary() {
        if (this.hasLoaded || this.isFetching) return;

        // Check session storage first
        const sessionKey = `patient_brief_${this.patientId}`;
        const cached = sessionStorage.getItem(sessionKey);
        if (cached) {
            this.summaryText = cached;
            window.cachedPatientSummary = cached;
            this.renderSummary(cached);
            this.hasLoaded = true;
            window.dispatchEvent(new CustomEvent('patient-brief-loaded', {
                detail: { summary: cached, patientId: this.patientId }
            }));
            return;
        }

        // Otherwise fetch in background
        this.fetchSummary({ background: true });
    }

    openAndLoad() {
        if (!this.overlay) return;
        
        // Prevent background body scroll while drawer is open
        document.body.style.overflow = 'hidden';
        if (this.backdrop) this.backdrop.classList.remove('d-none');
        this.overlay.classList.remove('d-none');
        
        if (!this.hasLoaded) {
            this.fetchSummary({ background: false });
        } else {
            if (this.loadingState) this.loadingState.style.display = 'none';
            if (this.contentArea) this.contentArea.style.display = 'block';
            if (this.capsuleBar) this.capsuleBar.style.display = 'flex';
        }
    }

    updateContext(patientId, encounterId) {
        if (this.patientId !== patientId || this.encounterId !== encounterId) {
            this.patientId = patientId;
            this.encounterId = encounterId;
            this.hasLoaded = false;
            this.summaryText = '';
            if (this.textContent) this.textContent.innerHTML = '';
            this.prefetchSummary();
        }
    }

    close() {
        if (this.overlay) this.overlay.classList.add('d-none');
        if (this.backdrop) this.backdrop.classList.add('d-none');
        document.body.style.overflow = '';
        this.stopAudio();
    }

    regenerate() {
        this.isForceRegenerate = true;
        this.stopAudio();
        this.hasLoaded = false;
        this.summaryText = '';
        if (this.textContent) this.textContent.innerHTML = '';
        this.fetchSummary({ background: false });
    }

    renderSummary(text, modelUsed = null) {
        if (!this.textContent) return;
        let formattedHtml = this.formatMarkdown(text);
        this.textContent.innerHTML = formattedHtml;
        if (modelUsed && this.metaInfo) {
            this.metaInfo.innerHTML = `<i class="mdi mdi-robot-outline"></i> Synthesized by ${modelUsed}`;
        }
        if (this.loadingState) this.loadingState.style.display = 'none';
        if (this.contentArea) this.contentArea.style.display = 'block';
        if (this.capsuleBar) this.capsuleBar.style.display = 'flex';
        this.setupAudio();
    }

    async fetchSummary(options = { background: false }) {
        if (this.isFetching) return;
        this.isFetching = true;

        const isBackground = options && options.background;
        if (!isBackground) {
            if (this.loadingState) this.loadingState.style.display = 'block';
            if (this.errorState) this.errorState.style.display = 'none';
            if (this.contentArea) this.contentArea.style.display = 'none';
            if (this.capsuleBar) this.capsuleBar.style.display = 'none';
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const response = await fetch('/llm/patient-summary', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
                body: JSON.stringify({
                    patient_id: this.patientId,
                    encounter_id: this.encounterId,
                    force_regenerate: this.isForceRegenerate
                })
            });

            this.isForceRegenerate = false;
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to generate summary');
            }

            this.summaryText = data.summary_text;
            window.cachedPatientSummary = data.summary_text;
            sessionStorage.setItem(`patient_brief_${this.patientId}`, data.summary_text);
            this.hasLoaded = true;

            this.renderSummary(data.summary_text, data.model_used);

            // Notify Scribe Studio and other subscribers
            window.dispatchEvent(new CustomEvent('patient-brief-loaded', {
                detail: { summary: this.summaryText, patientId: this.patientId }
            }));

            // Subtle toastr notification for background completion
            if (isBackground && typeof toastr !== 'undefined') {
                toastr.info('Longitudinal AI Patient Brief synthesized in background.', 'Clinical AI Ready', {
                    timeOut: 3500,
                    closeButton: true,
                    progressBar: true
                });
            }

        } catch (error) {
            if (!isBackground) {
                if (this.loadingState) this.loadingState.style.display = 'none';
                if (this.errorState) this.errorState.style.display = 'block';
                if (this.errorText) this.errorText.textContent = error.message;
            }
            console.error('Summary Error:', error);
        } finally {
            this.isFetching = false;
        }
    }

    formatMarkdown(text) {
        // Very basic markdown parser for the summary
        let html = text
            .replace(/^### (.*$)/gim, '<h3>$1</h3>')
            .replace(/^## (.*$)/gim, '<h2>$1</h2>')
            .replace(/^# (.*$)/gim, '<h1>$1</h1>')
            .replace(/\*\*(.*)\*\*/gim, '<strong>$1</strong>')
            .replace(/\*(.*)\*/gim, '<em>$1</em>')
            .replace(/^\- (.*$)/gim, '<ul><li>$1</li></ul>')
            .replace(/<\/ul>\n<ul>/gim, '')
            .replace(/\n/gim, '<br>');
            
        return html;
    }

    setupAudio() {
        if (!this.voiceEnabled || !this.synth) return;

        // Clean up text for speech (remove markdown asterisks, hashes)
        const cleanText = this.summaryText
            .replace(/[*#_]/g, '')
            .replace(/---/g, '')
            .replace(/<[^>]+>/g, '');

        this.utterance = new SpeechSynthesisUtterance(cleanText);
        let rateVal = parseFloat(this.rateSelect ? this.rateSelect.value : this.defaultRate);
        if (isNaN(rateVal) || !isFinite(rateVal) || rateVal < 0.1 || rateVal > 10) rateVal = 1.0;
        this.utterance.rate = rateVal;
        
        // Find a good voice based on dictation language setting
        const langSelect = document.querySelector('.select-speech-lang');
        const targetLang = langSelect ? langSelect.value : 'en-US';
        this.utterance.lang = targetLang;

        const voices = this.synth.getVoices();
        let preferredVoice = voices.find(v => v.lang === targetLang && (v.name.includes('Google') || v.name.includes('Natural')));
        if (!preferredVoice) preferredVoice = voices.find(v => v.lang.startsWith(targetLang.split('-')[0]));
        if (!preferredVoice) preferredVoice = voices.find(v => v.name.includes('Google') || v.name.includes('Natural') || v.lang === 'en-US');

        if (preferredVoice) {
            this.utterance.voice = preferredVoice;
        }

        this.utterance.onstart = () => {
            this.isPlaying = true;
            if (this.btnPlay) this.btnPlay.innerHTML = '<i class="mdi mdi-pause fs-5"></i>';
            if (this.statusText) this.statusText.textContent = 'Reading summary...';
            if (this.statusIcon) this.statusIcon.innerHTML = '<i class="mdi mdi-volume-high text-success"></i>';
        };

        this.utterance.onend = () => {
            this.isPlaying = false;
            if (this.btnPlay) this.btnPlay.innerHTML = '<i class="mdi mdi-play fs-5"></i>';
            if (this.statusText) this.statusText.textContent = 'Finished.';
            if (this.statusIcon) this.statusIcon.innerHTML = '<i class="mdi mdi-volume-off"></i>';
        };

        this.utterance.onpause = () => {
            this.isPlaying = false;
            if (this.btnPlay) this.btnPlay.innerHTML = '<i class="mdi mdi-play fs-5"></i>';
            if (this.statusText) this.statusText.textContent = 'Paused.';
            if (this.statusIcon) this.statusIcon.innerHTML = '<i class="mdi mdi-volume-medium"></i>';
        };

        this.utterance.onresume = () => {
            this.isPlaying = true;
            if (this.btnPlay) this.btnPlay.innerHTML = '<i class="mdi mdi-pause fs-5"></i>';
            if (this.statusText) this.statusText.textContent = 'Reading summary...';
            if (this.statusIcon) this.statusIcon.innerHTML = '<i class="mdi mdi-volume-high text-success"></i>';
        };
        
        // Ensure voices load
        if (speechSynthesis.onvoiceschanged !== undefined) {
            speechSynthesis.onvoiceschanged = () => {
                const updatedVoices = this.synth.getVoices();
                let prefVoice = updatedVoices.find(v => v.lang === targetLang && (v.name.includes('Google') || v.name.includes('Natural')));
                if (!prefVoice) prefVoice = updatedVoices.find(v => v.lang.startsWith(targetLang.split('-')[0]));
                if (!prefVoice) prefVoice = updatedVoices.find(v => v.name.includes('Google') || v.name.includes('Natural') || v.lang === 'en-US');
                if (prefVoice && this.utterance) {
                    this.utterance.voice = prefVoice;
                }
            };
        }
    }

    togglePlay() {
        if (!this.synth) return;

        if (this.synth.speaking) {
            if (this.synth.paused) {
                this.synth.resume();
            } else {
                this.synth.pause();
            }
        } else {
            this.playAudio();
        }
    }

    playAudio() {
        if (!this.synth) return;
        this.synth.cancel(); // Clear any pending speech
        
        // Re-create the utterance every time we play to avoid garbage collection bugs in Chrome/Safari
        const cleanText = this.summaryText
            .replace(/[*#_]/g, '')
            .replace(/---/g, '')
            .replace(/<[^>]+>/g, '');

        this.utterance = new SpeechSynthesisUtterance(cleanText);
        let rateVal = parseFloat(this.rateSelect ? this.rateSelect.value : this.defaultRate);
        if (isNaN(rateVal) || !isFinite(rateVal) || rateVal < 0.1 || rateVal > 10) rateVal = 1.0;
        this.utterance.rate = rateVal;
        
        const langSelect = document.querySelector('.select-speech-lang');
        const targetLang = langSelect ? langSelect.value : 'en-US';
        this.utterance.lang = targetLang;

        const voices = this.synth.getVoices();
        let preferredVoice = voices.find(v => v.lang === targetLang && (v.name.includes('Google') || v.name.includes('Natural')));
        if (!preferredVoice) preferredVoice = voices.find(v => v.lang.startsWith(targetLang.split('-')[0]));
        if (!preferredVoice) preferredVoice = voices.find(v => v.name.includes('Google') || v.name.includes('Natural') || v.lang === 'en-US');

        if (preferredVoice) {
            this.utterance.voice = preferredVoice;
        }

        this.utterance.onstart = () => {
            this.isPlaying = true;
            if (this.btnPlay) this.btnPlay.innerHTML = '<i class="mdi mdi-pause fs-5"></i>';
            if (this.statusText) this.statusText.textContent = 'Reading summary...';
            if (this.statusIcon) this.statusIcon.innerHTML = '<i class="mdi mdi-volume-high text-success"></i>';
        };

        this.utterance.onend = () => {
            this.isPlaying = false;
            if (this.btnPlay) this.btnPlay.innerHTML = '<i class="mdi mdi-play fs-5"></i>';
            if (this.statusText) this.statusText.textContent = 'Finished.';
            if (this.statusIcon) this.statusIcon.innerHTML = '<i class="mdi mdi-volume-off"></i>';
        };

        this.utterance.onpause = () => {
            this.isPlaying = false;
            if (this.btnPlay) this.btnPlay.innerHTML = '<i class="mdi mdi-play fs-5"></i>';
            if (this.statusText) this.statusText.textContent = 'Paused.';
            if (this.statusIcon) this.statusIcon.innerHTML = '<i class="mdi mdi-volume-medium"></i>';
        };

        this.utterance.onresume = () => {
            this.isPlaying = true;
            if (this.btnPlay) this.btnPlay.innerHTML = '<i class="mdi mdi-pause fs-5"></i>';
            if (this.statusText) this.statusText.textContent = 'Reading summary...';
            if (this.statusIcon) this.statusIcon.innerHTML = '<i class="mdi mdi-volume-high text-success"></i>';
        };

        this.synth.speak(this.utterance);
    }

    stopAudio() {
        if (!this.synth) return;
        this.synth.cancel();
        this.isPlaying = false;
        if (this.btnPlay) this.btnPlay.innerHTML = '<i class="mdi mdi-play fs-5"></i>';
        if (this.statusText) this.statusText.textContent = 'Ready to read...';
        if (this.statusIcon) this.statusIcon.innerHTML = '<i class="mdi mdi-volume-off"></i>';
    }

    copyToClipboard() {
        if (!this.summaryText) return;
        
        navigator.clipboard.writeText(this.summaryText).then(() => {
            const btn = document.querySelector('.btn-copy-summary');
            if (btn) {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="mdi mdi-check"></i> Copied!';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-outline-secondary');
                
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-secondary');
                }, 2000);
            }
        }).catch(err => {
            console.error('Failed to copy text: ', err);
        });
    }
}

// Export to window
window.PatientSummaryManager = PatientSummaryManager;
