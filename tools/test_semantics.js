const puppeteer = require('puppeteer-core');
const CHROME_PATH = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const APP_URL = 'http://127.0.0.1:8085';

async function testSemantics() {
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

  console.log('Clicking flt-semantics-placeholder...');
  await page.evaluate(() => {
    const el = document.querySelector('flt-semantics-placeholder');
    if (el) {
      el.click();
      el.focus();
    }
  });

  await new Promise(r => setTimeout(r, 2000));

  const inputs = await page.evaluate(() => {
    const all = Array.from(document.querySelectorAll('*')).filter(el => {
      const tag = el.tagName.toLowerCase();
      return tag.startsWith('flt-') || tag === 'input' || tag === 'textarea' || el.getAttribute('role');
    });
    return all.map(el => ({
      tag: el.tagName,
      role: el.getAttribute('role'),
      ariaLabel: el.getAttribute('aria-label'),
      value: el.value,
      rect: el.getBoundingClientRect()
    }));
  });

  console.log('Semantics tree nodes count:', inputs.length);
  console.log('Nodes with ariaLabel or role:', inputs.filter(n => n.ariaLabel || n.role));

  await browser.close();
}

testSemantics().catch(console.error);

