const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs');

const CHROME_PATH = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const APP_URL = process.env.APP_URL || 'http://127.0.0.1:8085';
const BACKEND_URL = process.env.BACKEND_URL || 'http://127.0.0.1:8000';
const OUTPUT_DIR = path.resolve(__dirname, '../docs/public/images/native_tests');

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

async function run() {
  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

  console.log(`[Test] Launching Chrome (${CHROME_PATH})...`);
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: 'new',
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-gpu',
      '--window-size=1440,900'
    ]
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });

  page.on('console', msg => {
    const text = msg.text();
    // Filter out verbose flutter asset spam
    if (!text.includes('canonical') && !text.includes('Asset') && !text.includes('Loaded font')) {
      console.log(`[Browser Console ${msg.type()}]`, text);
    }
  });

  page.on('pageerror', err => {
    console.error('[Browser PageError]', err.message);
  });

  console.log(`[Test] Navigating to ${APP_URL}...`);
  await page.goto(APP_URL, { waitUntil: 'networkidle2', timeout: 30000 });

  // Wait for Flutter Web engine to initialize
  console.log('[Test] Waiting for Flutter Web to render (up to 15s)...');
  await delay(8000);

  const initialShot = path.join(OUTPUT_DIR, '01_initial_screen.png');
  await page.screenshot({ path: initialShot });
  console.log(`[Test] Initial screenshot saved to ${initialShot}`);

  await browser.close();
  console.log('[Test] Browser closed successfully.');
}

run().catch(err => {
  console.error('[Test Failed]', err);
  process.exit(1);
});

