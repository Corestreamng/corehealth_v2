const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs');

const CHROME_PATH = process.env.CHROME_PATH || '/opt/google/chrome/google-chrome';
const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8000';
const OUTPUT_DIR = path.join(__dirname, '../docs/public/images/workbenches');

(async () => {
  console.log('--- Starting Classic Pages Parity & Dynamic Functionality Test ---');
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });

  // 1. Login
  console.log('[Step 1] Logging in...');
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
  await page.type('input[name=email]', 'sysadmin@mail.com');
  await page.type('input[name=password]', '123456');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2' }),
    page.click('button[type=submit]')
  ]);

  // 2. Test Classic Manual Batch Page
  console.log('[Step 2] Testing /inventory/store-workbench/manual-batch...');
  await page.goto(`${BASE_URL}/inventory/store-workbench/manual-batch`, { waitUntil: 'domcontentloaded' });
  await new Promise(r => setTimeout(r, 1000));

  // Check supplier options loaded directly
  const supplierCount = await page.evaluate(() => {
    return document.querySelectorAll('#supplier_id option').length;
  });
  console.log('Supplier options count in classic form:', supplierCount);
  if (supplierCount <= 1) {
    throw new Error('Suppliers failed to load in classic manual batch page!');
  }

  // Select store, supplier, set quantity and cost price
  await page.evaluate(() => {
    // Select first store
    const storeSel = document.getElementById('store_id');
    if (storeSel && storeSel.options.length > 1) {
      storeSel.selectedIndex = 1;
      $(storeSel).trigger('change');
    }

    // Select first supplier
    const supSel = document.getElementById('supplier_id');
    if (supSel && supSel.options.length > 1) {
      supSel.selectedIndex = 1;
      $(supSel).trigger('change');
    }

    // Enter quantity = 10, cost price = 2500
    const qtyInput = document.getElementById('packaging_qty');
    if (qtyInput) {
      qtyInput.value = 10;
      qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    const costInput = document.getElementById('cost_price');
    if (costInput) {
      costInput.value = '2500.00';
      costInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });

  await new Promise(r => setTimeout(r, 800));

  // Check GL preview card on manual batch page
  const glPreview = await page.evaluate(() => {
    return {
      drAcc: document.getElementById('manual-preview-dr-acc')?.textContent,
      drAmt: document.getElementById('manual-preview-dr-amt')?.textContent,
      crAcc: document.getElementById('manual-preview-cr-acc')?.textContent,
      crAmt: document.getElementById('manual-preview-cr-amt')?.textContent,
    };
  });
  console.log('Classic Manual Batch GL Preview (Credit):', glPreview);

  if (glPreview.drAmt !== '+₦25,000.00' || glPreview.crAmt !== '+₦25,000.00') {
    throw new Error(`GL Preview amount mismatch! Expected +₦25,000.00, got DR: ${glPreview.drAmt}, CR: ${glPreview.crAmt}`);
  }

  // Test Donation toggle
  await page.evaluate(() => {
    const donationCb = document.getElementById('manual_is_donation');
    donationCb.checked = true;
    $(donationCb).trigger('change');
  });
  await new Promise(r => setTimeout(r, 500));

  const donationGLPreview = await page.evaluate(() => {
    return {
      drAmt: document.getElementById('manual-preview-dr-amt')?.textContent,
      crAmt: document.getElementById('manual-preview-cr-amt')?.textContent,
      costVal: document.getElementById('cost_price')?.value,
    };
  });
  console.log('Classic Manual Batch Donation GL Preview:', donationGLPreview);
  if (donationGLPreview.drAmt !== '₦0.00' || donationGLPreview.costVal !== '0.00') {
    throw new Error('Donation toggle did not correctly set cost to 0.00 and GL preview to ₦0.00!');
  }

  // Untoggle donation and switch to Cash
  await page.evaluate(() => {
    const donationCb = document.getElementById('manual_is_donation');
    donationCb.checked = false;
    $(donationCb).trigger('change');

    const costInput = document.getElementById('cost_price');
    costInput.value = '2500.00';
    costInput.dispatchEvent(new Event('input', { bubbles: true }));

    const cashRadio = document.querySelector('input[name="acquisition_type"][value="cash"]');
    if (cashRadio) {
      cashRadio.checked = true;
      $(cashRadio).trigger('change');
    }
  });
  await new Promise(r => setTimeout(r, 500));

  const cashGLPreview = await page.evaluate(() => {
    return {
      crAcc: document.getElementById('manual-preview-cr-acc')?.textContent,
      crAmt: document.getElementById('manual-preview-cr-amt')?.textContent,
    };
  });
  console.log('Classic Manual Batch Cash GL Preview:', cashGLPreview);
  if (!cashGLPreview.crAcc.includes('1030') || cashGLPreview.crAmt !== '-₦25,000.00') {
    throw new Error('Cash acquisition type did not update GL preview correctly to 1030 Petty Cash!');
  }

  await page.evaluate(() => {
    document.getElementById('manual-journal-preview-card')?.scrollIntoView({ block: 'center' });
  });
  await new Promise(r => setTimeout(r, 400));
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'classic-manual-batch-preview.jpg'), quality: 92 });

  // 3. Test Classic Stock Adjustment Page
  console.log('\n[Step 3] Testing /inventory/store-workbench/batch/:id/adjust...');
  // Find a valid batch ID first
  const validBatchId = await page.evaluate(async () => {
    const res = await fetch('/inventory/store-workbench/ajax/store-batches?store_id=1');
    const data = await res.json();
    return data.batches && data.batches.length > 0 ? data.batches[0].id : null;
  });
  console.log('Testing with batch ID:', validBatchId);

  if (validBatchId) {
    await page.goto(`${BASE_URL}/inventory/store-workbench/batch/${validBatchId}/adjust`, { waitUntil: 'domcontentloaded' });
    await new Promise(r => setTimeout(r, 1000));

    // Test selectType('add')
    await page.evaluate(() => {
      selectType('add');
      const qtyInput = document.getElementById('qty');
      qtyInput.value = 5;
      qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await new Promise(r => setTimeout(r, 500));

    const addGLPreview = await page.evaluate(() => {
      return {
        drAcc: document.getElementById('adj-preview-dr-acc')?.textContent,
        drAmt: document.getElementById('adj-preview-dr-amt')?.textContent,
        crAcc: document.getElementById('adj-preview-cr-acc')?.textContent,
        crAmt: document.getElementById('adj-preview-cr-amt')?.textContent,
      };
    });
    console.log('Classic Adjustment (Add) GL Preview:', addGLPreview);
    if (!addGLPreview.crAcc.includes('4230')) {
      throw new Error('Add Stock adjustment did not credit Account 4230 Gain!');
    }

    // Test selectType('subtract')
    await page.evaluate(() => {
      selectType('subtract');
      const qtyInput = document.getElementById('qty');
      qtyInput.value = 3;
      qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await new Promise(r => setTimeout(r, 500));

    const subGLPreview = await page.evaluate(() => {
      return {
        drAcc: document.getElementById('adj-preview-dr-acc')?.textContent,
        drAmt: document.getElementById('adj-preview-dr-amt')?.textContent,
        crAcc: document.getElementById('adj-preview-cr-acc')?.textContent,
        crAmt: document.getElementById('adj-preview-cr-amt')?.textContent,
      };
    });
    console.log('Classic Adjustment (Subtract) GL Preview:', subGLPreview);
    if (!subGLPreview.drAcc.includes('5050')) {
      throw new Error('Subtract Stock adjustment did not debit Account 5050 Shrinkage!');
    }

    await page.evaluate(() => {
      document.getElementById('adj-journal-preview-card')?.scrollIntoView({ block: 'center' });
    });
    await new Promise(r => setTimeout(r, 400));
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'classic-adjustment-preview.jpg'), quality: 92 });
  }

  // 4. Test Product Batches Page Modals
  console.log('\n[Step 4] Testing /inventory/store-workbench/product/:id/batches...');
  await page.goto(`${BASE_URL}/inventory/store-workbench/product/1/batches`, { waitUntil: 'domcontentloaded' });
  await new Promise(r => setTimeout(r, 1000));

  // Test opening void modal with live preview
  await page.evaluate(() => {
    openVoidBatchModal(999, 'BATCH-TEST-VOID', 10, 5000);
  });
  await new Promise(r => setTimeout(r, 500));

  const voidPreview = await page.evaluate(() => {
    return {
      drAmt: document.getElementById('void-preview-dr-amt')?.textContent,
      crAmt: document.getElementById('void-preview-cr-amt')?.textContent,
    };
  });
  console.log('Product Batches Void Modal Preview:', voidPreview);
  if (voidPreview.drAmt !== '+₦5,000.00' || voidPreview.crAmt !== '-₦5,000.00') {
    throw new Error('Void modal preview amount mismatch!');
  }
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'product-batches-void-preview.jpg'), quality: 92 });

  // Test opening return modal with live preview
  await page.evaluate(() => {
    $('#modal-void-batch').modal('hide');
    openReturnBatchModal(999, 'BATCH-TEST-RET', 'Test Drug', 10, 500, 1, 'Prime Pharma');
  });
  await new Promise(r => setTimeout(r, 500));

  const returnPreview = await page.evaluate(() => {
    return {
      drAcc: document.getElementById('return-preview-dr-acc')?.textContent,
      drAmt: document.getElementById('return-preview-dr-amt')?.textContent,
      crAcc: document.getElementById('return-preview-cr-acc')?.textContent,
      crAmt: document.getElementById('return-preview-cr-amt')?.textContent,
    };
  });
  console.log('Product Batches Return Modal Preview:', returnPreview);
  if (!returnPreview.drAcc.includes('2110') || returnPreview.drAmt !== '+₦500.00') {
    throw new Error('Return modal preview amount mismatch!');
  }
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'product-batches-return-preview.jpg'), quality: 92 });

  await browser.close();
  console.log('\n=== All Classic Pages Parity & Dynamic Functionality Tests PASSED 100%! ===');
})();
