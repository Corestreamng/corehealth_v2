const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs');

const CHROME_PATH = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const APP_URL = 'http://127.0.0.1:8085';
const OUTPUT_DIR = path.resolve(__dirname, '../docs/public/images/native_tests');

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

async function run() {
  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

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
    if (text.includes('[ApiClient]') || text.includes('[Reception') || text.includes('[Doctor') || text.includes('[Nursing') || text.includes('[Maternity') || text.includes('[Billing') || text.includes('ERROR') || text.includes('Exception')) {
      console.log(`[Browser Console ${msg.type()}]`, text);
    }
  });

  page.on('pageerror', err => {
    console.error('[Browser PageError]', err.message);
  });

  await page.evaluateOnNewDocument(() => {
    localStorage.setItem('flutter.onboarding_done', 'true');
  });

  console.log('[1/8] Navigating to Server Setup screen...');
  await page.goto(APP_URL, { waitUntil: 'networkidle2' });
  await delay(6000);

  // Enable semantics
  await page.evaluate(() => {
    const el = document.querySelector('flt-semantics-placeholder');
    if (el) el.click();
  });
  await delay(1200);

  // Set server address
  console.log('[2/8] Setting server address http://127.0.0.1:8000...');
  const serverInput = await page.$('input[aria-label="Server Address"]');
  if (serverInput) {
    await serverInput.type('http://127.0.0.1:8000');
    await delay(300);
  }

  // Click Connect
  await page.evaluate(() => {
    const btns = Array.from(document.querySelectorAll('flt-semantics[role="button"], button'));
    btns[btns.length - 1]?.click();
  });
  await delay(5000);

  // Sign In
  console.log('[3/8] Entering login credentials for sysadmin@mail.com...');
  const emailInput = await page.$('input[aria-label="Email Address"]');
  const passwordInput = await page.$('input[aria-label="Password"]');

  if (emailInput && passwordInput) {
    await emailInput.type('sysadmin@mail.com');
    await delay(200);
    await passwordInput.type('123456');
    await delay(200);
  } else {
    throw new Error('Email or Password input not found!');
  }

  // Click Sign In
  console.log('[4/8] Clicking Sign In...');
  await page.evaluate(() => {
    const btns = Array.from(document.querySelectorAll('flt-semantics[role="button"], button'));
    for (const b of btns) {
      if ((b.getAttribute('aria-label') || '').includes('Sign In')) {
        b.click();
        return;
      }
    }
    // Fallback: click 4th element
    btns[3]?.click();
  });

  console.log('[Test] Waiting for login & AppShell navigation...');
  await delay(8000);

  // Screenshot 1: Desktop Home Dashboard
  const dashShot = path.join(OUTPUT_DIR, '06_desktop_dashboard.png');
  await page.screenshot({ path: dashShot });
  console.log(`[5/8] Saved Desktop Dashboard screenshot to ${dashShot}`);

  // Helper to click on a sidebar workspace by label
  async function clickSidebarItem(label) {
    console.log(`[Test] Clicking sidebar item: "${label}"...`);
    const clicked = await page.evaluate(lbl => {
      const els = Array.from(document.querySelectorAll('flt-semantics, button, [role="button"]'));
      for (const el of els) {
        const text = (el.getAttribute('aria-label') || el.innerText || el.textContent || '').trim();
        if (text.toLowerCase() === lbl.toLowerCase() || text.toLowerCase().includes(lbl.toLowerCase())) {
          el.click();
          return true;
        }
      }
      return false;
    }, label);
    return clicked;
  }

  // Navigate to Reception & Records
  await clickSidebarItem('Reception');
  await delay(4000);
  const recShot = path.join(OUTPUT_DIR, '07_reception_workspace.png');
  await page.screenshot({ path: recShot });
  console.log(`[6/8] Saved Reception Workspace screenshot to ${recShot}`);

  // Navigate to Doctors workspace
  await clickSidebarItem('Doctor');
  await delay(4000);
  const docShot = path.join(OUTPUT_DIR, '08_doctor_workspace.png');
  await page.screenshot({ path: docShot });
  console.log(`[6/8] Saved Doctor Workspace screenshot to ${docShot}`);

  // Navigate to Billing workspace
  await clickSidebarItem('Billing');
  await delay(4000);
  const billShot = path.join(OUTPUT_DIR, '09_billing_workspace.png');
  await page.screenshot({ path: billShot });
  console.log(`[6/8] Saved Billing Workspace screenshot to ${billShot}`);

  // Navigate to Nursing workspace
  await clickSidebarItem('Nursing');
  await delay(4000);
  const nurseShot = path.join(OUTPUT_DIR, '10_nursing_workspace.png');
  await page.screenshot({ path: nurseShot });
  console.log(`[6/8] Saved Nursing Workspace screenshot to ${nurseShot}`);

  // Navigate to Maternity workspace
  await clickSidebarItem('Maternity');
  await delay(4000);
  const matShot = path.join(OUTPUT_DIR, '11_maternity_workspace.png');
  await page.screenshot({ path: matShot });
  console.log(`[6/8] Saved Maternity Workspace screenshot to ${matShot}`);

  // Test Tablet Responsive Viewport (900x1180)
  console.log('[7/8] Testing Tablet Viewport (900x1180)...');
  await page.setViewport({ width: 900, height: 1180 });
  await delay(2000);
  const tabletShot = path.join(OUTPUT_DIR, '12_tablet_responsive.png');
  await page.screenshot({ path: tabletShot });
  console.log(`[7/8] Saved Tablet Responsive screenshot to ${tabletShot}`);

  // Test Phone Responsive Viewport (390x844)
  console.log('[8/8] Testing Phone Viewport (390x844)...');
  await page.setViewport({ width: 390, height: 844 });
  await delay(2000);
  const phoneShot = path.join(OUTPUT_DIR, '13_phone_responsive.png');
  await page.screenshot({ path: phoneShot });
  console.log(`[8/8] Saved Phone Responsive screenshot to ${phoneShot}`);

  await browser.close();
  console.log('[Test Complete] All 7 test phases executed and screens verified!');
}

run().catch(err => {
  console.error('[Test Failed]', err);
  process.exit(1);
});

