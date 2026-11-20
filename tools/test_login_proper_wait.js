const puppeteer = require('puppeteer-core');
const path = require('path');
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

  // Type server address
  console.log('[2] Setting server URL...');
  const serverInput = await page.waitForSelector('input[aria-label="Server Address"]', { timeout: 10000 });
  await serverInput.type('http://127.0.0.1:8000');
  await delay(300);

  // Click Connect
  console.log('[3] Clicking Connect...');
  await page.mouse.click(720, 605);

  // Wait for login screen Email Address input to mount!
  console.log('[4] Waiting for login screen to mount...');
  const emailInput = await page.waitForSelector('input[aria-label="Email Address"]', { timeout: 20000 });
  console.log('✓ Login screen mounted! Found Email Address input.');
  await delay(1000);

  const passwordInput = await page.$('input[aria-label="Password"]');
  console.log('[5] Typing credentials...');
  await emailInput.type('sysadmin@mail.com');
  await delay(300);
  await passwordInput.type('123456');
  await delay(500);

  console.log('[6] Clicking Sign In at (720, 560)...');
  await page.mouse.click(720, 560);

  console.log('[7] Waiting for authentication and AppShell (10s)...');
  await delay(10000);

  const shot = path.join(OUTPUT_DIR, '06_real_dashboard.png');
  await page.screenshot({ path: shot });
  console.log(`[8] Saved dashboard screenshot to ${shot}`);

  await browser.close();
}

run().catch(console.error);

