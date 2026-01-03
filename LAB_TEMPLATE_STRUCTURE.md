# Lab Result Template Structure (V2)

## Overview
The new lab result template system uses a structured JSON format stored in the `result_template_v2` column of the `services` table. This allows for:
- Structured data entry with validation
- Automatic normal/abnormal value detection
- Professional formatted result printouts
- Backward compatibility with existing HTML templates

## JSON Structure

```json
{
  "version": "2.0",
  "parameters": [
    {
      "id": "param_uuid",
      "name": "White Blood Cells",
      "code": "WBC",
      "type": "float",
      "unit": "cells/mm³",
      "required": true,
      "order": 1,
      "reference_range": {
        "min": 4000,
        "max": 11000,
        "display": "4,000 - 11,000 cells/mm³"
      }
    },
    {
      "id": "param_uuid2",
      "name": "Blood Group",
      "code": "BLOOD_GROUP",
      "type": "enum",
      "required": false,
      "order": 2,
      "options": ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"]
    },
    {
      "id": "param_uuid3",
      "name": "HIV Status",
      "code": "HIV",
      "type": "boolean",
      "required": true,
      "order": 3,
      "reference_value": false,
      "labels": {
        "true": "Positive",
        "false": "Negative"
      }
    },
    {
      "id": "param_uuid4",
      "name": "Comments",
      "code": "COMMENTS",
      "type": "long_text",
      "required": false,
      "order": 99
    }
  ],
  "categories": [
    {
      "name": "Complete Blood Count",
      "parameters": ["param_uuid", "param_uuid2"]
    }
  ]
}
```

## Field Types

### 1. **string**
Simple text input (single line)
```json
{
  "type": "string",
  "max_length": 255
}
```

### 2. **integer**
Whole numbers
```json
{
  "type": "integer",
  "reference_range": {
    "min": 60,
    "max": 100
  }
}
```

### 3. **float**
Decimal numbers
```json
{
  "type": "float",
  "decimal_places": 2,
  "reference_range": {
    "min": 12.0,
    "max": 16.0
  }
}
```

### 4. **boolean**
Yes/No, Positive/Negative
```json
{
  "type": "boolean",
  "reference_value": false,
  "labels": {
    "true": "Positive",
    "false": "Negative"
  }
}
```

### 5. **enum**
Predefined options (dropdown/select)
```json
{
  "type": "enum",
  "options": ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"],
  "allow_custom": false
}
```

### 6. **long_text**
Multi-line text area
```json
{
  "type": "long_text",
  "max_length": 2000,
  "rows": 4
}
```

## Parameter Schema

### Required Fields
- `id` (string): Unique identifier (UUID recommended)
- `name` (string): Display name of the parameter
- `code` (string): Short code (e.g., "WBC", "HB")
- `type` (string): One of: string, integer, float, boolean, enum, long_text
- `order` (integer): Display order

### Optional Fields
- `unit` (string): Unit of measurement (e.g., "g/dL", "cells/mm³")
- `required` (boolean): Whether value is required
- `reference_range` (object): For numeric types
  - `min` (number): Minimum normal value
  - `max` (number): Maximum normal value
  - `display` (string): How to display the range
- `reference_value` (any): Expected normal value (for boolean, string, enum)
- `options` (array): For enum type - available options
- `labels` (object): For boolean type - custom labels
- `max_length` (integer): For string/long_text types
- `decimal_places` (integer): For float type
- `rows` (integer): For long_text type - textarea rows
- `allow_custom` (boolean): For enum - allow values not in options
- `description` (string): Help text/instructions
- `group` (string): Category/section name

## Result Data Storage

When a result is entered using a V2 template, it's stored in two formats:

### 1. Structured Data (JSON in lab_service_requests.result_data column)
```json
{
  "template_version": "2.0",
  "service_id": 123,
  "parameters": {
    "param_uuid": {
      "value": 8500,
      "status": "normal",
      "reference_range": "4,000 - 11,000 cells/mm³"
    },
    "param_uuid2": {
      "value": "A+",
      "status": "N/A"
    },
    "param_uuid3": {
      "value": false,
      "status": "normal",
      "display": "Negative"
    }
  },
  "entered_by": 1,
  "entered_at": "2026-01-03 19:30:00"
}
```

### 2. HTML Representation (in lab_service_requests.result column)
Generated automatically for display and printing.

## Status Determination

### For Numeric Types (integer, float)
- **Normal**: Value within reference range (min ≤ value ≤ max)
- **High**: Value > max
- **Low**: Value < min

### For Boolean Type
- **Normal**: Value matches reference_value
- **Abnormal**: Value doesn't match reference_value

### For Enum/String Type
- **Normal**: Value matches reference_value (if specified)
- **N/A**: No reference value defined

## Migration Path

### Phase 1: Backward Compatibility
- Old tests without `result_template_v2` continue using HTML templates
- New tests can use V2 templates
- Result entry checks for `result_template_v2` first, falls back to `template`

### Phase 2: Gradual Migration
- Lab scientists can rebuild templates using the template builder
- Old results remain accessible
- New results use structured format

### Phase 3: Full Migration (Optional)
- Convert all services to V2 templates
- Keep old HTML templates for historical results

## Template Builder UI

The template builder will provide:
1. **Parameter Management**
   - Add/remove/reorder parameters
   - Drag-and-drop ordering
   - Field type selection with appropriate options

2. **Reference Range Configuration**
   - Min/max for numeric types
   - Expected values for boolean/enum
   - Custom labels and display formats

3. **Preview**
   - Live preview of result entry form
   - Sample data for testing

4. **Validation**
   - Ensure required fields are present
   - Validate reference ranges
   - Check for duplicate codes

## Example: Complete Blood Count Template

```json
{
  "version": "2.0",
  "parameters": [
    {
      "id": "wbc",
      "name": "White Blood Cells",
      "code": "WBC",
      "type": "float",
      "unit": "x 10⁹/L",
      "required": true,
      "order": 1,
      "decimal_places": 1,
      "reference_range": {
        "min": 4.0,
        "max": 10.0,
        "display": "4.0 - 10.0"
      }
    },
    {
      "id": "hb",
      "name": "Hemoglobin",
      "code": "HB",
      "type": "float",
      "unit": "g/dL",
      "required": true,
      "order": 2,
      "decimal_places": 1,
      "reference_range": {
        "min": 12.0,
        "max": 16.0,
        "display": "12.0 - 16.0"
      }
    },
    {
      "id": "pcv",
      "name": "Packed Cell Volume",
      "code": "PCV",
      "type": "float",
      "unit": "%",
      "required": true,
      "order": 3,
      "decimal_places": 1,
      "reference_range": {
        "min": 36.0,
        "max": 46.0,
        "display": "36 - 46"
      }
    },
    {
      "id": "platelet",
      "name": "Platelet Count",
      "code": "PLT",
      "type": "integer",
      "unit": "x 10⁹/L",
      "required": true,
      "order": 4,
      "reference_range": {
        "min": 150,
        "max": 400,
        "display": "150 - 400"
      }
    },
    {
      "id": "comments",
      "name": "Comments/Remarks",
      "code": "COMMENTS",
      "type": "long_text",
      "required": false,
      "order": 99,
      "rows": 3
    }
  ],
  "categories": [
    {
      "name": "Red Blood Cell Parameters",
      "parameters": ["hb", "pcv"]
    },
    {
      "name": "White Blood Cell Parameters",
      "parameters": ["wbc"]
    },
    {
      "name": "Platelet Parameters",
      "parameters": ["platelet"]
    }
  ]
}
```

## Database Schema

### services table
- `template` (longtext): Legacy HTML template - kept for backward compatibility
- `result_template_v2` (json): New structured template format

### lab_service_requests table  
Need to add:
- `result_data` (json): Structured result data when using V2 templates
- `result` (longtext): HTML representation (for both V1 and V2)

## Implementation Phases

1. ✅ Database migration
2. ✅ Model updates
3. ⏳ Template builder UI
4. ⏳ Result entry form renderer
5. ⏳ Normal/abnormal detection
6. ⏳ Professional print layout
7. ⏳ Backward compatibility testing
