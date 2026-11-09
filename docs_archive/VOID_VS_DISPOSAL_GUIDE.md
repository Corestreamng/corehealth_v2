# Fixed Asset Void vs Disposal - IAS 16 Compliance Guide

## Quick Decision Tree

```
Has the asset been depreciated?
│
├─ NO → Use VOID
│   └─ Reason: Registration errors, duplicates, data entry mistakes
│
└─ YES → Use DISPOSE
    └─ Reason: Asset had economic activity, must preserve historical records
```

## VOID - For Registration Errors Only

### When to Use Void
✓ Asset was registered by mistake (duplicate entry)  
✓ Wrong asset details entered  
✓ **No depreciation has been recorded** (accumulated depreciation = 0)  
✓ No economic activity occurred with the asset  

### What Void Does
1. Reverses the original acquisition journal entry
2. Creates a reversal journal entry (opposite DR/CR)
3. Changes asset status to VOIDED
4. Net effect: Asset "disappears" from accounting records

### Example Journal Entries

**Original Acquisition:**
```
DR  Fixed Asset          ₦100,000
    CR  Bank                         ₦100,000
```

**Void (Reversal):**
```
DR  Bank                 ₦100,000
    CR  Fixed Asset                  ₦100,000
```

**Net Result:** Asset never existed in the books

### System Protection
- Void button only appears if `canBeVoided()` returns true
- System checks: `accumulated_depreciation == 0`
- Error message explains why void is blocked

---

## DISPOSE - For Assets with Economic Activity

### When to Use Dispose
✓ Asset has been depreciated (any amount)  
✓ Asset is being sold, scrapped, or retired  
✓ Asset is impaired or damaged  
✓ Asset reached end of useful life  

### What Dispose Does (IAS 16.67-72)
1. Removes asset cost from balance sheet
2. Removes accumulated depreciation from balance sheet
3. Recognizes proceeds (if any)
4. Calculates and recognizes gain/loss on disposal
5. **Preserves** all historical depreciation records

### Example Journal Entries

**Asset Details:**
- Cost: ₦100,000
- Accumulated Depreciation: ₦60,000
- Book Value: ₦40,000
- Sale Proceeds: ₦45,000

**Disposal Journal Entry:**
```
DR  Accumulated Depreciation    ₦60,000
DR  Bank (proceeds)              ₦45,000
    CR  Fixed Asset                          ₦100,000
    CR  Gain on Disposal                     ₦5,000
```

**Result:** 
- Asset and accumulated depreciation removed
- ₦5,000 gain recognized (₦45,000 proceeds - ₦40,000 book value)
- Historical depreciation expense records preserved

---

## Why This Matters (IAS 16 Compliance)

### Problem with Voiding Depreciated Assets
❌ **Erases depreciation history** - Past P&L statements become inaccurate  
❌ **Retroactive adjustment** - Changes historical financial data  
❌ **Audit trail broken** - Loss of asset lifecycle documentation  
❌ **Non-compliance** - Violates IAS 16.67-72 derecognition rules  

### Correct Approach (Disposal)
✓ **Preserves history** - All depreciation expense records remain  
✓ **Accurate financials** - Past P&L statements unchanged  
✓ **Full audit trail** - Complete asset lifecycle documented  
✓ **IAS 16 compliant** - Proper derecognition process  

---

## System Behavior

### Automatic Protection Layers

1. **Model Level (`canBeVoided()`)**
   ```php
   // Returns false if accumulated_depreciation > 0
   if ($this->accumulated_depreciation > 0) {
       return false;
   }
   ```

2. **Service Level**
   ```php
   // Throws exception if validation fails
   if (!$asset->canBeVoided()) {
       throw new Exception('Asset cannot be voided...');
   }
   ```

3. **Controller Level**
   ```php
   // Returns detailed error message
   "The asset has depreciation recorded (₦XXX.XX). 
    Per IAS 16, assets with depreciation must be disposed..."
   ```

4. **UI Level**
   - Void button only shown if `canBeVoided()` is true
   - Modal includes IAS 16 guidance
   - Clear instructions on when to use void vs dispose

### Observer Protection
- `FixedAssetObserver`: Skips JE creation for voided assets
- `DepreciationObserver`: Blocks JE creation for voided/disposed assets

---

## Common Scenarios

### Scenario 1: Duplicate Entry (Same Day)
**Situation:** Receptionist accidentally registered laptop twice  
**Depreciation:** None (just created)  
**Action:** VOID the duplicate  
**Reason:** Registration error, no economic activity  

### Scenario 2: Wrong Asset Details
**Situation:** Entered wrong serial number, want to correct  
**Depreciation:** None (just created)  
**Action:** VOID and create new with correct details  
**Alternative:** Just edit the existing asset  

### Scenario 3: Asset Sold After 2 Years
**Situation:** Company car sold for ₦500,000  
**Depreciation:** ₦200,000 accumulated over 2 years  
**Action:** DISPOSE (not void!)  
**Reason:** Asset has depreciation history  

### Scenario 4: Asset Scrapped
**Situation:** Old equipment broken beyond repair  
**Depreciation:** ₦150,000 accumulated  
**Action:** DISPOSE with ₦0 proceeds  
**Reason:** Asset has depreciation history  

### Scenario 5: Asset Lost/Stolen
**Situation:** Laptop stolen, insurance claim filed  
**Depreciation:** ₦30,000 accumulated  
**Action:** DISPOSE with insurance proceeds  
**Reason:** Asset has depreciation history  

---

## Error Messages Guide

### "Asset has depreciation recorded"
**Meaning:** Cannot void because depreciation has been recorded  
**Solution:** Use the Dispose function instead  
**Why:** IAS 16 requires disposal for assets with economic activity  

### "Asset is already voided"
**Meaning:** Asset was already voided previously  
**Solution:** No action needed, or check if wrong asset selected  

### "Acquisition JE cannot be reversed"
**Meaning:** The accounting period is closed or JE already reversed  
**Solution:** Contact accounting supervisor for period reopening  

---

## Testing

Run these test scripts to verify compliance:

1. **test_void_asset.php** - Tests complete void workflow
2. **test_ias16_compliance.php** - Demonstrates IAS 16 compliance
3. **test_voided_observers.php** - Tests observer protection

All tests verify that depreciated assets cannot be voided and must use disposal process.

---

## References

- **IAS 16.67-72:** Derecognition of Property, Plant and Equipment
- **IAS 16.50:** Depreciation expense recognition
- **IAS 16.73-79:** Disclosure requirements

---

## Support

For questions about void vs disposal decisions, contact:
- Accounting Manager
- Chief Financial Officer
- External Auditor (for IAS 16 interpretations)
