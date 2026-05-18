# Security Policy

## Supported versions

WP AI Connector is in pre-alpha. Once `1.0.0` ships, this section will list the supported version line(s) and how long each receives security patches.

| Version | Supported |
|---------|-----------|
| 0.x (pre-alpha) | Best-effort only. Do not run on a production site yet. |

## Reporting a vulnerability

**Please do not open a public GitHub issue for security vulnerabilities.**

Email **ljutaev@gmail.com** with:

- A description of the issue
- Steps to reproduce, including any required setup
- Affected versions
- Proof-of-concept code, screenshots, or curl output
- Your name and any preferred credit, if you'd like an acknowledgment

You will receive a response within **72 hours**. If the issue is confirmed, a fix is coordinated and released before public disclosure. A reasonable disclosure window is **90 days** from the initial report, but we will move faster for actively exploited issues.

## Scope

**In scope:**

- Authentication bypass on the REST API
- SQL injection in the SQL SELECT endpoint
- Capability escalation via API keys (e.g., a read-only key performing writes)
- Sensitive data exposure in API responses or admin-visible logs
- CSRF on admin actions (key generation, scope changes)
- Stored XSS in the admin UI

**Out of scope:**

- Vulnerabilities in third-party WordPress plugins (report to their authors)
- Vulnerabilities in WordPress core (report to the [WordPress core security team](https://wordpress.org/news/category/security/))
- Misconfigured server environments (publicly exposed `wp-config.php`, etc.)
- Social engineering attacks
- Denial of service via legitimate API use (rate limiting is a feature request, not a vulnerability)

## Acknowledgments

Researchers who responsibly disclose verified vulnerabilities are credited in the changelog and — with permission — in this file once the fix has shipped.
