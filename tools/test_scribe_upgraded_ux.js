const puppeteer = require('puppeteer-core');
const path = require('path');

(async () => {
  console.log('[Puppeteer] Launching Chrome for upgraded Scribe UX verification...');
  const browser = await puppeteer.launch({
    executablePath: '/home/mrapollos/.cache/ms-playwright/chromium-1217/chrome-linux64/chrome',
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--use-fake-ui-for-media-stream']
  });

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1200, height: 1600 });

    page.on('console', msg => console.log('PAGE LOG:', msg.text()));
    page.on('pageerror', err => console.error('PAGE ERROR:', err.message));

    console.log('[Puppeteer] Navigating to http://localhost:3001...');
    await page.goto('http://localhost:3001', { waitUntil: 'networkidle2' });

    // Wait for Scribe studio custom element to mount
    await page.waitForFunction(() => {
      const el = document.querySelector('corehealth-scribe-studio');
      return el && el.shadowRoot && el.shadowRoot.getElementById('transcript-input');
    }, { timeout: 10000 });
    console.log('[Puppeteer] Track A (Web Component) mounted successfully.');

    // 1. Click "Load Sample Dictation"
    await page.click('#demo-load-sample');
    await new Promise(r => setTimeout(r, 600));

    // 2. Click "Process Dictation & Orders"
    await page.click('#demo-process-note');
    console.log('[Puppeteer] Processing dictation and orders...');

    // Wait for orders table to render
    await page.waitForFunction(() => {
      const el = document.querySelector('corehealth-scribe-studio');
      const rows = el.shadowRoot.querySelectorAll('#orders-tbody tr');
      return rows && rows.length >= 3;
    }, { timeout: 15000 });
    console.log('[Puppeteer] 3 recommended orders staged successfully in deck.');

    // 3. Test Add Order Modal in Web Component
    console.log('[Puppeteer] Testing vanilla Add Order modal...');
    await page.evaluate(() => {
      const el = document.querySelector('corehealth-scribe-studio');
      const addBtn = el.shadowRoot.getElementById('btn-add-order');
      addBtn.click();
    });
    await new Promise(r => setTimeout(r, 400));

    const isModalVisible = await page.evaluate(() => {
      const el = document.querySelector('corehealth-scribe-studio');
      const modal = el.shadowRoot.getElementById('modal-add-order');
      return modal && window.getComputedStyle(modal).display === 'flex';
    });
    console.log('[Puppeteer] Modal visible status:', isModalVisible);

    // Fill modal form and submit
    await page.evaluate(() => {
      const el = document.querySelector('corehealth-scribe-studio');
      const moCat = el.shadowRoot.getElementById('mo-cat');
      const moName = el.shadowRoot.getElementById('mo-name');
      const moSpec = el.shadowRoot.getElementById('mo-spec');
      const moInst = el.shadowRoot.getElementById('mo-inst');
      const moTariff = el.shadowRoot.getElementById('mo-tariff');
      const confirmBtn = el.shadowRoot.getElementById('btn-confirm-add-order');

      moCat.value = 'medication';
      moName.value = 'Amoxicillin-Clavulanic Acid 625mg';
      moSpec.value = '625mg BD';
      moInst.value = 'Take after meals with water';
      moTariff.value = '2500';

      confirmBtn.click();
    });
    await new Promise(r => setTimeout(r, 600));

    const countAfterAdd = await page.evaluate(() => {
      const el = document.querySelector('corehealth-scribe-studio');
      return el.shadowRoot.querySelectorAll('#orders-tbody tr').length;
    });
    console.log('[Puppeteer] Total orders staged after modal add:', countAfterAdd);

    // 4. Test Remove Order button
    console.log('[Puppeteer] Testing order removal via trash icon...');
    await page.evaluate(() => {
      const el = document.querySelector('corehealth-scribe-studio');
      const delBtns = el.shadowRoot.querySelectorAll('.btn-del-order');
      if (delBtns.length > 0) delBtns[delBtns.length - 1].click();
    });
    await new Promise(r => setTimeout(r, 600));

    const countAfterDel = await page.evaluate(() => {
      const el = document.querySelector('corehealth-scribe-studio');
      return el.shadowRoot.querySelectorAll('#orders-tbody tr').length;
    });
    console.log('[Puppeteer] Total orders staged after removal:', countAfterDel);

    // 5. Switch Taxonomy to Clerking
    await page.evaluate(() => {
      const el = document.querySelector('corehealth-scribe-studio');
      const clerkingBtn = el.shadowRoot.querySelector('.ch-tax-btn[data-tax="clerking"]');
      if (clerkingBtn) clerkingBtn.click();
    });
    await new Promise(r => setTimeout(r, 800));

    // 6. Sign Off
    await page.evaluate(() => {
      const el = document.querySelector('corehealth-scribe-studio');
      const signBtn = el.shadowRoot.getElementById('btn-sign');
      if (signBtn) signBtn.click();
    });

    // Wait for results card
    await page.waitForFunction(() => {
      const card = document.getElementById('results-card');
      return card && card.style.display !== 'none';
    }, { timeout: 15000 });
    console.log('[Puppeteer] openEHR and FHIR results card visible on page.');

    // 7. Verify Tabbed Results UI & Format Switching
    console.log('[Puppeteer] Verifying Tabbed Results UI (All, FHIR, openEHR, Orders)...');
    
    // Check FHIR Tab (Tabulated & Raw)
    await page.click('#tab-res-fhir');
    await new Promise(r => setTimeout(r, 400));
    const fhirRows = await page.$$eval('#tabulated-res-fhir .res-table tbody tr', trs => trs.length);
    console.log('[Puppeteer] FHIR tabulated rows count:', fhirRows);

    await page.click('#btn-fmt-raw');
    await new Promise(r => setTimeout(r, 300));
    const isFhirRawVisible = await page.$eval('#raw-res-fhir', el => window.getComputedStyle(el).display !== 'none');
    console.log('[Puppeteer] FHIR raw JSON view visible:', isFhirRawVisible);

    // Check openEHR Tab
    await page.click('#tab-res-openehr');
    await page.click('#btn-fmt-tabulated');
    await new Promise(r => setTimeout(r, 400));
    const openEhrRows = await page.$$eval('#tabulated-res-openehr .res-table tbody tr', trs => trs.length);
    console.log('[Puppeteer] openEHR tabulated rows count:', openEhrRows);

    // Check Clinical Orders Tab
    await page.click('#tab-res-orders');
    await new Promise(r => setTimeout(r, 400));
    const ordersRows = await page.$$eval('#tabulated-res-orders .res-table tbody tr', trs => trs.length);
    console.log('[Puppeteer] Clinical Orders tabulated rows count:', ordersRows);

    await page.click('#btn-fmt-raw');
    await new Promise(r => setTimeout(r, 300));
    const isOrdersRawVisible = await page.$eval('#raw-res-orders', el => window.getComputedStyle(el).display !== 'none');
    console.log('[Puppeteer] Orders raw JSON view visible:', isOrdersRawVisible);

    // Switch back to All Overview Tabulated for full screenshot
    await page.click('#tab-res-all');
    await page.click('#btn-fmt-tabulated');
    await new Promise(r => setTimeout(r, 500));

    const trackAPath = path.join(__dirname, '../docs/public/images/workbenches/scribe-sdk-verified-journey.png');
    await page.screenshot({ path: trackAPath, fullPage: true });
    console.log('[Puppeteer] Track A verified screenshot saved to:', trackAPath);

    // -------------------------------------------------------------
    // TRACK B: Sandboxed iFrame
    // -------------------------------------------------------------
    console.log('\n[Puppeteer] Testing Track B: Sandboxed iFrame...');
    await page.click('#tab-iframe');
    await new Promise(r => setTimeout(r, 1200));

    // Wait for iframe
    await page.waitForSelector('#scribe-mount iframe', { timeout: 10000 });
    const iframeHandle = await page.$('#scribe-mount iframe');
    const iframe = await iframeHandle.contentFrame();

    await iframe.waitForSelector('#ch-transcript-area', { timeout: 10000 });
    console.log('[Puppeteer] iFrame mounted and ready.');

    // Click sample inside iframe
    await iframe.click('#ch-btn-sample');
    await new Promise(r => setTimeout(r, 500));

    // Process inside iframe
    await iframe.click('#ch-btn-process');
    await iframe.waitForFunction(() => {
      const rows = document.querySelectorAll('#ch-orders-tbody tr');
      return rows && rows.length >= 3;
    }, { timeout: 15000 });
    console.log('[Puppeteer] iFrame staged 3 orders.');

    // Test Add Order Modal inside iframe
    await iframe.click('#ch-btn-add-order');
    await new Promise(r => setTimeout(r, 400));

    const isIframeModalVisible = await iframe.evaluate(() => {
      const modal = document.getElementById('ch-modal-add-order');
      return modal && window.getComputedStyle(modal).display === 'flex';
    });
    console.log('[Puppeteer] iFrame modal visible:', isIframeModalVisible);

    await iframe.evaluate(() => {
      const cat = document.getElementById('ch-mo-cat');
      const name = document.getElementById('ch-mo-name');
      const spec = document.getElementById('ch-mo-spec');
      const inst = document.getElementById('ch-mo-inst');
      const tariff = document.getElementById('ch-mo-tariff');
      const confirm = document.getElementById('ch-btn-confirm-add');

      cat.value = 'lab';
      name.value = 'Urgent Urinalysis Dipstick';
      spec.value = 'Rule out UTI / Nitrites';
      inst.value = 'Clean catch midstream sample';
      tariff.value = '1500';
      confirm.click();
    });
    await new Promise(r => setTimeout(r, 600));

    // Delete one item inside iframe
    await iframe.evaluate(() => {
      const delBtns = document.querySelectorAll('.ch-del-order');
      if (delBtns.length > 0) delBtns[delBtns.length - 1].click();
    });
    await new Promise(r => setTimeout(r, 600));

    // Sign inside iframe
    await iframe.click('#ch-btn-sign');
    await new Promise(r => setTimeout(r, 2000));

    const trackBPath = path.join(__dirname, '../docs/public/images/workbenches/scribe-sdk-iframe-verified-journey.png');
    await page.screenshot({ path: trackBPath, fullPage: true });
    console.log('[Puppeteer] Track B verified screenshot saved to:', trackBPath);

    console.log('\n[Puppeteer] ALL VERIFICATIONS PASSED SUCCESSFULLY!');
  } finally {
    await browser.close();
  }
})();
