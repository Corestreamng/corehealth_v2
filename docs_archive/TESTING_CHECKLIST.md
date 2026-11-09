# Testing Checklist & Flow Guide

## Table of Contents
1. [Store/Inventory Management Flow](#1-storeinventory-management-flow)
2. [Purchase Order (PO) Flow](#2-purchase-order-po-flow)
3. [Requisition Flow](#3-requisition-flow)
4. [Import/Export Data Flow](#4-importexport-data-flow)

---

## 1. Store/Inventory Management Flow

### Pre-requisites
- [ ] Admin/Superadmin account access
- [ ] At least one Store created (e.g., "Main Pharmacy", "Ward A Store")
- [ ] Product categories exist
- [ ] Products with prices configured

### Test Story: Setting Up a New Store

#### 1.1 Create Store
**Sidebar Navigation:** `Inventory Management` → `Stores`

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Inventory Management` → `Stores` | Store list page displays | ☐ |
| 2 | Click "Add New Store" button (top right) | Store creation form opens | ☐ |
| 3 | Fill in: Store Name, Location, Store Type | Fields accept input | ☐ |
| 4 | Select Store Manager (optional) | Dropdown shows staff list | ☐ |
| 5 | Toggle "Is Active" on | Toggle switches | ☐ |
| 6 | Click Save | Success message, store appears in list | ☐ |

#### 1.2 View Store Dashboard/Workbench
**Sidebar Navigation:** `Inventory Management` → `Store Workbench`

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Inventory Management` → `Store Workbench` | Workbench page loads | ☐ |
| 2 | Select store from dropdown (top of page) | Store stats update | ☐ |
| 3 | View "Low Stock Items" card | Shows items below reorder level | ☐ |
| 4 | View "Recent Transactions" card | Shows recent stock movements | ☐ |
| 5 | View "Expiring Soon" card | Shows batches expiring within 90 days | ☐ |

#### 1.3 Manual Batch Entry
**Sidebar Navigation:** `Inventory Management` → `Store Workbench` → Click "Add Stock Batch"

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Inventory Management` → `Store Workbench` | Workbench loads | ☐ |
| 2 | Click "Add Stock Batch" button | Batch form opens | ☐ |
| 3 | Select Product from dropdown | Product selected, shows current stock | ☐ |
| 4 | Enter Quantity (e.g., 100) | Field accepts number | ☐ |
| 5 | Enter Cost Price | Field accepts decimal | ☐ |
| 6 | Enter Batch Number (optional) | Field accepts text | ☐ |
| 7 | Enter Expiry Date (future date) | Date picker works | ☐ |
| 8 | Add Notes (optional) | Text area accepts input | ☐ |
| 9 | Click Save | Success message | ☐ |
| 10 | Verify store stock increased | Current quantity reflects new batch | ☐ |
| 11 | Check batch appears in batch list | New batch shows with details | ☐ |

#### 1.4 Stock Transfer Between Stores
**Sidebar Navigation:** `Inventory Management` → `Stock Transfer`

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Inventory Management` → `Stock Transfer` | Transfer form opens | ☐ |
| 2 | Select Source Store | Store with stock selected | ☐ |
| 3 | Select Destination Store | Different store selected | ☐ |
| 4 | Select Product | Product with available batches | ☐ |
| 5 | Select specific Batch(es) | Batches with available qty shown | ☐ |
| 6 | Enter Transfer Quantity | ≤ available quantity | ☐ |
| 7 | Add Transfer Notes | Optional notes field | ☐ |
| 8 | Click Submit Transfer | Confirmation prompt | ☐ |
| 9 | Confirm Transfer | Success message | ☐ |
| 10 | Verify source store decreased | Quantity reduced | ☐ |
| 11 | Verify destination store increased | Quantity increased | ☐ |
| 12 | Check transaction log | Transfer recorded | ☐ |

---

## 2. Purchase Order (PO) Flow

### Pre-requisites
- [ ] Supplier(s) configured in system
- [ ] Products exist with reorder levels set
- [ ] User has PO creation permissions

### Test Story: Complete PO Lifecycle

#### 2.1 Create Purchase Order
**Sidebar Navigation:** `Inventory Management` → `Purchase Orders`

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Inventory Management` → `Purchase Orders` | PO list page displays | ☐ |
| 2 | Click "Create New PO" button (top right) | PO creation form opens | ☐ |
| 3 | Select Supplier from dropdown | Supplier dropdown populated | ☐ |
| 4 | Select Destination Store | Store for receiving goods | ☐ |
| 5 | Set Expected Delivery Date | Future date selected | ☐ |
| 6 | Add Notes (optional) | Text area accepts input | ☐ |

#### 2.2 Add Items to PO
**Sidebar Navigation:** (Continue from Create PO form)

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Click "Add Item" button in PO form | Item row appears | ☐ |
| 2 | Search/Select Product | Product autocomplete works | ☐ |
| 3 | Enter Order Quantity | Numeric input accepted | ☐ |
| 4 | Enter Unit Cost | Cost per unit | ☐ |
| 5 | View calculated Line Total | Qty × Unit Cost | ☐ |
| 6 | Add multiple items (repeat steps 1-4) | Each item adds a new row | ☐ |
| 7 | View PO Total | Sum of all line totals | ☐ |
| 8 | Remove an item (click X) | Item row removed, total updated | ☐ |

#### 2.3 Submit PO for Approval
**Sidebar Navigation:** (Continue from PO form or `Inventory Management` → `Purchase Orders` → Select PO)

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Review all items in PO | All details correct | ☐ |
| 2 | Click "Submit for Approval" button | Confirmation prompt | ☐ |
| 3 | Confirm submission | PO status → "Pending Approval" | ☐ |
| 4 | PO becomes read-only | Cannot edit submitted PO | ☐ |

#### 2.4 Approve Purchase Order (Manager/Admin)
**Sidebar Navigation:** `Inventory Management` → `Purchase Orders` → Filter by "Pending Approval"

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Login as Approver (Manager/Admin account) | Logged in with approval permissions | ☐ |
| 2 | Sidebar: `Inventory Management` → `Purchase Orders` | PO list displays | ☐ |
| 3 | Filter/Tab: "Pending Approval" | Pending POs listed | ☐ |
| 4 | Click on pending PO row | PO details display | ☐ |
| 5 | Review items and totals | All information visible | ☐ |
| 6 | Click "Approve" button | Approval confirmation | ☐ |
| 7 | (Alt) Click "Reject" and enter reason | Rejection with notes | ☐ |
| 8 | PO status updates | Status → "Approved" | ☐ |

#### 2.5 Receive Goods Against PO
**Sidebar Navigation:** `Inventory Management` → `Goods Receipt` OR `Purchase Orders` → Select Approved PO → "Receive"

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Inventory Management` → `Goods Receipt` | GRN form opens | ☐ |
| 2 | Select approved PO from dropdown | PO items populate automatically | ☐ |
| 3 | For each item: Enter Received Qty | Can be ≤ ordered qty | ☐ |
| 4 | Enter Batch Number for each item | For tracking | ☐ |
| 5 | Enter Expiry Date for each item | For perishables | ☐ |
| 6 | Note any damaged/rejected items | Discrepancy tracking | ☐ |
| 7 | Click "Complete Reception" | GRN generated | ☐ |
| 8 | Verify stock increased in store | Store stock updated | ☐ |
| 9 | Verify batches created | New batches in system | ☐ |
| 10 | PO status updates | → "Received" or "Partially Received" | ☐ |

#### 2.6 Partial Receiving Scenario
**Sidebar Navigation:** `Inventory Management` → `Goods Receipt`

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Receive only 50% of ordered qty | Partial receipt processed | ☐ |
| 2 | PO status → "Partially Received" | Status reflects partial | ☐ |
| 3 | Return later: `Inventory Management` → `Purchase Orders` | Select same PO | ☐ |
| 4 | Click "Receive Remaining" | PO still accessible for more receipts | ☐ |
| 5 | Receive remaining items | Complete the order | ☐ |
| 6 | PO status → "Received" | Fully received | ☐ |

---

## 3. Requisition Flow

### Pre-requisites
- [ ] Multiple stores exist
- [ ] Stock available in source store
- [ ] Requesting user has requisition permissions

### Test Story: Internal Requisition Lifecycle

#### 3.1 Create Requisition Request
**Sidebar Navigation:** `Inventory Management` → `Requisitions`

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Inventory Management` → `Requisitions` | Requisition list page | ☐ |
| 2 | Click "New Requisition" button (top right) | Requisition form opens | ☐ |
| 3 | Select Requesting Store (your store/department) | Dropdown selection | ☐ |
| 4 | Select Source Store (store with stock) | Dropdown selection | ☐ |
| 5 | Set Required Date | When items needed | ☐ |
| 6 | Add Priority Level | Normal/Urgent/Critical | ☐ |
| 7 | Add Justification Notes | Reason for request | ☐ |

#### 3.2 Add Items to Requisition
**Sidebar Navigation:** (Continue from Requisition form)

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Click "Add Item" button | Item row appears | ☐ |
| 2 | Search Product in autocomplete field | Autocomplete from catalog | ☐ |
| 3 | View Available Stock column | Shows source store qty | ☐ |
| 4 | Enter Requested Quantity | Numeric input | ☐ |
| 5 | System warns if qty > available | Warning displayed | ☐ |
| 6 | Add multiple items (repeat) | Multiple rows added | ☐ |
| 7 | Click "Submit Requisition" | Status → "Pending" | ☐ |

#### 3.3 Approve Requisition (Store Manager)
**Sidebar Navigation:** `Inventory Management` → `Requisitions` → Filter "Pending"

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Login as Source Store Manager | Appropriate permissions | ☐ |
| 2 | Sidebar: `Inventory Management` → `Requisitions` | Requisition list | ☐ |
| 3 | Filter/Tab: "Pending" or "Awaiting My Approval" | Pending requests shown | ☐ |
| 4 | Click on requisition row | Details display | ☐ |
| 5 | For each item: Approve/Partial/Reject | Can approve less than requested | ☐ |
| 6 | Add approval notes | Optional comments | ☐ |
| 7 | Click "Approve" button | Status → "Approved" | ☐ |
| 8 | (Alt) Click "Reject" and enter reason | Status → "Rejected" | ☐ |

#### 3.4 Fulfill Requisition
**Sidebar Navigation:** `Inventory Management` → `Requisitions` → Select Approved Requisition

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Inventory Management` → `Requisitions` | Requisition list | ☐ |
| 2 | Filter: "Approved" | Approved requisitions shown | ☐ |
| 3 | Click on approved requisition | Requisition details | ☐ |
| 4 | Click "Fulfill" button | Fulfillment form opens | ☐ |
| 5 | For each item: Select Batch(es) | FEFO/FIFO selection | ☐ |
| 6 | Enter Fulfilled Quantity | ≤ approved qty | ☐ |
| 7 | Click "Complete Fulfillment" | Stock transfer initiated | ☐ |
| 8 | Verify source store decreased | Stock reduced | ☐ |
| 9 | Verify destination store increased | Stock added | ☐ |
| 10 | Requisition status → "Fulfilled" | Complete | ☐ |

#### 3.5 Requisition Rejection Flow
**Sidebar Navigation:** `Inventory Management` → `Requisitions`

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Create requisition (steps 3.1-3.2) | New request | ☐ |
| 2 | Login as Manager, reject with reason | "Out of stock" or other | ☐ |
| 3 | Requester receives notification | Notification/alert shown | ☐ |
| 4 | Sidebar: `Inventory Management` → `Requisitions` | View as requester | ☐ |
| 5 | Filter: "Rejected" or "My Requisitions" | Rejected requisition listed | ☐ |
| 6 | Click to view rejection reason | Notes visible | ☐ |

---

## 4. Import/Export Data Flow

### Pre-requisites
- [ ] Admin access to Import/Export module
- [ ] Sample CSV files ready (or download templates)
- [ ] Target categories/stores exist (or will be auto-created)

### Test Story: Product Import

#### 4.1 Download Template
**Sidebar Navigation:** `Administration` → `Data Import/Export`

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Administration` → `Data Import/Export` | Import/Export page loads | ☐ |
| 2 | Click "Products" tab | Products section shown | ☐ |
| 3 | Click "Download Template" button | CSV file downloads | ☐ |
| 4 | Open template in Excel/Notepad | Headers visible | ☐ |
| 5 | Verify headers match documentation | Correct columns present | ☐ |

#### 4.2 Prepare Import Data
**Action:** Offline - Edit downloaded CSV template

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Fill in required fields: | | |
|    | - product_name | "Test Product ABC" | ☐ |
|    | - product_code | "TEST-001" (unique) | ☐ |
|    | - category_name | "Test Category" | ☐ |
|    | - cost_price | 50.00 | ☐ |
|    | - sale_price | 100.00 | ☐ |
| 2 | Add optional fields: | | |
|    | - reorder_level | 10 | ☐ |
|    | - initial_quantity | 100 | ☐ |
|    | - store_name | "Main Pharmacy" | ☐ |
|    | - is_active | 1 | ☐ |
| 3 | Add 5-10 test rows | Multiple products | ☐ |
| 4 | Include edge cases: | | |
|    | - Duplicate product_code | Should be skipped | ☐ |
|    | - Missing required field | Should error | ☐ |
|    | - New category name | Should auto-create | ☐ |
| 5 | Save as CSV (UTF-8 encoding) | File saved | ☐ |

#### 4.3 Execute Product Import
**Sidebar Navigation:** `Administration` → `Data Import/Export` → Products Tab

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Administration` → `Data Import/Export` | Page loads | ☐ |
| 2 | Click "Products" tab | Products section shown | ☐ |
| 3 | Click "Choose File" in Import section | File browser opens | ☐ |
| 4 | Select prepared CSV file | File selected, name shown | ☐ |
| 5 | Select Default Store (optional dropdown) | Dropdown selection | ☐ |
| 6 | Click "Import Products" button | Processing starts | ☐ |
| 7 | Wait for completion | Progress/loading indicator | ☐ |
| 8 | View success message | "X products imported" | ☐ |
| 9 | View error details (if any) | Skipped rows listed with reasons | ☐ |
| 10 | Sidebar: `Inventory Management` → `Products` | Navigate to verify | ☐ |
| 11 | Search for imported products | Products exist | ☐ |
| 12 | Click product to view details | Price, stock, category correct | ☐ |
| 13 | Sidebar: `Inventory Management` → `Categories` | Check auto-created category | ☐ |

#### 4.4 Product Import Validation Tests
**Sidebar Navigation:** `Administration` → `Data Import/Export` → Products Tab

| Test Case | Expected Behavior | Status |
|-----------|-------------------|--------|
| Empty CSV | Error: "File is empty" | ☐ |
| Wrong file type (.xlsx) | Error: "Invalid file type" | ☐ |
| Missing product_name | Row skipped, error logged | ☐ |
| Missing product_code | Row skipped, error logged | ☐ |
| Duplicate product_code | Row skipped, "already exists" | ☐ |
| Invalid cost_price (text) | Row skipped or 0 used | ☐ |
| Non-existent store_name | Store field ignored | ☐ |
| Very large file (1000+ rows) | Completes without timeout | ☐ |

### Test Story: Service Import

#### 4.5 Service Import Flow
**Sidebar Navigation:** `Administration` → `Data Import/Export` → Services Tab

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Administration` → `Data Import/Export` | Page loads | ☐ |
| 2 | Click "Services" tab | Services section shown | ☐ |
| 3 | Click "Download Template" | CSV downloads | ☐ |
| 4 | Prepare test data in CSV: | | |
|    | - service_name | "Test Lab Service" | ☐ |
|    | - service_code | "LAB-TEST-001" | ☐ |
|    | - category_name | "Laboratory - Chemistry" | ☐ |
|    | - price | 5000.00 | ☐ |
|    | - cost_price | 2000.00 | ☐ |
| 5 | Return to Import/Export, Services tab | Back on page | ☐ |
| 6 | Choose file and click "Import Services" | Processing | ☐ |
| 7 | View success message | "X services imported" | ☐ |
| 8 | Sidebar: `Services` → `Manage Services` | Navigate to verify | ☐ |
| 9 | Search for imported services | New services exist | ☐ |
| 10 | Verify ServicePrice created | Price record exists | ☐ |

### Test Story: Staff Import

#### 4.6 Staff Import Flow
**Sidebar Navigation:** `Administration` → `Data Import/Export` → Staff Tab

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Administration` → `Data Import/Export` | Page loads | ☐ |
| 2 | Click "Staff" tab | Staff section shown | ☐ |
| 3 | Click "Download Template" | CSV downloads | ☐ |
| 4 | Prepare test data in CSV: | | |
|    | - surname | "TestStaff" | ☐ |
|    | - firstname | "User" | ☐ |
|    | - email | "teststaff@test.com" (unique) | ☐ |
|    | - role | "NURSE" (must exist in system) | ☐ |
| 5 | Return to Import/Export, Staff tab | Back on page | ☐ |
| 6 | Enter Default Password | "TempPass123" | ☐ |
| 7 | Choose file and click "Import Staff" | Processing | ☐ |
| 8 | View success message | "X staff imported" | ☐ |
| 9 | Sidebar: `Administration` → `Users` | Navigate to verify | ☐ |
| 10 | Search for new user email | User account created | ☐ |
| 11 | Sidebar: `Administration` → `Staff` | Check staff list | ☐ |
| 12 | Find staff profile | Staff profile exists | ☐ |
| 13 | Verify role assigned | User has correct role | ☐ |
| 14 | Logout, login with new account | Login works with default password | ☐ |

#### 4.7 Staff Import Validation Tests
**Sidebar Navigation:** `Administration` → `Data Import/Export` → Staff Tab

| Test Case | Expected Behavior | Status |
|-----------|-------------------|--------|
| Duplicate email | Row skipped, "email exists" | ☐ |
| Invalid role name | Row skipped, "role not found" | ☐ |
| Missing email | Row skipped, "missing required" | ☐ |
| Missing surname | Row skipped, "missing required" | ☐ |
| Invalid specialization | Specialization ignored (null) | ☐ |

### Test Story: Patient Import

#### 4.8 Patient Import Flow
**Sidebar Navigation:** `Administration` → `Data Import/Export` → Patients Tab

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Administration` → `Data Import/Export` | Page loads | ☐ |
| 2 | Click "Patients" tab | Patients section shown | ☐ |
| 3 | Click "Download Template" | CSV downloads | ☐ |
| 4 | Prepare test data in CSV: | | |
|    | - surname | "TestPatient" | ☐ |
|    | - firstname | "Import" | ☐ |
|    | - gender | "Male" | ☐ |
|    | - dob | "1990-01-15" | ☐ |
| 5 | Add optional HMO data: | | |
|    | - hmo_name | "NHIS" (must exist) | ☐ |
|    | - hmo_no | "12345678" | ☐ |
| 6 | Add allergies (comma-separated): | | |
|    | - allergies | "Penicillin,Sulfa" | ☐ |
| 7 | Return to Import/Export, Patients tab | Back on page | ☐ |
| 8 | Choose file and click "Import Patients" | Processing | ☐ |
| 9 | View success message | "X patients imported" | ☐ |
| 10 | Sidebar: `Reception` → `Patient List` | Navigate to verify | ☐ |
| 11 | Search for imported patient | Patient record found | ☐ |
| 12 | Verify file_no generated | Unique file number assigned | ☐ |
| 13 | Click patient to view details | Open patient profile | ☐ |
| 14 | Verify allergies as array | Properly parsed and displayed | ☐ |
| 15 | Verify HMO linked | hmo_id populated if HMO matched | ☐ |

### Test Story: Data Export

#### 4.9 Export Products
**Sidebar Navigation:** `Administration` → `Data Import/Export` → Products Tab → Export Section

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Administration` → `Data Import/Export` | Page loads | ☐ |
| 2 | Click "Products" tab | Products section shown | ☐ |
| 3 | Scroll to Export section | Export options visible | ☐ |
| 4 | (Optional) Select category filter | Dropdown selection | ☐ |
| 5 | Click "Export to CSV" button | Download starts | ☐ |
| 6 | Open downloaded file | Valid CSV with headers | ☐ |
| 7 | Verify data columns | All expected fields present | ☐ |
| 8 | Verify data accuracy | Matches database records | ☐ |

#### 4.10 Export Services
**Sidebar Navigation:** `Administration` → `Data Import/Export` → Services Tab → Export Section

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Administration` → `Data Import/Export` | Page loads | ☐ |
| 2 | Click "Services" tab | Services section shown | ☐ |
| 3 | Scroll to Export section | Export options visible | ☐ |
| 4 | (Optional) Select category filter | Dropdown selection | ☐ |
| 5 | Click "Export to CSV" button | Download starts | ☐ |
| 6 | Verify downloaded data | Accurate service records | ☐ |

#### 4.11 Export Staff
**Sidebar Navigation:** `Administration` → `Data Import/Export` → Staff Tab → Export Section

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Administration` → `Data Import/Export` | Page loads | ☐ |
| 2 | Click "Staff" tab | Staff section shown | ☐ |
| 3 | Scroll to Export section | Export options visible | ☐ |
| 4 | (Optional) Select role filter | Dropdown selection | ☐ |
| 5 | Click "Export to CSV" button | Download starts | ☐ |
| 6 | Verify downloaded data | Staff with roles listed | ☐ |
| 7 | Verify NO passwords exported | Security check - no password column | ☐ |

#### 4.12 Export Patients
**Sidebar Navigation:** `Administration` → `Data Import/Export` → Patients Tab → Export Section

| Step | Action | Expected Result | Status |
|------|--------|-----------------|--------|
| 1 | Sidebar: `Administration` → `Data Import/Export` | Page loads | ☐ |
| 2 | Click "Patients" tab | Patients section shown | ☐ |
| 3 | Scroll to Export section | Export options visible | ☐ |
| 4 | (Optional) Select HMO filter | Dropdown selection | ☐ |
| 5 | Click "Export to CSV" button | Download starts | ☐ |
| 6 | Verify downloaded data | Patient records present | ☐ |
| 7 | Verify allergies formatted | Comma-separated string | ☐ |

---

## 5. Integration Test Scenarios

### Scenario A: End-to-End PO to Dispensing
**Navigation Flow:** Multiple modules involved

| Step | Action | Sidebar Navigation | Status |
|------|--------|-------------------|--------|
| 1 | Create PO for 100 units of "Paracetamol" | `Inventory Management` → `Purchase Orders` → "Create New PO" | ☐ |
| 2 | Approve PO | `Inventory Management` → `Purchase Orders` → Filter "Pending" → Click PO → "Approve" | ☐ |
| 3 | Receive goods with batch "BTH-001", expiry 2027-12-31 | `Inventory Management` → `Goods Receipt` → Select PO | ☐ |
| 4 | Verify stock in store = 100 | `Inventory Management` → `Store Workbench` → Select store | ☐ |
| 5 | Create patient prescription for 10 units | `Doctor Workbench` → Select patient → Prescribe | ☐ |
| 6 | Dispense from pharmacy | `Pharmacy Workbench` → Pending prescriptions → Dispense | ☐ |
| 7 | Verify stock reduced to 90 | `Inventory Management` → `Store Workbench` | ☐ |
| 8 | Verify batch quantity reduced | `Inventory Management` → `Store Workbench` → View batches | ☐ |
| 9 | Check stock transaction log | `Inventory Management` → `Stock Transactions` | ☐ |

### Scenario B: Multi-Store Requisition
**Navigation Flow:** Requisition workflow

| Step | Action | Sidebar Navigation | Status |
|------|--------|-------------------|--------|
| 1 | Main Pharmacy has 200 units | `Inventory Management` → `Store Workbench` → Select "Main Pharmacy" | ☐ |
| 2 | Ward A requests 50 units | `Inventory Management` → `Requisitions` → "New Requisition" | ☐ |
| 3 | Pharmacy manager approves | (Login as manager) `Inventory Management` → `Requisitions` → "Pending" → Approve | ☐ |
| 4 | Fulfill requisition | `Inventory Management` → `Requisitions` → "Approved" → "Fulfill" | ☐ |
| 5 | Main Pharmacy = 150, Ward A = 50 | `Inventory Management` → `Store Workbench` → Check each store | ☐ |
| 6 | Ward A dispenses 10 to patient | `Pharmacy Workbench` (Ward A) → Dispense | ☐ |
| 7 | Ward A = 40 | `Inventory Management` → `Store Workbench` → Select "Ward A" | ☐ |

### Scenario C: Import and Immediate Use
**Navigation Flow:** Import then use

| Step | Action | Sidebar Navigation | Status |
|------|--------|-------------------|--------|
| 1 | Import 5 new products via CSV | `Administration` → `Data Import/Export` → Products Tab | ☐ |
| 2 | Verify all 5 appear in product list | `Inventory Management` → `Products` | ☐ |
| 3 | Create PO for imported products | `Inventory Management` → `Purchase Orders` → "Create New PO" | ☐ |
| 4 | Receive PO goods | `Inventory Management` → `Goods Receipt` | ☐ |
| 5 | Products available for dispensing | `Pharmacy Workbench` → Search products | ☐ |

### Scenario D: FEFO Batch Selection
**Navigation Flow:** Batch management

| Step | Action | Sidebar Navigation | Status |
|------|--------|-------------------|--------|
| 1 | Receive Batch A: exp 2026-06-01, qty 50 | `Inventory Management` → `Store Workbench` → "Add Stock Batch" | ☐ |
| 2 | Receive Batch B: exp 2027-01-01, qty 50 | `Inventory Management` → `Store Workbench` → "Add Stock Batch" | ☐ |
| 3 | Dispense 30 units | `Pharmacy Workbench` → Dispense | ☐ |
| 4 | Verify Batch A selected (earlier expiry) | `Inventory Management` → `Store Workbench` → View batches | ☐ |
| 5 | Batch A = 20, Batch B = 50 | Check batch quantities | ☐ |

---

## 6. Error Handling Tests

### Import Error Scenarios
| Scenario | Expected Behavior | Status |
|----------|-------------------|--------|
| Network timeout during upload | Error message, no partial import | ☐ |
| Server error mid-import | Transaction rollback, error message | ☐ |
| File encoding issues (non-UTF8) | Warning or auto-convert | ☐ |
| Extremely long product names | Truncate or reject | ☐ |
| Special characters in data | Handle or sanitize | ☐ |

### PO/Requisition Error Scenarios
| Scenario | Expected Behavior | Status |
|----------|-------------------|--------|
| Approve PO with deleted product | Warning, skip item | ☐ |
| Receive more than ordered | Prevent or warn | ☐ |
| Fulfill requisition with 0 stock | Error message | ☐ |
| Delete store with pending requisitions | Prevent deletion | ☐ |

---

## 7. Performance Tests

| Test | Criteria | Status |
|------|----------|--------|
| Import 1000 products | < 30 seconds | ☐ |
| Export 5000 patients | < 15 seconds | ☐ |
| Load store with 500 products | < 3 seconds | ☐ |
| Search products (autocomplete) | < 500ms | ☐ |

---

## Test Sign-off

| Module | Tester | Date | Status |
|--------|--------|------|--------|
| Store Management | | | ☐ Passed / ☐ Failed |
| Purchase Orders | | | ☐ Passed / ☐ Failed |
| Requisitions | | | ☐ Passed / ☐ Failed |
| Product Import | | | ☐ Passed / ☐ Failed |
| Service Import | | | ☐ Passed / ☐ Failed |
| Staff Import | | | ☐ Passed / ☐ Failed |
| Patient Import | | | ☐ Passed / ☐ Failed |
| Data Export | | | ☐ Passed / ☐ Failed |

---

*Document Version: 1.0*
*Created: January 22, 2026*
*Last Updated: January 22, 2026*
