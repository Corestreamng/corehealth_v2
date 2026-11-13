/**
 * CoreHealth AI Scribe — Standalone iFrame Embed Controller
 * Manages UI interactions within the sandboxed iframe and posts events to parent window.
 */

(function () {
  'use strict';

  var config = window.CH_EMBED_CONFIG || {};
  var token = config.token || '';
  var isRecording = false;
  var recognition = null;
  var fullTranscript = '';

  var recordBtn = document.getElementById('ch-btn-record');
  var transcriptArea = document.getElementById('ch-transcript-area');
  var structuredNoteArea = document.getElementById('ch-note-area');
  var signBtn = document.getElementById('ch-btn-sign');
  var statusBadge = document.getElementById('ch-status-badge');

  function postToParent(type, payload) {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({
        type: type,
        payload: payload || {}
      }, '*');
    }
  }

  // Notify parent container that iframe is mounted and ready
  postToParent('corehealth:ready', {
    patient_id: config.patientId,
    encounter_id: config.encounterId,
    locale: config.locale
  });

  // Setup Web Speech API if supported
  var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (SpeechRecognition) {
    recognition = new SpeechRecognition();
    recognition.continuous = true;
    recognition.interimResults = true;
    recognition.lang = config.locale || 'en-NG';

    recognition.onresult = function (event) {
      var interim = '';
      for (var i = event.resultIndex; i < event.results.length; ++i) {
        if (event.results[i].isFinal) {
          fullTranscript += event.results[i][0].transcript + ' ';
        } else {
          interim += event.results[i][0].transcript;
        }
      }
      if (transcriptArea) {
        transcriptArea.value = fullTranscript + (interim ? '(' + interim + ')' : '');
      }
    };

    recognition.onerror = function (event) {
      console.warn('Speech recognition error', event.error);
      postToParent('corehealth:error', { code: 'MIC_ERROR', message: event.error });
    };
  }

  if (recordBtn) {
    recordBtn.addEventListener('click', function () {
      if (!isRecording) {
        // Start recording
        isRecording = true;
        recordBtn.classList.add('is-recording');
        recordBtn.innerText = 'Stop Dictation';
        if (statusBadge) {
          statusBadge.innerText = '● Recording...';
          statusBadge.className = 'badge bg-danger text-white';
        }
        if (recognition) {
          try {
            recognition.start();
          } catch (e) {
            console.error('Recognition start error', e);
          }
        }
        postToParent('corehealth:recording-started', {});
      } else {
        // Stop recording and process
        isRecording = false;
        recordBtn.classList.remove('is-recording');
        recordBtn.innerText = 'Start Dictation';
        if (statusBadge) {
          statusBadge.innerText = 'Structuring note...';
          statusBadge.className = 'badge bg-warning text-dark';
        }
        if (recognition) {
          try {
            recognition.stop();
          } catch (e) {
            console.error('Recognition stop error', e);
          }
        }
        postToParent('corehealth:processing', {});
        processTranscript();
      }
    });
  }

  async function processTranscript() {
    var text = transcriptArea ? transcriptArea.value.trim() : fullTranscript.trim();
    if (!text) {
      if (statusBadge) {
        statusBadge.innerText = 'Idle';
        statusBadge.className = 'badge bg-secondary text-white';
      }
      return;
    }

    try {
      var response = await fetch('/api/sdk/scribe/process', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ' + token
        },
        body: JSON.stringify({
          transcript: text,
          patient_id: config.patientId,
          encounter_id: config.encounterId
        })
      });

      var data = await response.json();
      if (data.success && data.structured_sections) {
        var formatted = '';
        data.structured_sections.forEach(function (sec) {
          formatted += '### ' + sec.title + '\n' + sec.content + '\n\n';
        });
        if (structuredNoteArea) {
          structuredNoteArea.value = formatted.trim();
        }
        if (statusBadge) {
          statusBadge.innerText = 'Ready for Review';
          statusBadge.className = 'badge bg-info text-white';
        }
        if (signBtn) {
          signBtn.disabled = false;
        }
        postToParent('corehealth:note-ready', {
          soap_note: formatted,
          orders: data.extracted_orders || []
        });
      }
    } catch (err) {
      console.error('Error processing transcript', err);
      postToParent('corehealth:error', { code: 'PROCESS_ERROR', message: err.message });
    }
  }

  if (signBtn) {
    signBtn.addEventListener('click', async function () {
      var finalNote = structuredNoteArea ? structuredNoteArea.value.trim() : '';
      if (!finalNote) {
        alert('Please review or type the note before signing.');
        return;
      }

      signBtn.disabled = true;
      signBtn.innerText = 'Signing...';

      try {
        var signRes = await fetch('/api/sdk/scribe/sign', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + token
          },
          body: JSON.stringify({
            patient_id: config.patientId,
            encounter_id: config.encounterId,
            final_signed_note: finalNote,
            original_transcript: transcriptArea ? transcriptArea.value : fullTranscript
          })
        });

        var signData = await signRes.json();

        // Now fetch full openEHR and FHIR compositions
        var compRes = await fetch('/api/sdk/scribe/session/' + encodeURIComponent(token) + '/compositions', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + token
          },
          body: JSON.stringify({ cross_border: false })
        });
        var compData = await compRes.json();

        if (statusBadge) {
          statusBadge.innerText = '✓ Signed Off';
          statusBadge.className = 'badge bg-success text-white';
        }

        signBtn.innerText = '✓ Signed';

        postToParent('corehealth:signed-off', {
          audit_id: signData.audit_id,
          patient_id: config.patientId,
          encounter_id: config.encounterId,
          signed_at: signData.signed_at,
          final_signed_note: finalNote,
          original_transcript: transcriptArea ? transcriptArea.value : fullTranscript,
          openehr_composition: compData.openehr_composition || null,
          fhir_bundle: compData.fhir_bundle || null
        });
      } catch (err) {
        console.error('Error signing scribe note', err);
        signBtn.disabled = false;
        signBtn.innerText = 'Accept & Sign';
        postToParent('corehealth:error', { code: 'SIGN_ERROR', message: err.message });
      }
    });
  }
})();

