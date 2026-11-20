const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs');

const CHROME_PATH = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const APP_URL = process.env.APP_URL || 'http://127.0.0.1:8085';
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

  // Pre-seed localStorage before navigating!
  await page.evaluateOnNewDocument(() => {
    localStorage.setItem('flutter.onboarding_done', 'true');
    localStorage.setItem('flutter.base_url', 'http://127.0.0.1:8000');
  });

  console.log(`[Test] Navigating with pre-seeded localStorage to ${APP_URL}...`);
  await page.goto(APP_URL, { waitUntil: 'networkidle2', timeout: 30000 });
  await delay(8000);

  const shot = path.join(OUTPUT_DIR, '02_after_preseed.png');
  await page.screenshot({ path: shot });
  console.log(`[Test] Screenshot saved to ${shot}`);

  await browser.close();
}

run().catch(console.error);
