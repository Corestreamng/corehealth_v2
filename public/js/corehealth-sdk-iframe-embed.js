/**
 * CoreHealth by CorestreamNG — Standalone iFrame Embed Controller
 * (c) Corehealth by CorestreamNG <hello@corestream.ng>
 *
 * Manages UI interactions within the sandboxed iframe, suggested orders deck,
 * vanilla order staging modal, taxonomy switching, and communicates events
 * to parent window via postMessage.
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
  var recordingSeconds = 0;
  var recordingTimer = null;

  var recordBtn = document.getElementById('ch-btn-record');
  var recordBtnText = document.getElementById('ch-btn-record-text');
  var transcriptArea = document.getElementById('ch-transcript-area');
  var structuredNoteArea = document.getElementById('ch-note-area');
  var noteEmptyGuide = document.getElementById('ch-note-empty-guide');
  var noteLabel = document.getElementById('ch-note-label');
  var copyBtn = document.getElementById('ch-btn-copy');
  var signBtn = document.getElementById('ch-btn-sign');
  var statusBadge = document.getElementById('ch-status-badge');
  var ordersDeck = document.getElementById('ch-orders-deck');
  var ordersTbody = document.getElementById('ch-orders-tbody');
  var ordersCountBadge = document.getElementById('ch-orders-count-badge');
  var tariffCounter = document.getElementById('ch-tariff-counter');

  // Modal elements
  var modalAddOrder = document.getElementById('ch-modal-add-order');
  var btnCloseModal = document.getElementById('ch-btn-close-modal');
  var btnCancelModal = document.getElementById('ch-btn-cancel-modal');
  var btnConfirmAdd = document.getElementById('ch-btn-confirm-add');
  var moCat = document.getElementById('ch-mo-cat');
  var moName = document.getElementById('ch-mo-name');
  var moSpec = document.getElementById('ch-mo-spec');
  var moInst = document.getElementById('ch-mo-inst');
  var moTariff = document.getElementById('ch-mo-tariff');

  var SVG_TRASH = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>';
  var SVG_CHECK = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
  var SVG_MIC = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>';
  var SVG_STOP = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>';
  var SVG_SPARKLE = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';

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

  function formatTimer(sec) {
    var m = String(Math.floor(sec / 60)).padStart(2, '0');
    var s = String(sec % 60).padStart(2, '0');
    return m + ':' + s;
  }

  function setStepperStep(step) {
    var n1 = document.getElementById('ch-st-node-1');
    var n2 = document.getElementById('ch-st-node-2');
    var n3 = document.getElementById('ch-st-node-3');
    var b1 = document.getElementById('ch-bubble-1');
    var b2 = document.getElementById('ch-bubble-2');
    var b3 = document.getElementById('ch-bubble-3');

    if (b1) {
      b1.style.background = step >= 1 ? (step > 1 ? '#16a34a' : '#011b33') : '#cbd5e1';
      b1.style.color = step >= 1 ? '#fff' : '#475569';
    }
    if (n1) {
      n1.style.color = step >= 1 ? (step > 1 ? '#16a34a' : '#011b33') : '#64748b';
      n1.style.fontWeight = step === 1 ? '700' : '600';
    }

    if (b2) {
      b2.style.background = step >= 2 ? (step > 2 ? '#16a34a' : '#011b33') : '#cbd5e1';
      b2.style.color = step >= 2 ? '#fff' : '#475569';
    }
    if (n2) {
      n2.style.color = step >= 2 ? (step > 2 ? '#16a34a' : '#011b33') : '#64748b';
      n2.style.fontWeight = step === 2 ? '700' : (step > 2 ? '600' : '500');
    }

    if (b3) {
      b3.style.background = step >= 3 ? '#011b33' : '#cbd5e1';
      b3.style.color = step >= 3 ? '#fff' : '#475569';
    }
    if (n3) {
      n3.style.color = step >= 3 ? '#011b33' : '#64748b';
      n3.style.fontWeight = step === 3 ? '700' : '500';
    }
  }

  function formatTimer(sec) {
    var m = String(Math.floor(sec / 60)).padStart(2, '0');
    var s = String(sec % 60).padStart(2, '0');
    return m + ':' + s;
  }

  function setStepperStep(step) {
    var n1 = document.getElementById('ch-st-node-1');
    var n2 = document.getElementById('ch-st-node-2');
    var n3 = document.getElementById('ch-st-node-3');
    var b1 = document.getElementById('ch-bubble-1');
    var b2 = document.getElementById('ch-bubble-2');
    var b3 = document.getElementById('ch-bubble-3');

    if (b1) {
      b1.style.background = step >= 1 ? (step > 1 ? '#16a34a' : '#011b33') : '#cbd5e1';
      b1.style.color = step >= 1 ? '#fff' : '#475569';
    }
    if (n1) {
      n1.style.color = step >= 1 ? (step > 1 ? '#16a34a' : '#011b33') : '#64748b';
      n1.style.fontWeight = step === 1 ? '700' : '600';
    }

    if (b2) {
      b2.style.background = step >= 2 ? (step > 2 ? '#16a34a' : '#011b33') : '#cbd5e1';
      b2.style.color = step >= 2 ? '#fff' : '#475569';
    }
    if (n2) {
      n2.style.color = step >= 2 ? (step > 2 ? '#16a34a' : '#011b33') : '#64748b';
      n2.style.fontWeight = step === 2 ? '700' : (step > 2 ? '600' : '500');
    }

    if (b3) {
      b3.style.background = step >= 3 ? '#011b33' : '#cbd5e1';
      b3.style.color = step >= 3 ? '#fff' : '#475569';
    }
    if (n3) {
      n3.style.color = step >= 3 ? '#011b33' : '#64748b';
      n3.style.fontWeight = step === 3 ? '700' : '500';
    }
  }

  if (recordBtn) {
    recordBtn.addEventListener('click', function () {
      if (!isRecording) {
        // Start recording
        isRecording = true;
        recordingSeconds = 0;
        recordBtn.style.background = '#dc2626';
        recordBtn.innerHTML = SVG_STOP + ' <span id="ch-btn-record-text">Stop Dictation (00:00)</span>';

        if (statusBadge) {
          statusBadge.innerHTML = '<span class="ch-rec-pulse"></span> <span>Recording (00:00)</span>';
          statusBadge.style.background = '#dc2626';
        }

        recordingTimer = setInterval(function () {
          recordingSeconds++;
          var timeStr = formatTimer(recordingSeconds);
          var txt = document.getElementById('ch-btn-record-text');
          if (txt) txt.innerText = 'Stop Dictation (' + timeStr + ')';
          if (statusBadge) {
            statusBadge.innerHTML = '<span class="ch-rec-pulse"></span> <span>Recording (' + timeStr + ')</span>';
          }
        }, 1000);

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
        if (recordingTimer) {
          clearInterval(recordingTimer);
          recordingTimer = null;
        }
        recordBtn.style.background = '#011b33';
        recordBtn.innerHTML = SVG_MIC + ' <span id="ch-btn-record-text">Start Dictation</span>';

        if (statusBadge) {
          statusBadge.innerHTML = SVG_SPARKLE + ' <span>Structuring note & orders...</span>';
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

  var sampleBtn = document.getElementById('ch-btn-sample');
  if (sampleBtn) {
    sampleBtn.addEventListener('click', function () {
      if (transcriptArea) {
        transcriptArea.value = 'Patient presents with a 3-day history of high-grade fever, chills, severe headache, and general body weakness. Physical examination reveals: Temperature 38.6°C, BP 124/82 mmHg, Pulse 88 bpm. Chest is clear on auscultation, abdomen is soft and non-tender. Assessment: Acute Uncomplicated Malaria. Prescribe Artemether-Lumefantrine 80/480mg twice daily for 3 days and Paracetamol 500mg TDS for 3 days. Request Urgent Malaria Parasite (MP) Giemsa microscopy and Full Blood Count (FBC). Advise adequate bed rest and oral fluid intake.';
        fullTranscript = transcriptArea.value;
      }
    });
  }

  var processBtn = document.getElementById('ch-btn-process');
  if (processBtn) {
    processBtn.addEventListener('click', function () {
      if (statusBadge) {
        statusBadge.innerHTML = SVG_SPARKLE + ' <span>Structuring note & orders...</span>';
        statusBadge.style.background = '#d97706';
      }
      postToParent('corehealth:processing', {});
      processTranscript();
    });
  }

  var copyBtn = document.getElementById('ch-btn-copy');
  if (copyBtn) {
    copyBtn.addEventListener('click', async function () {
      if (structuredNoteArea && structuredNoteArea.value) {
        try {
          await navigator.clipboard.writeText(structuredNoteArea.value);
          var copyTxt = document.getElementById('ch-btn-copy-text');
          if (copyTxt) copyTxt.innerText = 'Copied!';
          setTimeout(function () {
            if (copyTxt) copyTxt.innerText = 'Copy';
          }, 1500);
        } catch (e) {
          console.warn('Clipboard write error', e);
        }
      }
    });
  }

  var clearBtn = document.getElementById('ch-btn-clear');
  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      if (confirm('Clear current transcript and clinical orders?')) {
        if (transcriptArea) transcriptArea.value = '';
        if (structuredNoteArea) {
          structuredNoteArea.value = '';
          structuredNoteArea.style.display = 'none';
        }
        if (noteEmptyGuide) noteEmptyGuide.style.display = 'flex';
        if (copyBtn) copyBtn.style.display = 'none';
        setStepperStep(1);
        fullTranscript = '';
        structuredSections = [];
        groundedOrders = [];
        renderOrdersTable();
        if (signBtn) signBtn.disabled = true;
        if (statusBadge) {
          statusBadge.innerHTML = SVG_CHECK + ' <span>Ready</span>';
          statusBadge.style.background = '#64748b';
        }
        postToParent('corehealth:orders-updated', groundedOrders);
      }
    });
  }

  var selectAllBox = document.getElementById('ch-select-all');
  if (selectAllBox) {
    selectAllBox.addEventListener('change', function (e) {
      var checked = e.target.checked;
      groundedOrders.forEach(function (ord) {
        ord.selected = checked;
        ord.is_selected = checked;
      });
      document.querySelectorAll('.ch-order-checkbox').forEach(function (cb) { cb.checked = checked; });
      updateTariffPreview();
      postToParent('corehealth:orders-updated', groundedOrders);
    });
  }

  // Vanilla Modal Handling
  function openAddOrderModal() {
    if (modalAddOrder) {
      modalAddOrder.style.display = 'flex';
      if (moName) {
        moName.value = '';
        moName.focus();
      }
      if (moSpec) moSpec.value = '';
      if (moInst) moInst.value = '';
      if (moTariff) moTariff.value = '1000';
    }
  }

  function closeAddOrderModal() {
    if (modalAddOrder) modalAddOrder.style.display = 'none';
  }

  var addOrderBtn = document.getElementById('ch-btn-add-order');
  if (addOrderBtn) addOrderBtn.addEventListener('click', openAddOrderModal);
  if (btnCloseModal) btnCloseModal.addEventListener('click', closeAddOrderModal);
  if (btnCancelModal) btnCancelModal.addEventListener('click', closeAddOrderModal);
  if (modalAddOrder) {
    modalAddOrder.addEventListener('click', function (e) {
      if (e.target === modalAddOrder) closeAddOrderModal();
    });
  }

  function submitAddOrder() {
    var name = moName ? moName.value.trim() : '';
    if (!name) {
      if (moName) {
        moName.style.borderColor = '#dc2626';
        moName.focus();
      }
      return;
    }
    if (moName) moName.style.borderColor = '#cbd5e1';

    var cat = moCat ? moCat.value : 'medication';
    var spec = moSpec ? moSpec.value.trim() : '';
    var inst = moInst ? moInst.value.trim() : '';
    var tariff = moTariff ? parseFloat(moTariff.value) || 0 : 0;
    var isMed = cat === 'medication';

    groundedOrders.push({
      item_id: 'custom_' + Date.now(),
      id: 'ord_' + (groundedOrders.length + 1),
      item_name: name,
      order_type: isMed ? 'PRODUCT' : 'SERVICE',
      service_type: isMed ? null : cat,
      category_id: isMed ? 4 : 5,
      category_name: isMed ? 'Prescription' : cat.toUpperCase(),
      catalog_id: null,
      dosage: isMed ? (spec || '1 tab') : null,
      indication: !isMed ? spec : null,
      frequency: isMed ? 'OD' : null,
      duration: isMed ? '3 days' : null,
      route: isMed ? 'oral' : null,
      instructions: inst || (isMed ? 'Take as directed by clinician' : 'Standard protocol'),
      unit_price: tariff,
      quantity: 1,
      selected: true,
      is_selected: true
    });

    renderOrdersTable();
    postToParent('corehealth:orders-updated', groundedOrders);
    closeAddOrderModal();
  }

  if (btnConfirmAdd) btnConfirmAdd.addEventListener('click', submitAddOrder);
  if (moName) {
    moName.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') submitAddOrder();
      if (e.key === 'Escape') closeAddOrderModal();
    });
  }

  async function processTranscript() {
    var text = transcriptArea ? transcriptArea.value.trim() : fullTranscript.trim();
    if (!text) {
      if (statusBadge) {
        statusBadge.innerHTML = SVG_CHECK + ' <span>Ready</span>';
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

        if (noteEmptyGuide) noteEmptyGuide.style.display = 'none';
        if (structuredNoteArea) {
          structuredNoteArea.style.display = 'block';
          structuredNoteArea.value = data.formatted_markdown || '';
        }
        if (copyBtn) copyBtn.style.display = 'inline-flex';
        setStepperStep(3);

        if (statusBadge) {
          statusBadge.innerHTML = SVG_CHECK + ' <span>Ready for Review</span>';
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

  function updateTariffPreview() {
    if (!tariffCounter) return;
    var totalTariff = 0;
    groundedOrders.forEach(function (ord) {
      if (ord.selected || ord.is_selected) {
        totalTariff += (ord.unit_price || 0) * (ord.quantity || 1);
      }
    });
    tariffCounter.innerText = 'Estimated Tariff: ₦' + totalTariff.toLocaleString();
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

    var rowsHtml = '';
    filtered.forEach(function (ord) {
      var realIdx = groundedOrders.indexOf(ord);
      var isSel = (ord.selected !== false && ord.is_selected !== false);

      var catBadgeStyle = 'background: #dbeafe; color: #1e40af;';
      var catLabel = 'PRESCRIPTION';
      if (ord.order_type === 'SERVICE') {
        if (ord.service_type === 'lab') { catBadgeStyle = 'background: #ede9fe; color: #5b21b6;'; catLabel = 'LABORATORY'; }
        else if (ord.service_type === 'imaging') { catBadgeStyle = 'background: #fef3c7; color: #92400e;'; catLabel = 'IMAGING'; }
        else { catBadgeStyle = 'background: #fee2e2; color: #991b1b;'; catLabel = 'PROCEDURE'; }
      }

      rowsHtml += '<tr style="border-bottom: 1px solid #f1f5f9;">' +
        '<td style="padding: 6px 10px;"><input type="checkbox" class="ch-order-checkbox" data-idx="' + realIdx + '" ' + (isSel ? 'checked' : '') + '></td>' +
        '<td style="padding: 6px 10px;"><span style="padding: 2px 6px; border-radius: 3px; font-weight: 700; font-size: 10px; ' + catBadgeStyle + '">' + catLabel + '</span></td>' +
        '<td style="padding: 6px 10px;"><strong>' + ord.item_name + '</strong>' + (ord.catalog_id ? ' <span style="color: #16a34a; font-size: 10px;">#' + ord.catalog_id + '</span>' : '') + '</td>' +
        '<td style="padding: 6px 10px;"><input type="text" class="ch-spec" data-idx="' + realIdx + '" value="' + (ord.dosage || ord.indication || '') + '" style="width: 100%; padding: 2px 6px; font-size: 11px; border: 1px solid #cbd5e1; border-radius: 3px;"></td>' +
        '<td style="padding: 6px 10px; font-weight: 600;">₦' + ((ord.unit_price || 0) * (ord.quantity || 1)).toLocaleString() + '</td>' +
        '<td style="padding: 6px 10px; text-align: center;"><button type="button" class="ch-btn-icon-del ch-del-order" data-idx="' + realIdx + '" title="Remove order from staging">' + SVG_TRASH + '</button></td>' +
        '</tr>';
    });

    ordersTbody.innerHTML = rowsHtml;
    updateTariffPreview();

    // Checkbox toggles
    ordersTbody.querySelectorAll('.ch-order-checkbox').forEach(function (chk) {
      chk.addEventListener('change', function (e) {
        var i = parseInt(e.target.getAttribute('data-idx'), 10);
        groundedOrders[i].selected = e.target.checked;
        groundedOrders[i].is_selected = e.target.checked;
        updateTariffPreview();
        postToParent('corehealth:orders-updated', groundedOrders);
      });
    });

    // Inline spec input
    ordersTbody.querySelectorAll('.ch-spec').forEach(function (inp) {
      inp.addEventListener('input', function (e) {
        var i = parseInt(e.target.getAttribute('data-idx'), 10);
        if (groundedOrders[i].order_type === 'PRODUCT') {
          groundedOrders[i].dosage = e.target.value;
        } else {
          groundedOrders[i].indication = e.target.value;
        }
        postToParent('corehealth:orders-updated', groundedOrders);
      });
    });

    // Delete buttons
    ordersTbody.querySelectorAll('.ch-del-order').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        var targetBtn = e.target.closest('.ch-del-order');
        if (!targetBtn) return;
        var i = parseInt(targetBtn.getAttribute('data-idx'), 10);
        groundedOrders.splice(i, 1);
        renderOrdersTable();
        postToParent('corehealth:orders-updated', groundedOrders);
      });
    });
  }

  // Filter Buttons
  document.querySelectorAll('.ch-filter-btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      document.querySelectorAll('.ch-filter-btn').forEach(function (b) {
        b.style.background = '#fff';
        b.style.color = '#475569';
        b.classList.remove('active');
      });
      btn.style.background = '#011b33';
      btn.style.color = '#fff';
      btn.classList.add('active');
      activeFilter = btn.getAttribute('data-filter') || 'all';
      renderOrdersTable();
    });
  });

  // Taxonomy Switching
  document.querySelectorAll('.ch-tax-pill').forEach(function (btn) {
    btn.addEventListener('click', async function (e) {
      var targetTax = btn.getAttribute('data-tax');
      if (!targetTax || targetTax === taxonomy) return;

      document.querySelectorAll('.ch-tax-pill').forEach(function (b) {
        b.style.background = 'none';
        b.style.color = '#475569';
        b.classList.remove('active');
      });
      btn.style.background = '#011b33';
      btn.style.color = '#fff';
      btn.classList.add('active');

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
          if (structuredNoteArea) {
            structuredNoteArea.value = data.formatted_markdown || '';
          }
          if (noteLabel) {
            noteLabel.innerText = '2. Structured ' + targetTax.toUpperCase() + ' Note & Orders';
          }
        }
      } catch (err) {
        console.error('Taxonomy switch error', err);
      }
    });
  });

  // Sign-off
  if (signBtn) {
    signBtn.addEventListener('click', async function () {
      var finalNote = structuredNoteArea ? structuredNoteArea.value.trim() : '';
      if (!finalNote) return;

      signBtn.disabled = true;
      signBtn.innerHTML = '<span>Signing Note & Orders...</span>';

      var selectedOrders = groundedOrders.filter(function (o) {
        return o.selected !== false && o.is_selected !== false;
      });

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
            original_transcript: transcriptArea ? transcriptArea.value : '',
            approved_orders: selectedOrders
          })
        });
        var signData = await signRes.json();

        signBtn.innerHTML = SVG_CHECK + ' <span>Signed</span>';
        if (statusBadge) {
          statusBadge.innerHTML = SVG_CHECK + ' <span>Signed Off</span>';
          statusBadge.style.background = '#16a34a';
        }

        postToParent('corehealth:signed-off', {
          audit_id: signData.audit_id,
          patient_id: config.patientId,
          encounter_id: config.encounterId,
          role: role,
          taxonomy: taxonomy,
          signed_at: signData.signed_at,
          final_signed_note: finalNote,
          approved_orders: selectedOrders
        });
      } catch (err) {
        console.error('Error signing scribe session', err);
        signBtn.disabled = false;
        signBtn.innerText = 'Accept & Sign Note & Orders';
        postToParent('corehealth:error', { code: 'SIGN_ERROR', message: err.message });
      }
    });
  }

  // Listen for instructions from parent window
  window.addEventListener('message', function (event) {
    var data = event.data;
    if (!data || typeof data !== 'object') return;

    if (data.type === 'corehealth:set-catalog') {
      customCatalog = data.payload ? data.payload.custom_catalog : null;
    }
  });

})();
