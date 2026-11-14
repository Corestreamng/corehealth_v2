/**
 * CoreHealth by CorestreamNG — Standalone iFrame Embed Controller
 * (c) Corehealth by CorestreamNG <hello@corestream.ng>
 *
 * Manages UI interactions within the sandboxed iframe, suggested orders deck,
 * taxonomy switching, and communicates events to parent window via postMessage.
 */

(function () {
  'use strict';

  var config = window.CH_EMBED_CONFIG || {};
  var token = config.token || '';
  var role = config.role || 'doctor';
  var taxonomy = config.taxonomy || 'soap';
  var customCatalog = config.customCatalog || null;

  var isRecording = false;
  var recognition = null;
  var fullTranscript = '';
  var structuredSections = [];
  var groundedOrders = [];
  var activeFilter = 'all';

  var recordBtn = document.getElementById('ch-btn-record');
  var transcriptArea = document.getElementById('ch-transcript-area');
  var structuredNoteArea = document.getElementById('ch-note-area');
  var noteLabel = document.getElementById('ch-note-label');
  var signBtn = document.getElementById('ch-btn-sign');
  var statusBadge = document.getElementById('ch-status-badge');
  var ordersDeck = document.getElementById('ch-orders-deck');
  var ordersTbody = document.getElementById('ch-orders-tbody');
  var ordersCountBadge = document.getElementById('ch-orders-count-badge');
  var tariffCounter = document.getElementById('ch-tariff-counter');

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
    role: role,
    taxonomy: taxonomy,
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
        recordBtn.innerText = 'Stop Dictation';
        recordBtn.style.background = '#dc2626';
        if (statusBadge) {
          statusBadge.innerText = '● Recording...';
          statusBadge.style.background = '#dc2626';
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
        recordBtn.innerText = 'Start Dictation';
        recordBtn.style.background = '#011b33';
        if (statusBadge) {
          statusBadge.innerText = 'Structuring note & orders...';
          statusBadge.style.background = '#d97706';
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
        statusBadge.style.background = '#64748b';
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
          encounter_id: config.encounterId,
          role: role,
          taxonomy: taxonomy,
          custom_catalog: customCatalog || undefined,
          existing_sections: structuredSections,
          existing_orders: groundedOrders
        })
      });

      var data = await response.json();
      if (data.success) {
        structuredSections = data.structured_sections || [];
        groundedOrders = data.grounded_orders || [];

        if (structuredNoteArea) {
          structuredNoteArea.value = data.formatted_markdown || '';
        }
        if (statusBadge) {
          statusBadge.innerText = 'Ready for Review';
          statusBadge.style.background = '#16a34a';
        }
        if (signBtn) {
          signBtn.disabled = false;
        }

        renderOrdersTable();

        postToParent('corehealth:note-ready', {
          formatted_markdown: data.formatted_markdown,
          structured_sections: structuredSections,
          orders: groundedOrders
        });
        postToParent('corehealth:orders-updated', groundedOrders);
      }
    } catch (err) {
      console.error('Error processing transcript', err);
      postToParent('corehealth:error', { code: 'PROCESS_ERROR', message: err.message });
    }
  }

  function renderOrdersTable() {
    if (!ordersDeck || !ordersTbody) return;

    if (groundedOrders.length === 0) {
      ordersDeck.style.display = 'none';
      return;
    }

    ordersDeck.style.display = 'block';
    if (ordersCountBadge) {
      ordersCountBadge.innerText = groundedOrders.length + ' RECOMMENDED';
    }

    var filtered = groundedOrders.filter(function (ord) {
      if (activeFilter === 'all') return true;
      if (activeFilter === 'rx') return ord.order_type === 'PRODUCT';
      if (activeFilter === 'lab') return ord.service_type === 'lab';
      if (activeFilter === 'rad') return ord.service_type === 'imaging';
      return true;
    });

    var totalTariff = 0;
    var rowsHtml = '';

    filtered.forEach(function (ord, idx) {
      var realIdx = groundedOrders.indexOf(ord);
      if (ord.is_selected) {
        totalTariff += (ord.unit_price || 0) * (ord.quantity || 1);
      }

      var catBadgeStyle = 'background: #dbeafe; color: #1e40af;';
      var catLabel = 'PRESCRIPTION';
      if (ord.order_type === 'SERVICE') {
        if (ord.service_type === 'lab') { catBadgeStyle = 'background: #ede9fe; color: #5b21b6;'; catLabel = 'LABORATORY'; }
        else if (ord.service_type === 'imaging') { catBadgeStyle = 'background: #fef3c7; color: #92400e;'; catLabel = 'IMAGING'; }
        else { catBadgeStyle = 'background: #fee2e2; color: #991b1b;'; catLabel = 'PROCEDURE'; }
      }

      rowsHtml += '<tr style="border-bottom: 1px solid #f1f5f9;">' +
        '<td style="padding: 6px 10px;"><input type="checkbox" class="ch-chk" data-idx="' + realIdx + '" ' + (ord.is_selected ? 'checked' : '') + '></td>' +
        '<td style="padding: 6px 10px;"><span style="padding: 2px 6px; border-radius: 3px; font-weight: 700; font-size: 10px; ' + catBadgeStyle + '">' + catLabel + '</span></td>' +
        '<td style="padding: 6px 10px;"><strong>' + ord.item_name + '</strong>' + (ord.catalog_id ? ' <span style="color: #16a34a; font-size: 10px;">#' + ord.catalog_id + '</span>' : '') + '</td>' +
        '<td style="padding: 6px 10px;"><input type="text" class="ch-spec" data-idx="' + realIdx + '" value="' + (ord.dosage || ord.indication || '') + '" style="width: 100%; padding: 2px 6px; font-size: 11px; border: 1px solid #cbd5e1; border-radius: 3px;"></td>' +
        '<td style="padding: 6px 10px; font-weight: 600;">₦' + ((ord.unit_price || 0) * (ord.quantity || 1)).toLocaleString() + '</td>' +
        '</tr>';
    });

    ordersTbody.innerHTML = rowsHtml;
    if (tariffCounter) {
      tariffCounter.innerText = 'Estimated Tariff: ₦' + totalTariff.toLocaleString();
    }

    ordersTbody.querySelectorAll('.ch-chk').forEach(function (chk) {
      chk.addEventListener('change', function (e) {
        var i = parseInt(e.target.getAttribute('data-idx'), 10);
        groundedOrders[i].is_selected = e.target.checked;
        renderOrdersTable();
        postToParent('corehealth:orders-updated', groundedOrders);
      });
    });

    ordersTbody.querySelectorAll('.ch-spec').forEach(function (inp) {
      inp.addEventListener('input', function (e) {
        var i = parseInt(e.target.getAttribute('data-idx'), 10);
        if (groundedOrders[i].order_type === 'PRODUCT') {
          groundedOrders[i].dosage = e.target.value;
        } else {
          groundedOrders[i].indication = e.target.value;
        }
      });
    });
  }

  // Taxonomy switching
  document.querySelectorAll('.ch-tax-pill').forEach(function (pill) {
    pill.addEventListener('click', async function (e) {
      var targetTax = e.target.getAttribute('data-tax');
      if (!targetTax || targetTax === taxonomy) return;

      document.querySelectorAll('.ch-tax-pill').forEach(function (p) {
        p.classList.remove('active');
        p.style.background = 'none';
        p.style.color = '#475569';
      });
      e.target.classList.add('active');
      e.target.style.background = '#011b33';
      e.target.style.color = '#fff';

      try {
        var res = await fetch('/api/sdk/scribe/convert-format', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + token
          },
          body: JSON.stringify({
            from_taxonomy: taxonomy,
            to_taxonomy: targetTax,
            role: role,
            current_sections: structuredSections
          })
        });
        var data = await res.json();
        if (data.success) {
          taxonomy = targetTax;
          structuredSections = data.structured_sections || [];
          if (structuredNoteArea) structuredNoteArea.value = data.formatted_markdown || '';
          if (noteLabel) noteLabel.innerText = '2. Structured ' + targetTax.toUpperCase() + ' Note & Orders';
        }
      } catch (err) {
        console.warn('Taxonomy conversion error', err);
      }
    });
  });

  // Filter buttons
  document.querySelectorAll('.ch-filter-btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      document.querySelectorAll('.ch-filter-btn').forEach(function (b) {
        b.style.background = '#fff';
        b.style.color = '#475569';
      });
      e.target.style.background = '#011b33';
      e.target.style.color = '#fff';
      activeFilter = e.target.getAttribute('data-filter') || 'all';
      renderOrdersTable();
    });
  });

  // Sign off
  if (signBtn) {
    signBtn.addEventListener('click', async function () {
      var finalNote = structuredNoteArea ? structuredNoteArea.value.trim() : '';
      if (!finalNote) {
        alert('Please review or type the note before signing.');
        return;
      }

      signBtn.disabled = true;
      signBtn.innerText = 'Signing Note & Orders...';

      var selectedOrders = groundedOrders.filter(function (o) { return o.is_selected; });

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
            role: role,
            taxonomy_type: taxonomy,
            final_signed_note: finalNote,
            original_transcript: transcriptArea ? transcriptArea.value : fullTranscript,
            approved_orders: selectedOrders
          })
        });

        var signData = await signRes.json();

        // Fetch compositions
        var compRes = await fetch('/api/sdk/scribe/compositions', {
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
          statusBadge.style.background = '#16a34a';
        }

        signBtn.innerText = '✓ Signed';

        postToParent('corehealth:signed-off', {
          audit_id: signData.audit_id,
          patient_id: config.patientId,
          encounter_id: config.encounterId,
          role: role,
          taxonomy: taxonomy,
          signed_at: signData.signed_at,
          final_signed_note: finalNote,
          approved_orders: selectedOrders,
          openehr_composition: compData.openehr_composition || null,
          fhir_bundle: compData.fhir_bundle || null
        });
      } catch (err) {
        console.error('Error signing scribe note', err);
        signBtn.disabled = false;
        signBtn.innerText = 'Accept & Sign Note & Orders';
        postToParent('corehealth:error', { code: 'SIGN_ERROR', message: err.message });
      }
    });
  }
})();
