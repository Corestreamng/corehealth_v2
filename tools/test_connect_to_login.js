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
    if (!text.includes('canonical') && !text.includes('Asset') && !text.includes('Loaded font') && !text.includes('WebGL')) {
      console.log(`[Browser Console ${msg.type()}]`, text);
    }
  });

  await page.evaluateOnNewDocument(() => {
    localStorage.setItem('flutter.onboarding_done', 'true');
  });

  console.log('[Test] Navigating to Server Setup screen...');
  await page.goto(APP_URL, { waitUntil: 'networkidle2' });
  await delay(6000);

  // Enable semantics
  await page.evaluate(() => {
    const el = document.querySelector('flt-semantics-placeholder');
    if (el) el.click();
  });
  await delay(1500);

  // Find input[aria-label="Server Address"]
  console.log('[Test] Typing http://127.0.0.1:8000 into Server Address input...');
  const serverInput = await page.$('input[aria-label="Server Address"]');
  if (serverInput) {
    await serverInput.type('http://127.0.0.1:8000');
    await delay(500);
  } else {
    console.error('Could not find Server Address input!');
  }

  // Click the Connect button
  console.log('[Test] Clicking Connect button...');
  await page.evaluate(() => {
    const btns = Array.from(document.querySelectorAll('flt-semantics[role="button"], button'));
    for (const b of btns) {
      if ((b.getAttribute('aria-label') || '').includes('Connect') || b.textContent.includes('Connect') || b.innerText?.includes('Connect')) {
        b.click();
        return;
      }
    }
    // Fallback: click last button in form
    btns[btns.length - 1]?.click();
  });

  console.log('[Test] Waiting for verifyServer and navigation to Login...');
  await delay(6000);

  const loginShot = path.join(OUTPUT_DIR, '05_login_screen_success.png');
  await page.screenshot({ path: loginShot });
  console.log(`[Test] Saved login screenshot to ${loginShot}`);

  // Inspect semantics on the Login screen
  const loginNodes = await page.evaluate(() => {
    const inputs = Array.from(document.querySelectorAll('input, textarea, flt-semantics[role="button"], button')).map(el => ({
      tag: el.tagName,
      role: el.getAttribute('role'),
      ariaLabel: el.getAttribute('aria-label') || el.innerText || el.textContent,
      type: el.type
    }));
    return inputs;
  });
  console.log('[Test] Login screen interactive elements:', loginNodes);

  await browser.close();
}

run().catch(console.error);
