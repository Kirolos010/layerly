# AI Design Studio | استوديو التصميم الذكي

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/MySQL-5.7%2B%20%2F%208.0%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Canvas](https://img.shields.io/badge/Fabric.js-5.3.1-blue)](http://fabricjs.com/)
[![AI Engine](https://img.shields.io/badge/Google%20Gemini-API-4285F4?logo=google&logoColor=white)](https://ai.google.dev/)
[![Design](https://img.shields.io/badge/UI-Liquid%20Glass-8B5CF6)](https://apple.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A lightweight, bilingual (Arabic & English) web application where users describe designs in natural language, import existing posters to make them editable, or customize curated starter templates. Generated designs are structured into independent, editable vector elements rendered onto an interactive Fabric.js canvas with an Apple-inspired **Liquid Glass UI**, genuine RTL layout, Google Arabic typography, undo/redo, auto-saving, MySQL cloud project persistence, and high-resolution export.

Built entirely with **vanilla PHP 8+ (zero heavyweight frameworks, zero Composer overhead)**, **MySQL via PDO**, **modular vanilla ES6 JavaScript**, **Fabric.js 5.3.1**, and modern **CSS3 with hardware-accelerated Liquid Glass aesthetics**. Compatible with local development on **Laragon (Windows)**, Apache, Nginx, and standard shared PHP hosting (**cPanel, InfinityFree, ByetHost**).

---

## Table of Contents / جدول المحتويات
- [English Documentation](#-english-documentation)
  1. [System Architecture & Folder Structure](#1-system-architecture--folder-structure)
  2. [Core Features](#2-core-features)
  3. [Prerequisites & PHP Extensions](#3-prerequisites--php-extensions)
  4. [Local Setup (Laragon / Built-in Server)](#4-local-setup-laragon--built-in-server)
  5. [Database Migrations & Setup](#5-database-migrations--setup)
  6. [Configuration Reference (`config.php`)](#6-configuration-reference-configphp)
  7. [Production Deployment & Security](#7-production-deployment--security)
  8. [Automated Testing](#8-automated-testing)
- [الدليل الكامل باللغة العربية (Arabic Documentation)](#-الدليل-الكامل-باللغة-العربية)
  1. [معمارية النظام وهيكل المجلدات](#1-معمارية-النظام-وهيكل-المجلدات)
  2. [المميزات الرئيسية](#2-المميزات-الرئيسية)
  3. [المتطلبات الأساسية وإضافات PHP](#3-المتطلبات-الأساسية-وإضافات-php)
  4. [التشغيل المحلي عبر Laragon أو سيرفر PHP المدمج](#4-التشغيل-المحلي-عبر-laragon-أو-سيرفر-php-المدمج)
  5. [إعداد وترقية قاعدة البيانات (Migrations)](#5-إعداد-وترقية-قاعدة-البيانات-migrations)
  6. [دليل إعداد ملف التكوين (config.php)](#6-دليل-إعداد-ملف-التكوين-configphp)
  7. [النشر على خوادم الإنتاج والاستضافات](#7-النشر-على-خوادم-الإنتاج-والاستضافات)
  8. [تشغيل الاختبارات المؤتمتة](#8-تشغيل-الاختبارات-المؤتمتة)

---

# 🇬🇧 English Documentation

## 1. System Architecture & Folder Structure

```
ai-design-studio/
├── auth/                      # Authentication & session pages
│   ├── login.php              # User login
│   ├── register.php           # User registration
│   ├── logout.php             # Session termination
│   ├── forgot-password.php    # Password reset request
│   └── reset-password.php     # Token verification & password update
├── account/                   # User dashboard & personal workspace
│   ├── profile.php            # User profile & language settings
│   ├── projects.php           # My Designs project grid (CRUD)
│   └── history.php            # Generation prompt & result history
├── pages/                     # Informational & utility views
│   ├── templates.php          # Starter templates gallery
│   ├── about.php              # Platform overview & quick help guide
│   └── 404.php                # Custom glassmorphic 404 page
├── admin/                     # System administration & diagnostics
│   ├── install.php            # Guided web setup wizard (protect/delete after setup)
│   └── diagnostics.php        # Environment & API readiness health checker
├── api/                       # RESTful JSON endpoints (CSRF-protected)
│   ├── enhance.php            # Expands brief user prompts
│   ├── generate.php           # Generates modular vector canvas JSON
│   ├── edit.php               # Conversational AI canvas modifications
│   ├── poster-generate.php    # High-fidelity AI poster generation pipeline
│   ├── poster-import.php      # Vision analysis + clean plate extraction
│   ├── poster-quota.php       # Rate limit & remaining daily quota check
│   ├── remove-bg.php          # RMBG background removal proxy
│   ├── extract-text.php       # Gemini Vision OCR text layer extraction
│   ├── projects.php           # Projects CRUD with owner isolation
│   ├── templates.php          # Starter template JSON loader
│   ├── history.php            # Generation log inspector & pruner
│   ├── i18n.php               # Client-side translation dictionary feed
│   ├── upload.php             # Safe upload proxy (prevents canvas tainting)
│   └── proxy-image.php        # SSRF-protected external image proxy
├── includes/                  # Core server architecture & business logic
│   ├── bootstrap.php          # Application bootstrapper & clean URL helper
│   ├── db.php                 # PDO database singleton connection
│   ├── security.php           # Security headers, CSRF tokens, sessions, i18n
│   ├── auth.php               # Authentication state & guest-to-user migration
│   ├── rate-limiter.php       # Sliding window IP & email rate limiter
│   ├── sanitizer.php          # Strict AI schema validator, clamping, font whitelist
│   ├── HistoryRepository.php  # Generation history persistence repository
│   └── ai/                    # Modular AI service providers
│       ├── AiClientInterface.php   # Standard provider contract
│       ├── GeminiClient.php        # Google Gemini Flash/Pro client
│       ├── GroqClient.php          # High-speed Groq LLaMA failover
│       ├── HuggingFaceClient.php   # RMBG background segmentation
│       └── AiService.php           # Multi-provider coordinator with failover
├── starter-templates/         # 10 Curated starter templates (JSON)
│   ├── ramadan-sale-post.json       # Arabic Instagram Post (1080x1080)
│   ├── coffee-shop-promo.json       # English Artisan Coffee Post (1080x1080)
│   ├── tech-course-banner.json      # Arabic Tech Bootcamp Banner (1200x630)
│   ├── fitness-workout-story.json   # English Workout Story (1080x1920)
│   ├── luxury-event-invitation.json # Arabic Gala Invitation (1080x1920)
│   ├── youtube-thumbnail-tech.json  # English Tech Thumbnail (1280x720)
│   ├── business-card-modern.json    # English Executive Card (1050x600)
│   ├── food-menu-flyer.json         # Arabic Restaurant Flyer (1080x1080)
│   ├── quote-card-minimal.json      # English Quote Card (1080x1080)
│   └── summer-sale-story.json       # Arabic Summer Promo Story (1080x1920)
├── database/
│   ├── schema.sql             # Full canonical database schema
│   └── migrations/            # Ordered incremental SQL migrations
│       ├── 001_initial_schema.sql
│       ├── 002_users_and_history.sql
│       └── 003_poster_quota.sql
├── storage/                   # Server-managed runtime files
│   ├── logs/                  # Daily diagnostic and error logs
│   └── uploads/               # User-uploaded assets & clean plates
├── assets/                    # Static front-end assets
│   ├── css/style.css          # Liquid Glass UI styling & RTL variables
│   └── js/                    # Modular ES6 JavaScript
│       ├── app.js             # Studio application entry point
│       └── editor/            # Canvas, layers, history, export managers
├── lang/                      # Bilingual translation dictionaries
│   ├── en.json / en.php       # Canonical English translations
│   └── ar.json / ar.php       # Canonical Arabic translations
├── tools/                     # Developer & CLI maintenance utilities
│   ├── migrate.php            # Automated database migration runner
│   ├── sync_translations.php  # JSON key parity validator
│   └── check_models.php       # Available Gemini models tester
├── tests/                     # Automated PHP test suites
├── docs/                      # Extended documentation & guides
│   ├── DEPLOYMENT.md          # Production deployment guide
│   └── screenshots/           # UI screenshots
├── .htaccess                  # Apache routing, clean URLs & directory locks
├── index.php                  # Main Studio canvas editor interface
└── config.example.php         # Configuration template
```

---

## 2. Core Features

- **Generative AI Design Engine**: Transforms natural language prompts into fully structured, editable Fabric.js vector objects (headings, subtext, badges, shapes, decorative accents).
- **Import & Make Editable (Mode 1)**: Upload any finished poster image. Gemini Vision transcribes all text and extracts bounding boxes, while the PHP GD inpainting engine cleans text background to produce modular editable layers.
- **Strict Copy & Anti-Hallucination**: Mode 2 art-directs posters using exact user copy without hallucinating or fabricating text.
- **Apple Liquid Glass Design System**: Glassmorphic panels, mesh gradient backdrop, frosted blur effects, and seamless light/dark mode with zero flash of unstyled theme.
- **Genuine RTL & Arabic Typography**: First-class Arabic layout with curated Google Arabic fonts (`Cairo`, `Tajawal`, `Almarai`, `Amiri`, `Reem Kufi`) and English fonts (`Poppins`, `Montserrat`, `Playfair Display`, `Roboto`).
- **Full Vector Editing**: Select, drag, resize, rotate, group, ungroup, align, duplicate, lock/unlock, and re-order layers via the visual layers panel.
- **High-Resolution Export**: Export at 1x, 2x, or 3x scale in PNG (with transparent background option), crisp JPG (quality slider), vector SVG, or raw project JSON.
- **Honest Bilingual Error Reporting**: Transparent errors when rate limits or quotas are reached; zero canned mock designs.
- **Hybrid Guest & User Architecture**: Work instantly as an anonymous guest with persistent cookie tokens; seamlessly migrate all guest designs upon registration or login.

---

## 3. Prerequisites & PHP Extensions

- **PHP 8.0 or newer** (tested on PHP 8.0, 8.1, 8.2, 8.3)
- **MySQL 5.7+ / 8.0+** or **MariaDB 10.3+**
- Required PHP Extensions:
  - `pdo_mysql` (Database access)
  - `curl` (AI API communication)
  - `gd` (Inpainting, clean plates, thumbnail processing)
  - `json` (Payload parsing & i18n)
  - `mbstring` (Multilingual UTF-8 string handling)
  - `fileinfo` (MIME-type security verification)

---

## 4. Local Setup (Laragon / Built-in Server)

### Option A: With Laragon (Recommended on Windows)
1. Clone or place the repository in `C:\laragon\www\ai-design-studio`.
2. Start All Services in Laragon (Apache & MySQL).
3. Copy `config.example.php` to `config.php`:
   ```bash
   cp config.example.php config.php
   ```
4. Open `config.php` and configure your database credentials and API keys:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'ai_design_studio');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('GEMINI_API_KEY', 'your_gemini_api_key_here');
   ```
5. Run migrations:
   ```bash
   php tools/migrate.php
   ```
6. Access the studio at: `http://ai-design-studio.test/` or `http://localhost/ai-design-studio/`.

### Option B: With PHP Built-in Server
1. Navigate to the project root:
   ```bash
   cd ai-design-studio
   cp config.example.php config.php
   # Edit config.php with your MySQL and Gemini credentials
   php tools/migrate.php
   php -S localhost:8000
   ```
2. Open your browser at `http://localhost:8000`.

---

## 5. Database Migrations & Setup

The database schema is managed via structured incremental SQL files in `database/migrations/`:
- `001_initial_schema.sql` (Projects & Rate Limits)
- `002_users_and_history.sql` (Users, Password Resets, Generations)
- `003_poster_quota.sql` (Poster Pipeline Enum Types)

Run the automated migration runner:
```bash
php tools/migrate.php
```
The runner creates a `migrations` tracking table, applies any pending migrations in order within a single transaction per migration, and guarantees idempotent execution.

---

## 6. Configuration Reference (`config.php`)

> [!WARNING]
> **NEVER COMMIT `config.php` TO VERSION CONTROL.** It contains sensitive database credentials and API keys. The file is listed in `.gitignore` by default.

Key configuration constants in `config.example.php`:

| Constant | Default | Description |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | MySQL server host |
| `DB_NAME` | `ai_design_studio` | Database name |
| `DB_USER` | `root` | Database username |
| `DB_PASS` | `""` | Database password |
| `GEMINI_API_KEY` | `""` | Google Gemini API Key ([Get one free](https://aistudio.google.com/)) |
| `GEMINI_MODEL` | `gemini-2.5-flash` | Text/design generation model |
| `GEMINI_VISION_MODEL` | `gemini-2.5-flash` | OCR & vision analysis model |
| `GEMINI_IMAGE_MODEL` | `imagen-3.0-generate-002` | Clean plate AI inpainting model |
| `HUGGINGFACE_API_KEY`| `""` | Optional token for RMBG background removal |
| `GROQ_API_KEY` | `""` | Optional fallback provider key |
| `AI_TIMEOUT_SECONDS` | `22` | Per-request timeout budget |
| `APP_ENV` | `development` | `development` or `production` |
| `BASE_URL` | `null` | Custom deployment subpath or auto-detected |
| `DIAGNOSTICS_SECRET` | `""` | Secret token required to access `admin/diagnostics.php` |

---

## 7. Production Deployment & Security

1. **Access Controls in `.htaccess`**: Direct browser requests to `/config.php`, `/includes/`, `/storage/`, `/database/`, `/tests/`, `/tools/`, and `/docs/` return `403 Forbidden`.
2. **Same-Origin Upload Proxy**: Images are served via `api/upload.php` to prevent tainted canvas errors in Fabric.js during export.
3. **Clean URLs**: `.htaccess` transparently routes clean URL endpoints (`/login`, `/register`, `/projects`, `/templates`, `/history`, `/about`) to their respective page controllers.
4. **Non-Deployment Directories**: When packaging a release bundle, exclude `tests/`, `tools/`, and `docs/`. See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for full instructions.

---

## 8. Automated Testing

Run the entire suite of 9 test runners with a single command:
```bash
php tests/run_all.php
```

Individual test suites:
- `php tests/diagnostics_test.php` (Environment and extension checks)
- `php tests/http_endpoints_test.php` (API simulation & CSRF rejection)
- `php tests/design_engine_test.php` (Security, rate limits, schema sanitization)
- `php tests/auth_and_history_test.php` (User accounts, sessions, guest migration)
- `php tests/editor_integration_test.php` (Fabric.js editor script integration)
- `php tests/export_and_templates_test.php` (Multi-scale export math & template loading)
- `php tests/i18n_and_glass_ui_test.php` (Bilingual parity, Liquid Glass UI tokens)
- `php tests/hybrid_and_vision_test.php` (Background removal & vision OCR endpoints)
- `php tests/poster_pipeline_test.php` (AI Poster Mode 1, Mode 2, GD clean plates)

---

# 🇪🇬 الدليل الكامل باللغة العربية

## 1. معمارية النظام وهيكل المجلدات

تمت إعادة هيكلة المشروع وفق أفضل الممارسات البرمجية بدون أطر عمل ثقيلة:
- `auth/`: صفحات المصادقة (تسجيل الدخول، إنشاء الحساب، استعادة كلمة المرور).
- `account/`: إدارة الحساب، لوحة المشروعات والتصاميم المحفوظة، وسجل التوليد.
- `pages/`: معرض القوالب الجاهزة، صفحة المساعدة والمعلومات، وصفحة 404.
- `admin/`: معالج التثبيت الذكي `install.php` وفاحص جاهزية النظام `diagnostics.php`.
- `api/`: واجهات برمجية RESTful بصيغة JSON محمية برمز CSRF ومحددة بمعدل طلبات.
- `includes/`: منطق العمل، فئات AI المستقلة في `includes/ai/`، والمصادقة والأمان.
- `database/migrations/`: ترقيات قاعدة البيانات المرتبة ذاتياً عبر `tools/migrate.php`.
- `starter-templates/`: 10 قوالب احترافية ثنائية اللغة لشبكات التواصل الاجتماعي والبانرات.
- `lang/`: قواميس الترجمة المتطابقة 100% بين العربية والإنجليزية (307 مفتاحاً).

---

## 2. المميزات الرئيسية

- **توليد التصاميم الذكي**: تحويل الوصف النصي إلى عناصر رسومية متجهة مستقلة وقابلة للتعديل.
- **استيراد البوسترات وتفكيكها (Mode 1)**: رفع أي بوستر وتحليله بذكاء Gemini Vision لإعادة رسم النصوص كطبقات متجهة واستخراج الخلفية النظيفة.
- **نظام تصميم الزجاج السائل Liquid Glass**: واجهة متطورة مستوحاة من فلسفة Apple مع ألوان متدرجة حية ودعم الوضعين الليلي والنهاري.
- **دعم حقيقي للاتجاه من اليمين لليسار (RTL)**: خطوط عربية متميزة من Google Fonts (`Cairo`, `Tajawal`, `Almarai`, `Amiri`, `Reem Kufi`).
- **تصدير فائق الدقة**: تصدير بصيغ PNG الشفافة، JPG، ومسارات SVG بدقة 1x و 2x و 3x.
- **أمان ومصداقية كاملة**: عدم اختلاق بيانات وهمية عند انقطاع الاتصال أو نفاد الحصة المجانية مع إظهار أخطاء واضحة وثنائية اللغة.

---

## 3. المتطلبات الأساسية وإضافات PHP

- **PHP 8.0 أو أحدث** (متوافق مع 8.1 و 8.2 و 8.3).
- **MySQL 5.7+ / 8.0+** أو **MariaDB 10.3+**.
- الإضافات المطلوبة في PHP: `pdo_mysql`, `curl`, `gd`, `json`, `mbstring`, `fileinfo`.

---

## 4. التشغيل المحلي عبر Laragon أو سيرفر PHP المدمج

1. ضع المجلد في مسار `C:\laragon\www\ai-design-studio`.
2. انسخ ملف الإعدادات:
   ```bash
   cp config.example.php config.php
   ```
3. أضف مفتاح Google Gemini وبيانات قاعدة البيانات داخل `config.php`.
4. نفذ ملف الترقيات عبر الطرفية:
   ```bash
   php tools/migrate.php
   ```
5. افتح المتصفح على: `http://localhost/ai-design-studio/` أو عبر منفذ PHP المدمج `php -S localhost:8000`.

---

## 5. إعداد وترقية قاعدة البيانات (Migrations)

لتطبيق التحديثات والترقيات بأمان تام ودون تكرار:
```bash
php tools/migrate.php
```

---

## 6. دليل إعداد ملف التكوين (config.php)

> [!CAUTION]
> **إياك ورفع ملف `config.php` إلى مستودع GitHub.** يحتوي الملف على مفاتيحك السرية وبيانات الاتصال.

- انسخ الملف من `config.example.php`.
- املأ المتغير `GEMINI_API_KEY` بمفتاحك المجاني من منصة Google AI Studio.
- عين رمزاً سرياً في `DIAGNOSTICS_SECRET` لحماية صفحة الفحص.

---

## 7. النشر على خوادم الإنتاج والاستضافات

راجع الدليل التفصيلي المرفق في [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) للتعرف على إعدادات خوادم Apache و Nginx وحماية المجلدات الخاصة.

---

## 8. تشغيل الاختبارات المؤتمتة

لتشغيل جميع حزم الفحص والتأكد من سلامة النظام 100%:
```bash
php tests/run_all.php
```

---

## License / الترخيص

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
جميع الحقوق محفوظة &copy; 2026 AI Design Studio Contributors. مرخص بموجب رخصة MIT الحرة.
