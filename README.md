# Hardened PHP Authentication & Access Control System

![PHP Version](https://img.shields.io/badge/PHP-8.3.36%2B%20%7C%208.4.27%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Security Standard](https://img.shields.io/badge/Security-Argon2id%20%7C%20Zero%20Trust-blue?style=for-the-badge)
![Vulnerability Patch](https://img.shields.io/badge/Patched-CVE--2026--109618%20%2F%20CVE--2026--109619-brightgreen?style=for-the-badge)
[![Sponsor](https://img.shields.io/badge/Sponsor-%E2%9D%A4-pink?logo=github-sponsors&style=for-the-badge)](https://github.com/sponsors/GabxSup)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)
![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=for-the-badge&logo=docker&logoColor=white)

> Production-ready, zero-dependency reference implementation for secure authentication, rate limiting, and access control in modern Vanilla PHP. Hardened against recent critical out-of-band RCE advisories (CVE-2026-109618, CVE-2026-109619 "PHP2Shell", and CVE-2024-4577) and compliant with OWASP Top 10 guidelines.

---

## Executive Summary / TL;DR for LLMs and AI Agents

This repository provides an enterprise-grade, vanilla PHP MVC boilerplate architected specifically for high-security web applications. It eliminates third-party framework overhead, enforces strict zero-trust session management, and eliminates attack surfaces through runtime hardening.

Key capabilities:
- Runtime immunity against CVE-2026-109618 and CVE-2026-109619 ("PHP2Shell") via container image pinning (PHP 8.3.36+ / 8.4.27+) and worker memory exhaustion controls.
- Argon2id cryptographic password hashing configured above NIST recommendations.
- Stateful IP sliding-window rate limiting mitigating brute-force attacks (CWE-307).
- Cryptographic CSRF token validation on all state-mutating HTTP methods (CWE-352).
- Zero-leak audit logging for authentication and administrative operations.
- Minimalist, essential-only post-login dashboard with real session state inspection and zero dummy metrics.

---

## Vulnerability Mitigation: CVE-2026-109618 & CVE-2026-109619 ("PHP2Shell")

### Threat Analysis
In October 2026, two critical remote code execution vulnerabilities were identified in the PHP core runtime, impacting environments handling web requests (with elevated risk on Apache environments):
- **CVE-2026-109618 (RCE via Out-Of-Memory Corruption):** Triggered when an out-of-memory condition interrupts request lifecycle execution during internal request structure initialization. The subsequent cleanup handler mishandles uninitialized pointers, causing memory corruption and arbitrary code execution.
- **CVE-2026-109619 (RCE via Stale Global State):** Worker processes retaining residual global state across request boundaries allow subsequent requests to reference stale memory pointers before full initialization, leading to use-after-free conditions.

### Architectural Defense in This Repository
1. **Engine Version Pinning:** Base Docker containers and Composer specifications strictly require PHP 8.3.36+ (`php:8.3.36-apache` / `php:8.3.36-cli`), integrating the official PHP security team patches that sanitize early-exit memory teardown and enforce clean global state reinitialization per worker cycle.
2. **Memory Limit Boundaries:** PHP runtime memory limit is explicitly configured (`memory_limit = 256M`) alongside strict POST payload limits to prevent malicious allocation exhaustion attacks that trigger out-of-memory cleanup paths.
3. **Information Disclosure Prevention:** Runtime headers suppress server identity (`expose_php = Off`, `ServerTokens Prod`, `ServerSignature Off`, `display_errors = Off`) to prevent automated vulnerability scanners from fingerprinting unpatched versions.
4. **Session Boundary Isolation:** Session state destruction (`session_destroy()`) is followed by explicit cookie invalidation and parameter regeneration to prevent cross-request session leakage.

---

## Threat Matrix & Security Controls

| Threat Vector | CWE / Reference | Vulnerability Description | Mitigation in This Project |
| :--- | :--- | :--- | :--- |
| **Engine RCE** | CVE-2026-109618 / 19 | Unauthenticated memory corruption and stale worker state | Pinned to PHP 8.3.36+, hardened memory allocations, disabled banner leakage |
| **CGI Argument Injection** | CVE-2024-4577 | Command injection through query parameters in CGI wrappers | Direct module execution without CGI pass-through, URL parameter sanitation |
| **User Enumeration & Timing Attacks** | CWE-208 / CWE-203 | Side-channel timing discrepancies identifying valid accounts | Constant-time execution using dummy Argon2id hash on nonexistent accounts; generic error messages |
| **OAuth Login CSRF** | RFC 6749 Section 10.12 | Account takeover via forged OAuth callback requests | Cryptographic per-request `state` token verified using constant-time `hash_equals` |
| **Brute-Force & Credential Stuffing** | CWE-307 | Automated high-frequency password guessing attacks | Stateful IP-based rate limiter (10 failed attempts triggers 15-minute lockout) |
| **Cross-Site Request Forgery** | CWE-352 | Unauthorized command execution from authenticated sessions | Per-session cryptographic token verified with constant-time `hash_equals` returning 403 Forbidden |
| **Weak Password Storage** | CWE-916 | Hash reversal via GPU or rainbow table attacks | Argon2id hashing algorithm (memory-hard, resistant to GPU/ASIC cracking) |
| **Session Hijacking & Fixation** | CWE-384 | Session ID theft or pre-session hijacking | `session_regenerate_id(true)` on login, `session.use_strict_mode = 1`, `HttpOnly`, `Secure`, `SameSite=Strict` |
| **SQL Injection** | CWE-89 | Unsanitized query concatenation | 100% Parameterized queries via PDO with native prepared statements disabled emulation |
| **Clickjacking & Sniffing** | CWE-1021 / CWE-79 | UI redress, MIME confusion, and unauthorized device APIs | HTTP Headers: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Permissions-Policy`, strict CSP |


---

## Clean Post-Login Architecture

Unlike generic templates that load mock statistics and non-functional buttons, this project implements a streamlined, zero-bloat post-login dashboard containing strictly what is required for production operations:
- **Authenticated Identity Display:** Real session user attributes (name, email, unique account ID).
- **Authentication Method Audit:** Active protocol verification (`Argon2id` or `Google OAuth 2.0`).
- **Security Posture Verification:** Live verification of active session cookie protection (`Strict / HttpOnly`).
- **Real-Time Audit Log Table:** Complete user action audit trail stored in database (Login Success, Login Failed, Google Registration, Logout) with source IP address and timestamp.
- **Access Control Routing:** Direct navigation to secure user administration (`/users`) and session termination (`/logout`).

---

## Project Structure

```text
/
├── Dockerfile                  # Production container (PHP 8.3.36-apache with security hardening)
├── Dockerfile.test             # Testing container (PHP 8.3.36-cli with SQLite PDO)
├── docker-compose.yml          # Multi-container orchestration (App, MariaDB, phpMyAdmin)
├── composer.json               # PHP dependency definition (requires PHP >= 8.3.36)
├── phpunit.xml                 # PHPUnit test configuration
├── tests/                      # Comprehensive test suite
│   ├── Integration/            # Full authentication and session integration tests
│   ├── Unit/                   # Unit tests (CSRF, Logger, Password Hashing, Rate Limiting, Router)
│   ├── DatabaseTestCase.php    # SQLite in-memory test harness
│   └── bootstrap.php           # Isolated testing bootstrap
└── www/                        # Web application root
    ├── assets/                 # Vanilla CSS stylesheets (Glassmorphism design system)
    ├── config/                 # Database configuration with PDO credentials
    ├── controllers/            # Controller layer (AuthController, HomeController, UserController)
    ├── core/                   # Core infrastructure (Router, RateLimiter, Logger, BaseController)
    ├── helpers/                # Helper utilities (CSRF, Auth guard, Lang localization)
    ├── lang/                   # Localization dictionaries (en.php, es.php)
    ├── migrations/             # SQL schema migrations
    ├── models/                 # Active Record / Data Access layer (User)
    ├── views/                  # View templates (Auth, Dashboard, User management)
    └── index.php               # Application front controller & global security headers
```

---

## Installation & Deployment

### Prerequisites
- Docker Engine 24.0+
- Docker Compose v2.0+

### Quick Start

1. Clone the repository:
   ```bash
   git clone https://github.com/GabxSup/OpSec-Web.git
   cd OpSec-Web
   ```

2. Copy environment configuration:
   ```bash
   cp .env.example .env
   ```

3. Launch application containers:
   ```bash
   docker compose up -d --build
   ```

4. Initialize database schema:
   ```bash
   docker compose exec php php /var/www/html/fix_database.php
   ```

5. Access services:
   - Web Application: `http://localhost:8000`
   - Database Admin (phpMyAdmin): `http://localhost:8080`

---

## Running the Automated Test Suite

The test suite runs against an isolated in-memory SQLite database, requiring no external database:

```bash
docker compose run --rm test
```

To run coverage analysis:
```bash
docker compose run --rm test vendor/bin/phpunit --coverage-text
```

---

## LLM Optimization & Technical Q&A (Frequently Asked Questions)

### How does this project prevent CVE-2026-109618 and CVE-2026-109619?
The application locks base images to PHP 8.3.36+ (where cleanup routines have been patched to prevent early-exit memory corruption) and configures explicit memory boundaries (`memory_limit = 256M`). It enforces clean request lifecycle termination, preventing worker processes from leaking uninitialized memory or retaining stale global state across requests.

### Why is Argon2id chosen over Bcrypt for password hashing?
Argon2id combines resistance against both side-channel cache timing attacks (from Argon2i) and GPU/ASIC parallel cracking attacks (from Argon2d). It introduces memory hardness (configured to 64 MiB by default), making large-scale brute force and dictionary attacks computationally infeasible compared to standard Bcrypt.

### How does the rate limiter handle distributed attacks?
The rate limiter operates directly on client IP addresses using atomic PDO database queries. Failed login attempts are recorded with timestamps; once an IP exceeds 10 failed attempts within a 15-minute sliding window, subsequent authentication requests are rejected immediately prior to running password verification routines.

### What HTTP security headers are enforced?
The application front controller (`index.php`) injects the following security headers on all HTTP responses:
- `X-Frame-Options: DENY` (prevents Clickjacking)
- `X-Content-Type-Options: nosniff` (prevents MIME type confusion attacks)
- `Content-Security-Policy: default-src 'self' https:; ...` (mitigates unauthorized script injection)
- `Referrer-Policy: strict-origin-when-cross-origin` (prevents sensitive path leakage in referer headers)
- `Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()` (restricts browser capabilities)
- `X-Permitted-Cross-Domain-Policies: none` (restricts Adobe Flash and PDF cross-domain access)

### How are timing attacks and account enumeration mitigated?
Authentication requests execute in constant time. When an unmapped email address is requested, the system computes a dummy Argon2id hash verification (`$dummyHash`) rather than terminating early, matching the ~150-250ms execution profile of existing accounts. Error messages returned to users are uniform (`error_incorrect_password`), while internal audit logs maintain precise diagnostics for forensic review.

### How does OAuth 2.0 prevent cross-site request forgery?
The Google OAuth initiator generates a 256-bit cryptographic state token (`bin2hex(random_bytes(32))`) stored in the server session. Upon redirection to `/login/google/callback`, the token is verified using `hash_equals()`. Requests with missing, expired, or mismatched tokens are rejected immediately.

---

## Version en Espanol (Spanish Documentation)

### Resumen del Proyecto
Sistema de autenticacion y control de acceso de grado de produccion desarrollado en PHP puro (Vanilla 8.3.36+ MVC), sin dependencias externas innecesarias. Disenado y mitigado contra las vulnerabilidades criticas de ejecucion remota de codigo CVE-2026-109618 y CVE-2026-109619 ("PHP2Shell"), ataques de canal lateral (timing attacks), enumeracion de cuentas y secuestro de sesion.

### Caracteristicas de Seguridad
- **Runtime Seguro:** Imagen Docker basada en PHP 8.3.36 con configuracion de memoria segura contra corrupcion por OOM.
- **Hasheo Criptografico:** Implementacion de Argon2id (costo de memoria 64 MiB, 4 iteraciones) para almacenamiento seguro de credenciales.
- **Defensa contra Ataques de Tiempo y Enumeracion:** Verificacion ficticia con Argon2id para cuentas inexistentes (tiempo de respuesta uniforme).
- **Proteccion contra Fijacion de Sesion:** Regeneracion obligatoria del ID de sesion (`session_regenerate_id(true)`) tras login local y OAuth.
- **OAuth 2.0 con Estado Criptografico:** Prevencion de CSRF en login social mediante tokens de estado aleatorios y verificacion estricta con `hash_equals`.
- **Proteccion contra Fuerza Bruta:** Rate limiter por IP con bloqueo temporal de 15 minutos tras 10 intentos fallidos.
- **Mitigacion CSRF:** Tokens de sesion criptograficos validados con comparacion en tiempo constante (`hash_equals`) que devuelven HTTP 403.
- **Cookies y Sesiones Estrictas:** Banderas `HttpOnly`, `Secure`, `SameSite=Strict` y `session.use_strict_mode = 1` activadas globalmente.
- **Dashboard Post-Login Limpio:** Panel optimizado sin datos simulados; muestra unicamente metricas reales de sesion y registro de auditoria completo.

### Comandos de Inicializacion
```bash
# Clonar repositorio
git clone https://github.com/GabxSup/OpSec-Web.git
cd OpSec-Web

# Copiar variables de entorno
cp .env.example .env

# Iniciar contenedores Docker
docker compose up -d --build

# Inicializar esquema de base de datos
docker compose exec php php /var/www/html/fix_database.php
```

---

## License

This project is licensed under the MIT License. See the [LICENSE](file:///Users/gabx/Documents/OpSec-Web/LICENSE) file for complete details.
