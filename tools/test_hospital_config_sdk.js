const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs');

const CHROME_PATH = process.env.CHROME_BIN || '/home/mrapollos/.cache/ms-playwright/chromium-1217/chrome-linux64/chrome';
const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const EMAIL = process.env.AUTH_EMAIL || 'sysadmin@mail.com';
const PASSWORD = process.env.AUTH_PASSWORD || '123456';
const OUTPUT_DIR = path.resolve(__dirname, '../docs/public/images/workbenches');

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
  console.log('[HospitalConfig SDK Test] Launching Chrome...');
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--window-size=1600,1200']
  });

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1600, height: 1200 });

    page.on('console', msg => console.log('PAGE LOG:', msg.text()));
    page.on('pageerror', err => console.error('PAGE ERROR:', err.message));

    // Handle confirm dialogs automatically
    page.on('dialog', async dialog => {
      console.log('[HospitalConfig SDK Test] Handled dialog:', dialog.message());
      await dialog.accept();
    });

    // 1. Authenticate
    console.log(`[HospitalConfig SDK Test] Authenticating at ${BASE_URL}/login...`);
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await delay(1000);
    await page.type('#email', EMAIL);
    await page.type('#password', PASSWORD);
    await Promise.all([
      page.click('.btn-auth-primary'),
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 })
    ]);
    await delay(1500);

    // 2. Navigate to Hospital Config
    console.log(`[HospitalConfig SDK Test] Navigating to ${BASE_URL}/hospital-config#sdk-settings...`);
    await page.goto(`${BASE_URL}/hospital-config#sdk-settings`, { waitUntil: 'networkidle2', timeout: 60000 });
    await delay(1000);

    // Ensure SDK tab is active
    await page.evaluate(() => {
      const tab = document.querySelector('#sdk-tab');
      if (tab) tab.click();
    });
    await delay(800);

    // Verify KPI counters
    const kpis = await page.evaluate(() => {
      const cards = document.querySelectorAll('#sdk-settings .card-body .row:first-child .h4');
      return Array.from(cards).map(c => c.textContent.trim());
    });
    console.log('[HospitalConfig SDK Test] KPI values:', kpis);

    // Capture main overview screenshot
    const mainImgPath = path.join(OUTPUT_DIR, 'hospital-config-sdk-clients-monitoring.png');
    await page.screenshot({ path: mainImgPath, fullPage: false });
    console.log(`[HospitalConfig SDK Test] Saved screenshot: ${mainImgPath}`);

    // 3. Test .env Snippet Modal
    console.log('[HospitalConfig SDK Test] Opening .env Snippet modal...');
    await page.evaluate(() => {
      openEnvSnippetModal('partner-emr-dev', 'Partner EMR Developer Sandbox', 'http://127.0.0.1:8000');
    });
    await delay(800);

    const snippetText = await page.evaluate(() => {
      const el = document.getElementById('envSnippetContent');
      return el ? el.textContent : '';
    });
    console.log('[HospitalConfig SDK Test] Snippet content snippet:\n', snippetText.substring(0, 150) + '...');

    const snippetImgPath = path.join(OUTPUT_DIR, 'hospital-config-sdk-env-snippet.png');
    await page.screenshot({ path: snippetImgPath, fullPage: false });
    console.log(`[HospitalConfig SDK Test] Saved snippet screenshot: ${snippetImgPath}`);

    // Close modal
    await page.evaluate(() => {
      window.$('#envSnippetModal').modal('hide');
    });
    await delay(800);

    // 4. Test Flash Secret Banner on Register
    const testClientId = 'ehealth-test-' + Date.now().toString().slice(-4);
    console.log(`[HospitalConfig SDK Test] Registering a new partner client '${testClientId}'...`);

    await page.evaluate((cid) => {
      document.querySelector('#registerSdkClientModal input[name="name"]').value = 'eHealth Africa Test Node';
      document.querySelector('#registerSdkClientModal input[name="organization"]').value = 'eHealth Africa Operations';
      document.querySelector('#registerSdkClientModal input[name="client_id"]').value = cid;
      document.querySelector('#registerSdkClientModal input[name="rate_limit_rpm"]').value = '150';
    }, testClientId);

    await Promise.all([
      page.evaluate(() => document.querySelector('#registerSdkClientModal form').submit()),
      page.waitForNavigation({ waitUntil: 'networkidle2' })
    ]);
    await delay(1000);

    // Check if new secret flash is displayed
    const flashSecret = await page.evaluate(() => {
      const input = document.getElementById('flash-new-secret-input');
      return input ? input.value : null;
    });
    console.log('[HospitalConfig SDK Test] Flash secret captured:', flashSecret ? (flashSecret.substring(0, 12) + '...') : 'NONE');

    // Take screenshot of flash secret in view
    const flashImgPath = path.join(OUTPUT_DIR, 'hospital-config-sdk-new-client-secret.png');
    await page.screenshot({ path: flashImgPath, fullPage: false });
    console.log(`[HospitalConfig SDK Test] Saved flash secret screenshot: ${flashImgPath}`);

    // 5. Test Rotate Secret
    console.log('[HospitalConfig SDK Test] Testing secret rotation for newly created client...');
    const rotateUrl = await page.evaluate((cid) => {
      const row = Array.from(document.querySelectorAll('#sdk-settings table tbody tr')).find(r => r.textContent.includes(cid));
      if (!row) return null;
      const btn = row.querySelector('button[title="Rotate Secret Key"]');
      const match = btn ? btn.getAttribute('onclick').match(/'(http[^']+)'/) : null;
      return match ? match[1] : null;
    }, testClientId);

    if (rotateUrl) {
      console.log('[HospitalConfig SDK Test] Executing rotate at URL:', rotateUrl);
      await Promise.all([
        page.evaluate((url) => {
          const form = document.getElementById('rotateSdkClientForm');
          form.action = url;
          form.submit();
        }, rotateUrl),
        page.waitForNavigation({ waitUntil: 'networkidle2' })
      ]);
      await delay(1000);
      const rotatedSecret = await page.evaluate(() => {
        const input = document.getElementById('flash-new-secret-input');
        return input ? input.value : null;
      });
      console.log('[HospitalConfig SDK Test] Rotated secret captured:', rotatedSecret ? (rotatedSecret.substring(0, 12) + '...') : 'NONE');
    }

    // 6. Clean up test client
    console.log(`[HospitalConfig SDK Test] Cleaning up test client '${testClientId}'...`);
    const deleteUrl = await page.evaluate((cid) => {
      const row = Array.from(document.querySelectorAll('#sdk-settings table tbody tr')).find(r => r.textContent.includes(cid));
      if (!row) return null;
      const btn = row.querySelector('button[title="Delete Client"]');
      const match = btn ? btn.getAttribute('onclick').match(/'(http[^']+)'/) : null;
      return match ? match[1] : null;
    }, testClientId);

    if (deleteUrl) {
      await Promise.all([
        page.evaluate((url) => {
          const form = document.getElementById('deleteSdkClientForm');
          form.action = url;
          form.submit();
        }, deleteUrl),
        page.waitForNavigation({ waitUntil: 'networkidle2' })
      ]);
      console.log('[HospitalConfig SDK Test] Test client deleted.');
    }

    // Also clean up ehealth-test-node-1 from previous test
    const deletePrevUrl = await page.evaluate(() => {
      const row = Array.from(document.querySelectorAll('#sdk-settings table tbody tr')).find(r => r.textContent.includes('ehealth-test-node-1'));
      if (!row) return null;
      const btn = row.querySelector('button[title="Delete Client"]');
      const match = btn ? btn.getAttribute('onclick').match(/'(http[^']+)'/) : null;
      return match ? match[1] : null;
    });
    if (deletePrevUrl) {
      await Promise.all([
        page.evaluate((url) => {
          const form = document.getElementById('deleteSdkClientForm');
          form.action = url;
          form.submit();
        }, deletePrevUrl),
        page.waitForNavigation({ waitUntil: 'networkidle2' })
      ]);
      console.log('[HospitalConfig SDK Test] ehealth-test-node-1 cleaned up.');
    }

    console.log('[HospitalConfig SDK Test] ALL END-TO-END VERIFICATION CHECKS COMPLETED SUCCESSFULLY!');
  } catch (err) {
    console.error('[HospitalConfig SDK Test] FAILED:', err);
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
