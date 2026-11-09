/**
 * CoreHealth v2 - Workbench Screenshot Automation Tool
 * 
 * Uses Puppeteer-Core with local Google Chrome to capture authentic,
 * high-density zero-scroll cockpit views and interactive modals at
 * Full HD standard (1920 x 1080).
 *
 * Usage:
 *   node tools/capture_workbench_screenshots.js
 *   npm run capture:screenshots
 */

const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs');

const CHROME_PATH = process.env.CHROME_PATH || '/opt/google/chrome/google-chrome';
const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8000';
const EMAIL = process.env.AUTH_EMAIL || 'sysadmin@mail.com';
const PASSWORD = process.env.AUTH_PASSWORD || '123456';
const OUTPUT_DIR = path.resolve(__dirname, '../docs/public/images/workbenches');

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

async function captureAll() {
  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

  console.log(`[Screenshot Tool] Launching Chrome (${CHROME_PATH}) at Full HD 1920x1080...`);
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: 'new',
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--window-size=1920,1080',
      '--disable-gpu'
    ]
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1920, height: 1080 });

  // 1. Authenticate
  console.log(`[Screenshot Tool] Navigating to ${BASE_URL}/login...`);
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await delay(1000);
  await page.type('#email', EMAIL);
  await page.type('#password', PASSWORD);
  
  console.log('[Screenshot Tool] Submitting login...');
  await Promise.all([
    page.click('.btn-auth-primary'),
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 })
  ]);
  await delay(2000);
  console.log('[Screenshot Tool] Authenticated. Current page:', page.url());

  // --------------------------------------------------------------------------
  // RECEPTION WORKBENCH CAPTURES (1920x1080)
  // --------------------------------------------------------------------------
  console.log('[Screenshot Tool] 1. Capturing Reception Workbench Cockpit...');
  await page.goto(`${BASE_URL}/reception/workbench`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await delay(2500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-workbench-cockpit.jpg'), quality: 92 });

  console.log('[Screenshot Tool] 2. Triggering Reception Patient Registration Modal...');
  await page.evaluate(() => {
    if (typeof showPatientFormModal === 'function') {
      showPatientFormModal('create');
    } else if (typeof $ !== 'undefined') {
      $('#patientFormModal').modal('show');
    }
  });
  await delay(1500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-registration-modal.jpg'), quality: 92 });
  
  // Close registration modal cleanly
  await page.evaluate(() => {
    if (typeof $ !== 'undefined') {
      $('#patientFormModal').modal('hide');
      $('.modal-backdrop').remove();
      $('body').removeClass('modal-open').css('overflow', '');
    }
  });
  await delay(600);

  console.log('[Screenshot Tool] 3. Triggering Reception Walk-in Point-of-Sale Tab...');
  await page.evaluate(() => {
    if (typeof switchWorkspaceTab === 'function') {
      switchWorkspaceTab('walkin');
    } else if (typeof $ !== 'undefined') {
      $('button.workspace-tab[data-tab="walkin"], a[data-tab="walkin"]').click();
    }
  });
  await delay(1500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-walkin-pos.jpg'), quality: 92 });

  console.log('[Screenshot Tool] 3b. Triggering Reception Doctor Queue Full View...');
  await page.evaluate(() => {
    if (typeof showQueue === 'function') {
      showQueue('all');
    } else {
      const btn = document.getElementById('show-all-queue-btn') || document.getElementById('view-queue-btn');
      if (btn) btn.click();
    }
  });
  await delay(2500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-queue-panel.jpg'), quality: 92 });

  console.log('[Screenshot Tool] 3c. Triggering Reception Appointment Schedule Calendar...');
  await page.evaluate(() => {
    const apptItem = document.querySelector('.queue-item[data-filter="appointments-today"]');
    if (apptItem) {
      apptItem.click();
    } else if (typeof showAppointmentsCalendar === 'function') {
      showAppointmentsCalendar();
    }
  });
  await delay(2500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-appointments-calendar.jpg'), quality: 92 });

  console.log('[Screenshot Tool] 3d. Loading Patient Folder and Book Service Tab...');
  await page.evaluate(() => {
    if (typeof hideQueue === 'function') hideQueue();
    const closeAppt = document.getElementById('btn-close-appointments-view');
    if (closeAppt) closeAppt.click();
    if (typeof loadPatient === 'function') loadPatient(1);
  });
  await delay(2500);
  await page.evaluate(() => {
    if (typeof switchWorkspaceTab === 'function') {
      switchWorkspaceTab('booking');
    } else if (typeof $ !== 'undefined') {
      $('button.workspace-tab[data-tab="booking"]').click();
    }
  });
  await delay(2000);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-workspace-tabs.jpg'), quality: 92 });
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-booking-tab.jpg'), quality: 92 });

  // --------------------------------------------------------------------------
  // DOCTOR WORKBENCH QUEUES (1920x1080 with Historical Dates)
  // --------------------------------------------------------------------------
  console.log('[Screenshot Tool] 4. Capturing Doctor Consultation Queue (Waiting)...');
  await page.goto(`${BASE_URL}/encounters?status=waiting`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await delay(2500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-queue-waiting.jpg'), quality: 92 });

  console.log('[Screenshot Tool] 4b. Capturing Doctor Queue Calendar View...');
  await page.evaluate(() => {
    const calBtn = document.getElementById('btn-calendar-view');
    if (calBtn) calBtn.click();
  });
  await delay(2000);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-queue-calendar.jpg'), quality: 92 });

  console.log('[Screenshot Tool] 4c. Capturing Doctor Queue Inpatient Admissions (Fetched from 2026-01-01)...');
  await page.evaluate(() => {
    const histTab = document.getElementById('history-lists-tab');
    if (histTab) histTab.click();
    setTimeout(() => {
      const admTab = document.getElementById('other_admissions_tab') || document.getElementById('my_admissions_tab');
      if (admTab) admTab.click();
      const dateFromOther = document.getElementById('other_adm_start_date');
      if (dateFromOther) dateFromOther.value = '2026-01-01';
      const dateFromMy = document.getElementById('my_adm_start_date');
      if (dateFromMy) dateFromMy.value = '2026-01-01';
      const fetchOther = document.getElementById('other_adm_fetch_btn');
      if (fetchOther) fetchOther.click();
      const fetchMy = document.getElementById('my_adm_fetch_btn');
      if (fetchMy) fetchMy.click();
    }, 400);
  });
  await delay(3000);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-queue-admissions.jpg'), quality: 92 });

  console.log('[Screenshot Tool] 4d. Capturing Doctor Queue Referrals Subtab (Fetched from 2026-01-01)...');
  await page.evaluate(() => {
    const refTab = document.getElementById('all_referrals_tab') || document.getElementById('my_referrals_tab');
    if (refTab) refTab.click();
    const dateFromAll = document.getElementById('all_ref_start_date');
    if (dateFromAll) dateFromAll.value = '2026-01-01';
    const dateFromMy = document.getElementById('my_ref_start_date');
    if (dateFromMy) dateFromMy.value = '2026-01-01';
    const fetchAll = document.getElementById('all_ref_fetch_btn');
    if (fetchAll) fetchAll.click();
    const fetchMy = document.getElementById('my_ref_fetch_btn');
    if (fetchMy) fetchMy.click();
  });
  await delay(3000);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-queue-referrals.jpg'), quality: 92 });

  console.log('[Screenshot Tool] 4e. Capturing Doctor Queue Previous Encounters (Fetched from 2026-09-01)...');
  await page.evaluate(() => {
    const prevTab = document.getElementById('prev_data_tab');
    if (prevTab) prevTab.click();
    const dateFromPrev = document.getElementById('prev_start_date');
    if (dateFromPrev) dateFromPrev.value = '2026-09-01';
    const fetchPrev = document.getElementById('prev_fetch_btn');
    if (fetchPrev) fetchPrev.click();
  });
  await delay(2500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-queue-previous-encounters.jpg'), quality: 92 });

  // --------------------------------------------------------------------------
  // DOCTOR WORKBENCH CLINICAL ENCOUNTER (1920x1080)
  // --------------------------------------------------------------------------
  console.log('[Screenshot Tool] 5. Capturing Active Clinical Encounter Cockpit (Expanded Header)...');
  await page.goto(`${BASE_URL}/encounters/create?patient_id=1`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await delay(3000);
  await page.evaluate(() => window.scrollTo(0, 0));
  await delay(500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-encounter-cockpit.jpg'), quality: 92 });

  // Helper inside page to collapse sticky details panel so workspace tabs have full vertical height
  const collapseHeader = async () => {
    await page.evaluate(() => {
      const panel = document.getElementById('sch-details-panel');
      if (panel) panel.classList.remove('open');
      const btn = document.getElementById('sch-expand-btn');
      if (btn) btn.classList.remove('expanded');
      const textEl = document.getElementById('sch-expand-text');
      if (textEl) textEl.textContent = 'Details';
      const compactBtns = document.getElementById('sch-compact-actions');
      if (compactBtns) compactBtns.style.display = 'flex';
      window.scrollTo(0, 0);
    });
    await delay(300);
  };

  console.log('[Screenshot Tool] 6. Triggering Clinical Notes & Diagnosis Tab (Collapsed Header)...');
  await page.evaluate(() => {
    window._hasShownNoActivePlanPrompt = true;
    if (typeof switch_tab === 'function') {
      switch_tab(null, 'clinical_notes_tab');
    } else if (typeof $ !== 'undefined') {
      $('#clinical_notes_tab').click();
    }
  });
  await collapseHeader();
  await delay(1800);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-clinical-notes.jpg'), quality: 92 });

  // --------------------------------------------------------------------------
  // LABORATORY SERVICES: BOTH SUBTABS (History & New Request)
  // --------------------------------------------------------------------------
  console.log('[Screenshot Tool] 7a. Triggering Laboratory History Subtab...');
  await page.evaluate(() => {
    window._hasShownNoActivePlanPrompt = true;
    if (typeof switch_tab === 'function') switch_tab(null, 'laboratory_services_tab');
    const labHist = document.getElementById('lab-history-tab');
    if (labHist) labHist.click();
  });
  await collapseHeader();
  await delay(2000);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-lab-history.jpg'), quality: 92 });

  console.log('[Screenshot Tool] 7b. Triggering New Lab Request Subtab...');
  await page.evaluate(() => {
    const labNew = document.getElementById('lab-new-tab');
    if (labNew) labNew.click();
  });
  await delay(1800);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-lab-new.jpg'), quality: 92 });
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-lab.jpg'), quality: 92 });

  // --------------------------------------------------------------------------
  // MEDICATIONS: BOTH SUBTABS (Drug History & New Prescription)
  // --------------------------------------------------------------------------
  console.log('[Screenshot Tool] 8a. Triggering Medications Drug History Subtab...');
  await page.evaluate(() => {
    window._hasShownNoActivePlanPrompt = true;
    if (typeof switch_tab === 'function') switch_tab(null, 'medications_tab');
    const prescHist = document.getElementById('presc-history-tab');
    if (prescHist) prescHist.click();
  });
  await collapseHeader();
  await delay(2000);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-prescription-history.jpg'), quality: 92 });

  console.log('[Screenshot Tool] 8b. Triggering New Prescription Subtab...');
  await page.evaluate(() => {
    const prescNew = document.getElementById('presc-new-tab');
    if (prescNew) prescNew.click();
  });
  await delay(1800);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-prescription-new.jpg'), quality: 92 });
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-prescription.jpg'), quality: 92 });

  // --------------------------------------------------------------------------
  // IMAGING SERVICES: BOTH SUBTABS (Imaging History & New Request)
  // --------------------------------------------------------------------------
  console.log('[Screenshot Tool] 9a. Triggering Imaging History Subtab...');
  await page.evaluate(() => {
    window._hasShownNoActivePlanPrompt = true;
    if (typeof switch_tab === 'function') switch_tab(null, 'imaging_services_tab');
    const imgHist = document.getElementById('imaging-history-tab');
    if (imgHist) imgHist.click();
  });
  await collapseHeader();
  await delay(2000);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-imaging-history.jpg'), quality: 92 });
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-imaging.jpg'), quality: 92 });

  console.log('[Screenshot Tool] 9b. Triggering New Imaging Request Subtab...');
  await page.evaluate(() => {
    const imgNew = document.getElementById('imaging-new-tab');
    if (imgNew) imgNew.click();
  });
  await delay(1800);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-imaging-new.jpg'), quality: 92 });

  // --------------------------------------------------------------------------
  // PROCEDURES: BOTH SUBTABS (Procedure History & Request / Book)
  // --------------------------------------------------------------------------
  console.log('[Screenshot Tool] 10a. Triggering Procedure History Subtab...');
  await page.evaluate(() => {
    window._hasShownNoActivePlanPrompt = true;
    if (typeof switch_tab === 'function') switch_tab(null, 'procedures_tab');
    const procHist = document.getElementById('proc-history-tab');
    if (procHist) procHist.click();
  });
  await collapseHeader();
  await delay(2000);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-procedure-history.jpg'), quality: 92 });
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-procedures.jpg'), quality: 92 });

  console.log('[Screenshot Tool] 10b. Triggering Request / Book Procedure Subtab...');
  await page.evaluate(() => {
    const procNew = document.getElementById('proc-new-tab');
    if (procNew) procNew.click();
  });
  await delay(1800);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-procedure-new.jpg'), quality: 92 });

  // --------------------------------------------------------------------------
  // REFERRALS, ADMISSION & CONCLUDE MODALS (1920x1080)
  // --------------------------------------------------------------------------
  console.log('[Screenshot Tool] 11. Triggering Specialist Referrals Tab with Form Open (Collapsed Header)...');
  await page.evaluate(() => {
    window._hasShownNoActivePlanPrompt = true;
    if (typeof switch_tab === 'function') {
      switch_tab(null, 'referrals_tab');
    } else if (typeof $ !== 'undefined') {
      $('#referrals_tab').click();
    }
    const formCard = document.getElementById('referral-form-card');
    if (formCard) formCard.classList.remove('d-none');
    const toggleBtn = document.getElementById('toggle-referral-form-btn');
    if (toggleBtn) toggleBtn.innerHTML = '<i class="mdi mdi-close"></i> Hide Form';
  });
  await collapseHeader();
  await delay(1800);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-referrals-tab.jpg'), quality: 92 });

  console.log('[Screenshot Tool] 12. Triggering Inpatient Ward Admission Modal...');
  await page.evaluate(() => {
    if (typeof openAdmitModal === 'function') {
      openAdmitModal();
    } else if (typeof $ !== 'undefined') {
      $('#admitPatientModal').modal('show');
    }
  });
  await delay(1800);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-admit-modal.jpg'), quality: 92 });

  // Cleanly close admission modal
  await page.evaluate(() => {
    if (typeof $ !== 'undefined') {
      $('#admitPatientModal').modal('hide');
      $('.modal-backdrop').remove();
      $('body').removeClass('modal-open').css('overflow', '');
    }
  });
  await delay(600);

  console.log('[Screenshot Tool] 13. Triggering Conclude Consultation Summary Modal...');
  await page.evaluate(() => {
    if (typeof $ !== 'undefined' && $('#concludeEncounterModal').length) {
      $('#concludeEncounterModal').modal('show');
    }
  });
  await delay(1800);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-conclude-modal.jpg'), quality: 92 });

  await browser.close();
  console.log('[Screenshot Tool] Finished! All Full HD (1920x1080) screenshots captured in:', OUTPUT_DIR);
}

captureAll().catch(err => {
  console.error('[Screenshot Tool] Fatal error:', err);
  process.exit(1);
});
