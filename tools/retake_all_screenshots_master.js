/**
 * CoreHealth v2 - Master Screenshot Capture Automation
 * Full HD (1920x1080) Quality 92
 * Anonymized / HIPAA-Compliant Mock Telemetry
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

async function run() {
  console.log(`[Master Capture] Launching Chrome (${CHROME_PATH}) at Full HD 1920x1080...`);
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
  console.log(`[Master Capture] Authenticating at ${BASE_URL}/login...`);
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await delay(1000);
  await page.type('#email', EMAIL);
  await page.type('#password', PASSWORD);
  
  await Promise.all([
    page.click('.btn-auth-primary'),
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 })
  ]);
  await delay(2000);
  console.log('[Master Capture] Authenticated successfully.');

  // ==========================================================================
  // SECTION 1: RECEPTION WORKBENCH
  // ==========================================================================
  console.log(`\n=== SECTION 1: RECEPTION WORKBENCH ===`);
  await page.goto(`${BASE_URL}/reception/workbench`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await delay(3000);

  // 1a. Cockpit Overview (Empty State)
  console.log('[Master Capture] 1. Cockpit overview...');
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-workbench-cockpit.jpg'), quality: 92 });

  // 1b. Load Patient 1 (Fresh workspace, Zero Modals)
  console.log('[Master Capture] 2. Loading Patient 1...');
  await page.evaluate(() => {
    if (typeof loadPatient === 'function') loadPatient(1);
  });
  await delay(3000);

  // 1c. Walk-in Sales Point-of-Sale Tab (Unobstructed)
  console.log('[Master Capture] 3. Walk-in Sales Point-of-Sale Tab...');
  await page.evaluate(() => {
    if (typeof switchWorkspaceTab === 'function') {
      switchWorkspaceTab('walkin');
    } else if (typeof $ !== 'undefined') {
      $('button.workspace-tab[data-tab="walkin"]').click();
    }
  });
  await delay(2500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-walkin-pos.jpg'), quality: 92 });
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-action-ribbon.jpg'), quality: 92 });

  // 1d. Consultation Booking Tab (Unobstructed)
  console.log('[Master Capture] 4. Consultation Booking Tab...');
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

  // 1e. Patient Profile Tab & Active Demographic Header
  console.log('[Master Capture] 5. Patient Profile Tab & Active Header...');
  await page.evaluate(() => {
    if (typeof switchWorkspaceTab === 'function') {
      switchWorkspaceTab('profile');
    } else if (typeof $ !== 'undefined') {
      $('button.workspace-tab[data-tab="profile"]').click();
    }
  });
  await delay(2000);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-patient-header.jpg'), quality: 92 });
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-patient-profile.jpg'), quality: 92 });

  // 1f. Doctor Queue View
  console.log('[Master Capture] 6. Doctor Queue View...');
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

  // 1g. Pending Referrals Queue
  console.log('[Master Capture] 7. Reception Pending Referrals Queue...');
  await page.evaluate(() => {
    const refItem = document.querySelector('.queue-item[data-filter="referrals"]');
    if (refItem) refItem.click();
    else if (typeof showQueue === 'function') showQueue('referrals');
  });
  await delay(2500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-referrals-queue.jpg'), quality: 92 });

  // 1h. Appointment Schedule
  console.log('[Master Capture] 8. Appointment Schedule Calendar...');
  await page.evaluate(() => {
    const apptItem = document.querySelector('.queue-item[data-filter="appointments-today"]');
    if (apptItem) apptItem.click();
    else if (typeof showAppointmentsCalendar === 'function') showAppointmentsCalendar();
  });
  await delay(2500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-appointments-calendar.jpg'), quality: 92 });

  // 1i. HMO Pending Validation Panel
  console.log('[Master Capture] 9. HMO Pending Validation Panel...');
  await page.evaluate(() => {
    if (typeof showHmoValidationPanel === 'function') {
      showHmoValidationPanel();
    } else {
      const hmoBtn = document.getElementById('btn-hmo-validation') || document.getElementById('queue-hmo-pending-item');
      if (hmoBtn) hmoBtn.click();
    }
  });
  await delay(2500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-hmo-validation.jpg'), quality: 92 });

  // 1j. Modals
  console.log('[Master Capture] 10a. Emergency Intake Modal...');
  await page.evaluate(() => {
    if (typeof showEmergencyIntakeModal === 'function') showEmergencyIntakeModal();
    else if (typeof $ !== 'undefined') $('#emergencyIntakeModal').modal('show');
  });
  await delay(1800);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-emergency-intake.jpg'), quality: 92 });

  console.log('[Master Capture] 10b. Quick Register Modal...');
  await page.evaluate(() => {
    if (typeof showQuickRegisterModal === 'function') showQuickRegisterModal();
    else if (typeof $ !== 'undefined') $('#quickRegisterModal').modal('show');
  });
  await delay(1800);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-quick-register.jpg'), quality: 92 });

  console.log('[Master Capture] 10c. Patient Registration Modal (5-Step)...');
  await page.evaluate(() => {
    if (typeof showPatientFormModal === 'function') showPatientFormModal('create');
    else if (typeof $ !== 'undefined') $('#patientFormModal').modal('show');
  });
  await delay(1800);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'reception-registration-modal.jpg'), quality: 92 });

  // ==========================================================================
  // SECTION 2: DOCTOR CLINICAL ENCOUNTER WORKBENCH
  // ==========================================================================
  console.log(`\n=== SECTION 2: DOCTOR CLINICAL ENCOUNTER WORKBENCH ===`);
  console.log('[Master Capture] 11. Active Clinical Encounter Cockpit (Expanded Header)...');
  await page.goto(`${BASE_URL}/encounters/create?patient_id=1`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await delay(3000);
  await page.evaluate(() => window.scrollTo(0, 0));
  await delay(500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-encounter-cockpit.jpg'), quality: 92 });

  // Helper inside page to collapse sticky details panel
  const collapseHeader = async () => {
    await page.evaluate(() => {
      window._hasShownNoActivePlanPrompt = true;
      window._activeTreatmentPlan = true;
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

  const activateMainTab = async (tabId) => {
    await page.evaluate((id) => {
      window._hasShownNoActivePlanPrompt = true;
      window._activeTreatmentPlan = true;
      if (typeof switch_tab === 'function') switch_tab(null, id);
      else if (typeof $ !== 'undefined') $('#' + id).click();
    }, tabId);
    await collapseHeader();
    await delay(1500);
  };

  const activateSubTab = async (subTabId) => {
    await page.evaluate((id) => {
      const el = document.getElementById(id);
      if (el) {
        el.click();
        if (typeof $ !== 'undefined' && typeof $(el).tab === 'function') $(el).tab('show');
      }
    }, subTabId);
    await delay(1500);
  };

  // 12. Clinical Notes Tab
  console.log('[Master Capture] 12. Clinical Notes & Diagnosis Tab...');
  await activateMainTab('clinical_notes_tab');
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-clinical-notes.jpg'), quality: 92 });

  // 13. Laboratory Services (Both Subtabs)
  console.log('[Master Capture] 13a. Laboratory History Subtab...');
  await activateMainTab('laboratory_services_tab');
  await activateSubTab('lab-history-tab');
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-lab-history.jpg'), quality: 92 });

  console.log('[Master Capture] 13b. New Lab Request Subtab...');
  await activateSubTab('lab-new-tab');
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-lab-new.jpg'), quality: 92 });
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-lab.jpg'), quality: 92 });

  // 14. Medications (Both Subtabs)
  console.log('[Master Capture] 14a. Drug History Subtab...');
  await activateMainTab('medications_tab');
  await activateSubTab('presc-history-tab');
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-prescription-history.jpg'), quality: 92 });

  console.log('[Master Capture] 14b. Add Prescription Subtab...');
  await activateSubTab('presc-new-tab');
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-prescription-new.jpg'), quality: 92 });
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-prescription.jpg'), quality: 92 });

  // 15. Imaging Services (Both Subtabs)
  console.log('[Master Capture] 15a. Imaging History Subtab...');
  await activateMainTab('imaging_services_tab');
  await activateSubTab('imaging-history-tab');
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-imaging-history.jpg'), quality: 92 });

  console.log('[Master Capture] 15b. New Imaging Request Subtab...');
  await activateSubTab('imaging-new-tab');
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-imaging-new.jpg'), quality: 92 });
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-imaging.jpg'), quality: 92 });

  // 16. Procedures (Both Subtabs)
  console.log('[Master Capture] 16a. Procedure History Subtab...');
  await activateMainTab('procedures_tab');
  await activateSubTab('proc-history-tab');
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-procedure-history.jpg'), quality: 92 });

  console.log('[Master Capture] 16b. Book Procedure Subtab...');
  await activateSubTab('proc-new-tab');
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-procedure-new.jpg'), quality: 92 });
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-orders-procedures.jpg'), quality: 92 });

  // 17. Specialist Referrals Tab
  console.log('[Master Capture] 17. Specialist Referrals Tab with Form Open...');
  await activateMainTab('referrals_tab');
  await page.evaluate(() => {
    const formCard = document.getElementById('referral-form-card');
    if (formCard) formCard.classList.remove('d-none');
  });
  await delay(1200);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-referrals-tab.jpg'), quality: 92 });

  // 18. Inpatient Admission Modal
  console.log('[Master Capture] 18. Inpatient Admission Modal...');
  await page.evaluate(() => {
    if (typeof openAdmitModal === 'function') openAdmitModal();
    else if (typeof $ !== 'undefined') $('#admitPatientModal').modal('show');
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

  // 19. Conclude Consultation Modal
  console.log('[Master Capture] 19. Conclude Consultation Summary Modal...');
  await page.evaluate(() => {
    if (typeof $ !== 'undefined' && $('#concludeEncounterModal').length) {
      $('#concludeEncounterModal').modal('show');
    }
  });
  await delay(1800);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-conclude-modal.jpg'), quality: 92 });

  // ==========================================================================
  // SECTION 3: DOCTOR QUEUES
  // ==========================================================================
  console.log(`\n=== SECTION 3: DOCTOR WORKBENCH QUEUES ===`);
  console.log('[Master Capture] 20. Doctor Consultation Queue (Waiting)...');
  await page.goto(`${BASE_URL}/encounters?status=waiting`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await delay(2500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-queue-waiting.jpg'), quality: 92 });

  console.log('[Master Capture] 21. Doctor Queue Calendar View...');
  await page.evaluate(() => {
    const calBtn = document.getElementById('btn-calendar-view');
    if (calBtn) calBtn.click();
  });
  await delay(2000);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-queue-calendar.jpg'), quality: 92 });

  console.log('[Master Capture] 22. Doctor Queue Inpatient Admissions (Fetched from 2026-01-01)...');
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

  console.log('[Master Capture] 23. Doctor Queue Referrals (Fetched from 2026-01-01)...');
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

  console.log('[Master Capture] 24. Doctor Queue Previous Encounters...');
  await page.evaluate(() => {
    const prevTab = document.getElementById('prev_data_tab');
    if (prevTab) prevTab.click();
  });
  await delay(2500);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'doctor-queue-previous-encounters.jpg'), quality: 92 });

  await browser.close();
  console.log('\n=======================================================');
  console.log('SUCCESS: All Full HD (1920x1080) Screenshots Re-Captured with Scrambled HIPAA Data!');
  console.log('Output Directory:', OUTPUT_DIR);
  console.log('=======================================================');
}

run().catch(err => {
  console.error('[Master Capture] Fatal error:', err);
  process.exit(1);
});
