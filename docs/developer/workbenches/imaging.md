# Imaging & Radiology Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | RADIOLOGIST</span>

## 1. Controllers & Route Contracts
- **Primary Controller**: `App\Http\Controllers\ImagingWorkbenchController`.
- **Primary Routes (`routes/web.php`)**:
  - `GET /imaging-workbench` (`imaging.workbench`) — Radiology queue and modality worklist.
  - `GET /imaging-workbench/queue` (`imaging.queue`) — Live imaging requests DataTable feed.
  - `GET /imaging-workbench/imaging-service-requests/{id}` (`imaging.getRequest`) — Single investigation details.
  - `GET /imaging-workbench/imaging-service-requests/{id}/attachments` (`imaging.getAttachments`) — Attachment and scan image viewer.

## 2. Models & Schema Fields
- `ImagingServiceRequest`: Tracks `entered_by_name`, `approved_by_name`, and modality classifications.

## 3. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/Imaging/
```

👉 **[Read User Story Guide for Imaging](/user-guides/workbenches/imaging)**
