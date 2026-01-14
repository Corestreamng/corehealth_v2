# My Transactions - Quick Test Guide

## Test Environment Setup
1. Ensure pharmacy workbench is accessible
2. Log in as a pharmacist user
3. Have some test transaction data in the database

---

## Quick Test Scenarios

### Scenario 1: Basic Load (30 seconds)
1. Open Pharmacy Workbench
2. Click "My Transactions" button in sidebar
3. Click "Today" preset button
4. **Expected:** Transactions for today should load immediately

### Scenario 2: Date Range (45 seconds)
1. Open My Transactions modal
2. Click "This Week" preset
3. **Expected:** All transactions from Sunday to today
4. Click "Last Month" preset
5. **Expected:** All transactions from previous month

### Scenario 3: Filters (1 minute)
1. Open My Transactions modal
2. Select "This Month" preset
3. Select "CASH" from Payment Type dropdown
4. Click "Load"
5. **Expected:** Only cash transactions displayed
6. Change to "POS" and click "Load"
7. **Expected:** Only POS transactions displayed

### Scenario 4: Charts (30 seconds)
1. Load any date range with multiple payment types
2. **Expected:** 
   - Pie chart showing payment distribution
   - Bar chart showing top 5 products
   - Both charts should be interactive

### Scenario 5: Excel Export (30 seconds)
1. Load transactions
2. Click "Excel" button
3. **Expected:** File downloads named `My_Transactions_[dates].xlsx`
4. Open file
5. **Expected:** Two sheets - Summary and Transactions

### Scenario 6: Print (30 seconds)
1. Load transactions
2. Click "Print" button
3. **Expected:** Print preview opens in new window
4. **Expected:** Date range and statistics visible
5. Close window without printing

### Scenario 7: Transaction Details (45 seconds)
1. Load transactions
2. Click eye icon on any transaction
3. **Expected:** Details modal opens with complete information
4. Click "Print Receipt"
5. **Expected:** Receipt preview opens
6. Close both modals

---

## Visual Checks

### Statistics Dashboard
- [ ] 4 metric cards display correctly
- [ ] Numbers format with thousand separators
- [ ] Currency symbol (₦) appears
- [ ] Cards have gradient background
- [ ] Cards have hover effect

### Payment Breakdown
- [ ] Cards show for each payment type
- [ ] Badges are color-coded correctly
- [ ] Transaction counts are accurate
- [ ] Amounts match table totals

### Charts
- [ ] Pie chart shows correct proportions
- [ ] Pie chart colors match payment type badges
- [ ] Bar chart shows top products
- [ ] Tooltips display when hovering
- [ ] Legends are readable

### Table
- [ ] All 12 columns visible
- [ ] Date formats as DD/MM/YYYY HH:MM
- [ ] Payment method badges are colored
- [ ] Reference numbers display in badge style
- [ ] Eye icon buttons work
- [ ] Hover effect on rows

---

## Error Scenarios

### No Data
1. Select future date range
2. Click "Load"
3. **Expected:** "No transactions found" message with icon

### Invalid Date Range
1. Set "To Date" before "From Date"
2. Click "Load"
3. **Expected:** Should handle gracefully or show warning

### Network Error
1. Disconnect internet (if testing locally, stop server)
2. Try to load transactions
3. **Expected:** Error message "Failed to load transactions"

---

## Browser Compatibility

Test in each browser:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Edge (latest)
- [ ] Safari (if Mac available)

---

## Mobile Responsiveness

Test on mobile viewport (375px width):
- [ ] Modal fits screen
- [ ] Date preset buttons stack properly
- [ ] Filter fields are usable
- [ ] Table scrolls horizontally
- [ ] Charts resize appropriately
- [ ] Buttons are thumb-friendly

---

## Performance Checks

### Load Time
- [ ] Transactions load in < 2 seconds for 100 records
- [ ] Charts render in < 1 second
- [ ] Modal opens instantly

### Memory
- [ ] No console errors
- [ ] Charts destroy properly when reloading
- [ ] No memory leaks when opening/closing modal multiple times

---

## Data Accuracy

### Statistics Verification
1. Load small dataset (e.g., 5 transactions)
2. Manually calculate:
   - Total count
   - Sum of amounts
   - Sum of discounts
   - Net amount (total - discounts)
3. **Expected:** Modal statistics match manual calculations

### Payment Breakdown Verification
1. Load dataset with multiple payment types
2. Count transactions by type manually
3. **Expected:** Breakdown cards match manual counts

### Chart Verification
1. **Pie Chart:** Sum of segments should = 100%
2. **Bar Chart:** Products should be ordered by amount (highest first)

---

## Edge Cases

### Large Dataset
1. Load "This Month" for busy pharmacy
2. **Expected:** Handles 500+ transactions smoothly
3. Check scroll behavior in table

### Single Transaction
1. Select date with only 1 transaction
2. **Expected:** All features work, charts display single segment/bar

### All Same Payment Type
1. Filter to show only CASH transactions
2. **Expected:** Pie chart shows single segment, breakdown shows only CASH

### Empty Product Name
1. If any transaction has no product
2. **Expected:** Shows "N/A" in table and details

### Null Bank
1. For CASH transactions (no bank)
2. **Expected:** Shows "-" in table and details

---

## User Actions Test

### Filter Combinations
- [ ] Date range + payment type
- [ ] Date range + bank
- [ ] Date range + payment type + bank
- [ ] Reset filters by clicking "Today"

### Modal Interactions
- [ ] Open and close multiple times
- [ ] Load different date ranges without closing
- [ ] Switch between presets quickly
- [ ] Manual date entry works

### Export After Filter
1. Apply filters
2. Export to Excel
3. **Expected:** Excel contains only filtered data

---

## Regression Testing

Ensure existing features still work:
- [ ] Dispense medications
- [ ] View prescription list
- [ ] Use other quick actions
- [ ] Navigate workbench tabs
- [ ] Other modals still open

---

## Security Checks

- [ ] Can only see own transactions (not other pharmacists')
- [ ] Date range cannot exceed reasonable limit (e.g., 1 year)
- [ ] No SQL errors in console
- [ ] No sensitive data exposed in network tab

---

## Accessibility

- [ ] Can tab through all controls
- [ ] Enter key works on buttons
- [ ] Screen reader can read statistics
- [ ] Color contrast meets WCAG AA
- [ ] Focus indicators visible

---

## Expected Results Summary

✅ **Success Criteria:**
- All date presets work correctly
- Filters combine properly
- Statistics calculate accurately
- Charts display and update
- Excel exports successfully
- Print generates proper layout
- Transaction details show complete info
- No console errors
- Responsive on all screen sizes
- Works in all major browsers

---

## Issue Reporting Template

If you find a bug, report with:
1. **What you did:** Steps to reproduce
2. **What you expected:** Desired behavior
3. **What happened:** Actual behavior
4. **Browser:** Name and version
5. **Screenshot:** If visual issue
6. **Console errors:** Copy any errors from console

---

## Test Sign-Off

Tester: _______________
Date: _______________
Browser: _______________
Device: _______________

Result: ⬜ Pass ⬜ Fail ⬜ Partial

Notes:
_________________________________
_________________________________
_________________________________
