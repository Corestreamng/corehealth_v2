# API Versioning Strategy

## Overview

CoreHealth v2 exposes a RESTful API for mobile applications, third-party integrations, and automated reporting systems. To ensure backwards compatibility while evolving the system, we enforce explicit API versioning.

## URI Structure

All API routes must be prefixed with the API version. 
Current stable version: `v1`

**Example:**
`/api/v1/patients/{id}`

## Header-Based Versioning (Fallback)

In cases where URI versioning is not possible for legacy integrations, the API will inspect the `Accept` header.

**Header Format:**
`Accept: application/vnd.corehealth.v1+json`

## Deprecation Policy

1. **Active**: The current primary version.
2. **Deprecated**: The version is slated for removal. A `Warning` header will be included in all responses, e.g., `Warning: 299 - "This API version is deprecated and will be removed in 6 months."`
3. **Retired**: The version is no longer supported and requests will return a `410 Gone` status code.

Clients will be given a minimum of 6 months notice before an API version transitions from Deprecated to Retired.

## Breaking Changes

A new API version (e.g., `v2`) must be created when introducing any of the following breaking changes:
- Removing or renaming an endpoint.
- Removing or renaming a field in a response payload.
- Changing the data type of a field.
- Adding a new mandatory request parameter or payload field.

Non-breaking changes (such as adding optional parameters or new fields to a response) can be introduced to the current active version without bumping the major version number.
