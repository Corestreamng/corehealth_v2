const puppeteer = require('puppeteer-core');
const path = require('path');
const CHROME_PATH = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const APP_URL = 'http://127.0.0.1:8085';
const OUTPUT_DIR = path.resolve(__dirname, '../docs/public/images/native_tests');

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

async function testLoginClick() {
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--window-size=1440,900']
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });

  page.on('console', msg => {
    console.log(`[Browser Console ${msg.type()}]`, msg.text());
  });

  await page.evaluateOnNewDocument(() => {
    localStorage.setItem('flutter.onboarding_done', 'true');
  });

  await page.goto(APP_URL, { waitUntil: 'networkidle2' });
  await delay(6000);

  // Enable semantics
  await page.evaluate(() => {
    const el = document.querySelector('flt-semantics-placeholder');
    if (el) el.click();
  });
  await delay(1200);

  // Set server address
  const serverInput = await page.$('input[aria-label="Server Address"]');
  if (serverInput) {
    await serverInput.type('http://127.0.0.1:8000');
    await delay(300);
  }

  // Click Connect at (720, 605)
  await page.mouse.click(720, 605);
  await delay(4000);

  // Type credentials
  const emailInput = await page.$('input[aria-label="Email Address"]');
  const passwordInput = await page.$('input[aria-label="Password"]');
  if (emailInput && passwordInput) {
    await emailInput.type('sysadmin@mail.com');
    await delay(200);
    await passwordInput.type('123456');
    await delay(200);
  }

  console.log('Clicking Sign In button at (720, 560)...');
  await page.mouse.click(720, 560);

  console.log('Waiting 8s for login API response...');
  await delay(8000);

  const shot = path.join(OUTPUT_DIR, 'test_login_result.png');
  await page.screenshot({ path: shot });
  console.log(`Screenshot saved to ${shot}`);

  await browser.close();
}

testLoginClick().catch(console.error);

