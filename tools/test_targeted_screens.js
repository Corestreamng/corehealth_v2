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
  await delay(1000);

  // Server & Login
  const serverInput = await page.$('input[aria-label="Server Address"]');
  if (serverInput) await serverInput.type('http://127.0.0.1:8000');
  await page.evaluate(() => {
    const btns = Array.from(document.querySelectorAll('flt-semantics[role="button"], button'));
    btns[btns.length - 1]?.click();
  });
  await delay(6000);

  const emailInput = await page.$('input[aria-label="Email Address"]');
  const passwordInput = await page.$('input[aria-label="Password"]');
  if (emailInput && passwordInput) {
    await emailInput.type('sysadmin@mail.com');
    await passwordInput.type('123456');
  }
  await page.keyboard.press('Enter');
  await delay(10000);

  async function clickText(textMatch) {
    console.log(`Clicking "${textMatch}"...`);
    await page.evaluate(tm => {
      const all = Array.from(document.querySelectorAll('flt-semantics, span, [role="button"]'));
      for (const el of all) {
        const t = (el.getAttribute('aria-label') || el.innerText || el.textContent || '').trim();
        if (t === tm || t.includes(tm)) {
          el.click();
          return;
        }
      }
    }, textMatch);
    await delay(4000);
  }

  // 1. Billing Desk
  await clickText('Billing');
  await delay(1000);
  await clickText('Billing Desk');
  await delay(3000);
  await page.screenshot({ path: path.join(OUTPUT_DIR, '08_billing_desk_desktop.png') });

  // 2. Nursing Desk
  await clickText('Nursing');
  await delay(1000);
  await clickText('Nursing Desk');
  await delay(3000);
  await page.screenshot({ path: path.join(OUTPUT_DIR, '09_nursing_desk_desktop.png') });

  // 3. Maternity Desk
  await clickText('Maternity');
  await delay(1000);
  await clickText('Maternity Desk');
  await delay(3000);
  await page.screenshot({ path: path.join(OUTPUT_DIR, '10_maternity_desk_desktop.png') });

  await browser.close();
  console.log('✓ Desktop screens captured!');
}

run().catch(console.error);

