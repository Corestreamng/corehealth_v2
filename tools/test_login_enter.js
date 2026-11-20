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
    if (!text.includes('WebGL') && !text.includes('Loaded font') && !text.includes('Asset')) {
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

  // Type server address
  const serverInput = await page.$('input[aria-label="Server Address"]');
  if (serverInput) {
    await serverInput.type('http://127.0.0.1:8000');
    await delay(300);
  }

  // Click Connect button
  await page.evaluate(() => {
    const btns = Array.from(document.querySelectorAll('flt-semantics[role="button"], button'));
    btns[btns.length - 1]?.click();
  });

  console.log('[2] Waiting 6s for login screen...');
  await delay(6000);

  // Type credentials
  const emailInput = await page.$('input[aria-label="Email Address"]');
  const passwordInput = await page.$('input[aria-label="Password"]');
  if (emailInput && passwordInput) {
    await emailInput.type('sysadmin@mail.com');
    await delay(300);
    await passwordInput.type('123456');
    await delay(300);
  }

  console.log('[3] Pressing Enter to submit login form...');
  await page.keyboard.press('Enter');

  console.log('[4] Waiting for login & AppShell navigation (12s)...');
  await delay(12000);

  const shot = path.join(OUTPUT_DIR, '06_dashboard_after_enter.png');
  await page.screenshot({ path: shot });
  console.log(`[5] Saved screenshot to ${shot}`);

  await browser.close();
}

run().catch(console.error);

