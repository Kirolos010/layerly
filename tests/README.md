# AI Design Studio - Automated Test Suites

> **⚠️ DO NOT DEPLOY TO PRODUCTION:** The `tests/`, `tools/`, and `docs/` directories are intended strictly for local development, continuous integration, and test environments. Do not upload them to production shared hosting or public servers.

This directory contains automated unit, integration, and browser end-to-end tests for all core application features.

---

## Running the Tests

### 1. Run All Feature Suites
To run all feature tests in a single command:
```bash
php tests/run_all.php
```

### 2. Run Individual Feature Tests
You can run any test suite individually with PHP CLI:

| Suite File | Feature Covered |
| :--- | :--- |
| `php tests/diagnostics_test.php` | System requirements, PHP extensions (curl, gd, pdo_mysql), permissions |
| `php tests/http_endpoints_test.php` | API routing, CSRF protection, error response formatting |
| `php tests/design_engine_test.php` | Schema sanitization, rate limiting, prompt enhance, design generation |
| `php tests/auth_and_history_test.php` | User registration, login, bcrypt passwords, password reset, guest history migration |
| `php tests/editor_integration_test.php` | Fabric.js integration, SSRF proxy guards, font whitelisting, upload validation |
| `php tests/export_and_templates_test.php` | Resolution scaling math (1x/2x/3x), project CRUD, template gallery |
| `php tests/i18n_and_glass_ui_test.php` | Full bilingual key parity, RTL logical properties, Liquid Glass tokens |
| `php tests/hybrid_vision_test.php` | Pollinations text-free backgrounds, RMBG background removal, OCR extraction |
| `php tests/poster_pipeline_test.php` | Mode 1 poster import & vision OCR, GD inpainting clean plate, Mode 2 art direction |

### 3. Headless Browser End-to-End Tests (Python / Playwright / Chrome CDP)
```bash
python tests/export_browser_test.py
python tests/i18n_browser_test.py
python tests/hybrid_browser_test.py
python tests/poster_browser_test.py
```
*(Requires a local web server running at `http://localhost/ai-design-studio` or `http://localhost:8000`)*
