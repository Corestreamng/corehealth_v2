# Security Policy

## Supported Versions

| Version | Supported |
| ------- | --------- |
| 2.6.x   | ✅ Yes    |
| 2.5.x   | ✅ Yes    |
| < 2.5   | ❌ No     |

## Reporting a Vulnerability

CoreHealth v2 is a clinical Healthcare Management Information System. Security vulnerabilities could affect patient data and clinical workflows. We take all security reports seriously.

**Please do NOT disclose security vulnerabilities publicly via GitHub Issues.**

### How to Report

1. **Email**: Send a detailed report to `info@corestream.ng` (or the project maintainer).
2. **Include in your report**:
    - Description of the vulnerability
    - Steps to reproduce
    - Potential impact and affected versions
    - Any proof-of-concept code or screenshots

### Response Timeline

| Stage                  | Timeframe                                               |
| ---------------------- | ------------------------------------------------------- |
| Acknowledgement        | Within 48 hours                                         |
| Initial assessment     | Within 5 business days                                  |
| Fix or mitigation plan | Within 30 days for critical, 90 days for lower severity |
| Public disclosure      | Coordinated with reporter after fix is deployed         |

### Vulnerability Severity

We follow the CVSS v3.1 severity scale:

| Severity | CVSS Score | Response Target |
| -------- | ---------- | --------------- |
| Critical | 9.0–10.0   | 7 days          |
| High     | 7.0–8.9    | 30 days         |
| Medium   | 4.0–6.9    | 60 days         |
| Low      | 0.1–3.9    | 90 days         |

### Scope

**In scope:**

- Authentication and session management
- Patient data access and PHI/PII exposure
- SQL injection and data exfiltration
- Cross-site scripting (XSS) and CSRF in clinical workflows
- Privilege escalation (role/permission bypass)
- API endpoint security (mobile apps, REST endpoints)

**Out of scope:**

- Vulnerabilities in third-party dependencies already reported upstream
- Social engineering attacks
- Physical access attacks
- Self-inflicted damage from misconfigured environments

### Security Best Practices in This Codebase

- All HTTP inputs are validated using Laravel's `$request->validate()` before processing
- SQL queries use Eloquent ORM with parameterized bindings — no raw interpolated SQL
- Secrets and credentials are injected via environment variables (`.env`) — never committed
- Role-based access control is enforced via Spatie Laravel Permission on all clinical routes
- CSRF protection is enabled on all state-mutating endpoints
- Dependencies are audited weekly via `composer audit` and `npm audit` in CI

### Hall of Fame

We appreciate responsible disclosure. Reporters of valid, confirmed vulnerabilities will be credited here (with permission).

---

_This security policy is maintained by the CoreHealth v2 development team._
