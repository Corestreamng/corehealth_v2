# CAPEX Column Mapping

## Controller vs Migration Schema

### Main Table: capex_projects (exists) vs capex_requests (controller expects)

| Migration (capex_projects) | Controller (capex_requests) | View Usage | Notes |
|---|---|---|---|
| project_code | reference_number | reference_number | Unique identifier |
| project_name | title | title | Display name |
| project_type | category | category | equipment/technology/etc |
| fiscal_year | fiscal_year | fiscal_year | ✓ Added by 2nd migration |
| estimated_cost | requested_amount | requested_amount | Initial amount |
| approved_budget | approved_amount | approved_amount | After approval |
| actual_cost | actual_amount | spent/actual | Completed amount |
| proposed_date | created_at | - | Request date |
| status | status | status | Both use same enum values BUT different names |

### Status Values Mapping:
- Migration: `draft`, `pending_approval`, `approved`, `in_progress`, `completed`, `cancelled`, `on_hold`
- Controller/Views: `draft`, `pending`, `approved`, `in_progress`, `completed`, `rejected`, `revision`

### Missing Tables (controller expects):
1. **capex_request_items** - Migration doesn't have this, items tracked differently
2. **capex_approval_history** - Migration doesn't have this
3. **capex_expenses** - Migration has `capex_project_expenses` instead

### Solution:
Update controller to use:
- `capex_projects` instead of `capex_requests` (via view created in migration)
- Map column names correctly
- Either add missing tables OR remove references to them
