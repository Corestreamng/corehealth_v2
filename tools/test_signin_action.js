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
  await delay(1200);

  // Type server URL
  console.log('[2] Setting server URL...');
  const serverInput = await page.$('input[aria-label="Server Address"]');
  if (serverInput) {
    await serverInput.type('http://127.0.0.1:8000');
    await delay(300);
  }

  // Click Connect button
  console.log('[3] Clicking Connect button...');
  await page.evaluate(() => {
    const btns = Array.from(document.querySelectorAll('flt-semantics[role="button"], button'));
    btns[btns.length - 1]?.click();
  });

  console.log('[4] Waiting 6s for login screen...');
  await delay(6000);

  // Type credentials
  console.log('[5] Typing credentials...');
  const emailInput = await page.$('input[aria-label="Email Address"]');
  const passwordInput = await page.$('input[aria-label="Password"]');
  if (emailInput && passwordInput) {
    await emailInput.type('sysadmin@mail.com');
    await delay(300);
    await passwordInput.type('123456');
    await delay(500);
  } else {
    throw new Error('Email or password input not found on login screen!');
  }

  // Get Sign In button position
  const signInBox = await page.evaluate(() => {
    const btns = Array.from(document.querySelectorAll('flt-semantics[role="button"]'));
    for (const b of btns) {
      if ((b.getAttribute('aria-label') || '').includes('Sign In')) {
        const r = b.getBoundingClientRect();
        return { x: r.x + r.width / 2, y: r.y + r.height / 2, found: true };
      }
    }
    return { x: 720, y: 560, found: false };
  });

  console.log('[6] Sign In button coordinates:', signInBox);
  console.log('[7] Clicking Sign In button...');
  await page.mouse.click(signInBox.x, signInBox.y);
  await page.evaluate(() => {
    const btns = Array.from(document.querySelectorAll('flt-semantics[role="button"]'));
    for (const b of btns) {
      if ((b.getAttribute('aria-label') || '').includes('Sign In')) {
        b.click();
        return;
      }
    }
  });

  console.log('[8] Waiting for authentication response & navigation (12s)...');
  await delay(12000);

  const shot = path.join(OUTPUT_DIR, '06_authenticated_shell.png');
  await page.screenshot({ path: shot });
  console.log(`[9] Saved screenshot to ${shot}`);

  await browser.close();
}

run().catch(console.error);

