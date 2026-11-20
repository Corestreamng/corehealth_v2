const puppeteer = require('puppeteer-core');
const CHROME_PATH = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const APP_URL = 'http://127.0.0.1:8085';

async function inspectDom() {
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu']
  });

  const page = await browser.newPage();
  await page.evaluateOnNewDocument(() => {
    localStorage.setItem('flutter.onboarding_done', 'true');
  });

  await page.goto(APP_URL, { waitUntil: 'networkidle2' });
  await new Promise(r => setTimeout(r, 6000));

  const domInfo = await page.evaluate(() => {
    const inputs = Array.from(document.querySelectorAll('input, textarea, button, flt-semantics-placeholder')).map(el => ({
      tag: el.tagName,
      type: el.type,
      id: el.id,
      className: el.className,
      placeholder: el.placeholder,
      value: el.value,
      rect: el.getBoundingClientRect()
    }));
    return {
      bodyHtml: document.body.innerHTML.substring(0, 1000),
      inputs
    };
  });

  console.log('DOM info:', JSON.stringify(domInfo, null, 2));
  await browser.close();
}

inspectDom().catch(console.error);
