# SecureApp (Web Application Layer)

This directory contains the application source code for the hardened PHP authentication system.

## Architectural Features

- **Authentication Engine**: Argon2id password hashing, native Google OAuth 2.0 integration, and session lifecycle controls.
- **Security Defenses**:
  - Pinned runtime: PHP 8.3.36+ (mitigating CVE-2026-109618 and CVE-2026-109619).
  - Cross-Site Request Forgery (CSRF) protection via unique session tokens.
  - IP-based sliding window rate limiter.
  - Parameterized database operations via PDO.
  - HTTP security headers (CSP, X-Frame-Options, X-Content-Type-Options).
- **Internationalization**: Lightweight bilingual support (English and Spanish) via `Lang` helper.
- **Interface**: Clean glassmorphism layout, responsive data tables, zero mock/dummy metrics.

## Directory Structure

```text
www/
├── assets/          # CSS Styles (Glassmorphism layout)
├── config/          # Database configuration
├── controllers/     # Business logic (Auth, User, Home)
├── core/            # Infrastructure (Router, Logger, RateLimiter, BaseController)
├── helpers/         # Auxiliary helpers (CSRF, Auth guard, Lang)
├── lang/            # Translation dictionaries (en.php, es.php)
├── migrations/      # Database schemas
├── models/          # Data models (User)
├── views/           # Presentation layer (Auth, Home, Users)
└── index.php        # Application entry point & security headers
```

## Setup & Execution

Run via Docker Compose from the project root:
```bash
docker compose up -d
```
