const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs');

const CHROME_PATH = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const APP_URL = 'http://127.0.0.1:8085';
const OUTPUT_DIR = path.resolve(__dirname, '../docs/public/images/native_tests');

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

async function run() {
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--window-size=1440,900']
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });

  page.on('console', msg => {
    const text = msg.text();
    if (text.includes('[ApiClient]') || text.includes('[Reception') || text.includes('[Doctor') || text.includes('[Nursing') || text.includes('[Maternity') || text.includes('[Billing') || text.includes('ERROR') || text.includes('Exception')) {
      console.log(`[Browser Console ${msg.type()}]`, text);
    }
  });

  await page.evaluateOnNewDocument(() => {
    localStorage.setItem('flutter.onboarding_done', 'true');
  });

  console.log('[1] Opening app...');
  await page.goto(APP_URL, { waitUntil: 'networkidle2' });
  await delay(6000);

  // Enable semantics
  await page.evaluate(() => {
    const el = document.querySelector('flt-semantics-placeholder');
    if (el) el.click();
  });
  await delay(1000);

  // Set server address
  const serverInput = await page.$('input[aria-label="Server Address"]');
  if (serverInput) {
    await serverInput.type('http://127.0.0.1:8000');
    await delay(300);
  }

  // Click Connect
  await page.evaluate(() => {
    const btns = Array.from(document.querySelectorAll('flt-semantics[role="button"], button'));
    btns[btns.length - 1]?.click();
  });
  await delay(6000);

  // Login
  console.log('[2] Signing in...');
  const emailInput = await page.$('input[aria-label="Email Address"]');
  const passwordInput = await page.$('input[aria-label="Password"]');
  if (emailInput && passwordInput) {
    await emailInput.type('sysadmin@mail.com');
    await delay(300);
    await passwordInput.type('123456');
    await delay(300);
  }
  await page.keyboard.press('Enter');
  console.log('[3] Waiting for AppShell (10s)...');
  await delay(10000);

  // Helper to click sidebar items by exact label or text
  async function clickSidebar(label) {
    console.log(`Navigating to ${label}...`);
    await page.evaluate(lbl => {
      const all = Array.from(document.querySelectorAll('flt-semantics, button, [role="button"]'));
      for (const el of all) {
        const text = (el.getAttribute('aria-label') || el.innerText || el.textContent || '').trim();
        if (text === lbl || text.startsWith(lbl)) {
          el.click();
          return;
        }
      }
    }, label);
    await delay(4000);
  }

  // 1. Doctors Workspace
  console.log('[4] Navigating to Doctors workspace...');
  // In the sidebar, Doctors is at x ≈ 90, y ≈ 955 (or we can click it via DOM)
  await clickSidebar('Doctors');
  // Also click "My Queues" if sub-item appears
  await clickSidebar('My Queues');
  await delay(3000);
  const docShot = path.join(OUTPUT_DIR, '07_doctor_desk.png');
  await page.screenshot({ path: docShot });
  console.log(`Saved ${docShot}`);

  // 2. Billing Workspace
  console.log('[5] Navigating to Billing workspace...');
  await clickSidebar('Billing');
  await clickSidebar('Cashier');
  await delay(3000);
  const billShot = path.join(OUTPUT_DIR, '08_billing_desk.png');
  await page.screenshot({ path: billShot });
  console.log(`Saved ${billShot}`);

  // 3. Nursing Workspace
  console.log('[6] Navigating to Nursing workspace...');
  await clickSidebar('Nursing');
  await clickSidebar('Ward');
  await delay(3000);
  const nurseShot = path.join(OUTPUT_DIR, '09_nursing_desk.png');
  await page.screenshot({ path: nurseShot });
  console.log(`Saved ${nurseShot}`);

  // 4. Maternity Workspace
  console.log('[7] Navigating to Maternity workspace...');
  await clickSidebar('Maternity');
  await clickSidebar('Maternity Desk');
  await delay(3000);
  const matShot = path.join(OUTPUT_DIR, '10_maternity_desk.png');
  await page.screenshot({ path: matShot });
  console.log(`Saved ${matShot}`);

  // 5. Tablet Form Factor
  console.log('[8] Testing Tablet Form Factor (900x1180)...');
  await page.setViewport({ width: 900, height: 1180 });
  await delay(2000);
  const tabletShot = path.join(OUTPUT_DIR, '11_tablet_form_factor.png');
  await page.screenshot({ path: tabletShot });
  console.log(`Saved ${tabletShot}`);

  // 6. Phone Form Factor
  console.log('[9] Testing Phone Form Factor (390x844)...');
  await page.setViewport({ width: 390, height: 844 });
  await delay(2000);
  const phoneShot = path.join(OUTPUT_DIR, '12_phone_form_factor.png');
  await page.screenshot({ path: phoneShot });
  console.log(`Saved ${phoneShot}`);

  await browser.close();
  console.log('✓ All workspace and responsive tests completed successfully!');
}

run().catch(console.error);

