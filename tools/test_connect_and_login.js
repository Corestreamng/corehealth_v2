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

  // Click on the Server Address field
  console.log('[Test] Clicking Server Address field (720, 500)...');
  await page.mouse.click(720, 500);
  await delay(500);

  console.log('[Test] Typing server address http://127.0.0.1:8000...');
  await page.keyboard.type('http://127.0.0.1:8000', { delay: 30 });
  await delay(1000);

  await page.screenshot({ path: path.join(OUTPUT_DIR, '03_typed_server_url.png') });

  console.log('[Test] Clicking Connect button (720, 605)...');
  await page.mouse.click(720, 605);
  await delay(5000);

  const loginShot = path.join(OUTPUT_DIR, '04_login_screen.png');
  await page.screenshot({ path: loginShot });
  console.log(`[Test] Saved login screenshot to ${loginShot}`);

  await browser.close();
}

run().catch(console.error);
