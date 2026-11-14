const puppeteer = require('puppeteer-core');
const path = require('path');

(async () => {
  console.log('[Puppeteer] Launching Chrome to capture results tabs...');
  const browser = await puppeteer.launch({
    executablePath: '/home/mrapollos/.cache/ms-playwright/chromium-1217/chrome-linux64/chrome',
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--use-fake-ui-for-media-stream']
  });

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1200, height: 1600 });

    await page.goto('http://localhost:3001', { waitUntil: 'networkidle2' });

    // Wait for Scribe studio custom element to mount
    await page.waitForFunction(() => {
      const el = document.querySelector('corehealth-scribe-studio');
      return el && el.shadowRoot && el.shadowRoot.getElementById('transcript-input');
    }, { timeout: 10000 });

    // Load sample and process note
    await page.click('#demo-load-sample');
    await new Promise(r => setTimeout(r, 600));
    await page.click('#demo-process-note');

    // Wait for orders to render
    await page.waitForFunction(() => {
      const el = document.querySelector('corehealth-scribe-studio');
      const rows = el.shadowRoot.querySelectorAll('#orders-tbody tr');
      return rows && rows.length >= 3;
    }, { timeout: 15000 });

    // Sign note
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

    // 1. Capture FHIR Tabulated View
    await page.click('#tab-res-fhir');
    await page.click('#btn-fmt-tabulated');
    await new Promise(r => setTimeout(r, 600));
    const fhirCard = await page.$('#results-card');
    await fhirCard.screenshot({ path: path.join(__dirname, '../docs/public/images/workbenches/scribe-results-fhir-tabulated.png') });

    // 2. Capture openEHR Tabulated View
    await page.click('#tab-res-openehr');
    await new Promise(r => setTimeout(r, 600));
    await fhirCard.screenshot({ path: path.join(__dirname, '../docs/public/images/workbenches/scribe-results-openehr-tabulated.png') });

    // 3. Capture Clinical Orders Tabulated View
    await page.click('#tab-res-orders');
    await new Promise(r => setTimeout(r, 600));
    await fhirCard.screenshot({ path: path.join(__dirname, '../docs/public/images/workbenches/scribe-results-orders-tabulated.png') });

    console.log('[Puppeteer] All 3 results tabs captured successfully.');
  } finally {
    await browser.close();
  }
})();

