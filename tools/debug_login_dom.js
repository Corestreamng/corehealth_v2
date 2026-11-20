const puppeteer = require('puppeteer-core');
const CHROME_PATH = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const APP_URL = 'http://127.0.0.1:8085';

async function debugLoginDom() {
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
  await new Promise(r => setTimeout(r, 6000));

  // Enable semantics
  await page.evaluate(() => {
    const el = document.querySelector('flt-semantics-placeholder');
    if (el) el.click();
  });
  await new Promise(r => setTimeout(r, 1000));

  const serverInput = await page.$('input[aria-label="Server Address"]');
  if (serverInput) await serverInput.type('http://127.0.0.1:8000');

  // Click Connect button
  await page.evaluate(() => {
    const btns = Array.from(document.querySelectorAll('flt-semantics[role="button"], button'));
    btns[btns.length - 1]?.click();
  });

  console.log('Waiting 6s for login screen...');
  await new Promise(r => setTimeout(r, 6000));

  const emailInput = await page.$('input[aria-label="Email Address"]');
  const passwordInput = await page.$('input[aria-label="Password"]');
  if (emailInput && passwordInput) {
    await emailInput.type('sysadmin@mail.com');
    await passwordInput.type('123456');
  }

  await new Promise(r => setTimeout(r, 1000));

  const elements = await page.evaluate(() => {
    const all = Array.from(document.querySelectorAll('*'));
    return all.filter(el => {
      const label = el.getAttribute('aria-label') || '';
      const text = el.innerText || el.textContent || '';
      return label.includes('Sign') || label.includes('In') || text.includes('Sign In');
    }).map(el => ({
      tag: el.tagName,
      role: el.getAttribute('role'),
      ariaLabel: el.getAttribute('aria-label'),
      rect: el.getBoundingClientRect()
    }));
  });

  console.log('Matching elements after typing:', JSON.stringify(elements, null, 2));
  await browser.close();
}

debugLoginDom().catch(console.error);

