# AI Design Studio | استوديو التصميم الذكي

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/MySQL-5.7%2B%20%2F%208.0%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Canvas](https://img.shields.io/badge/Fabric.js-5.3.1-blue)](http://fabricjs.com/)
[![AI Engine](https://img.shields.io/badge/Gemini-2.5%20Flash-4285F4?logo=google&logoColor=white)](https://ai.google.dev/)
[![Design](https://img.shields.io/badge/UI-Liquid%20Glass-8B5CF6)](https://apple.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A lightweight, bilingual (Arabic & English) web application where users describe designs in natural language or choose from curated starter templates. Generated designs are structured into independent, editable vector elements rendered onto an interactive Fabric.js canvas with an Apple-inspired **Liquid Glass UI**, genuine RTL layout, Google Arabic typography, undo/redo, auto-saving, MySQL cloud project persistence, and high-resolution export.

Built entirely with **plain PHP 8+ (no frameworks, no Composer)**, **MySQL via PDO**, **vanilla JavaScript (ES6 modules)**, **Fabric.js 5.3.1**, and modern **CSS3 with hardware-accelerated Liquid Glass aesthetics**. Compatible with local development on **Laragon (Windows)** and free shared PHP hosting (**InfinityFree**, **ByetHost**, **cPanel**).

---

## Table of Contents / جدول المحتويات
- [English Documentation](#-english-documentation)
  1. [System Architecture](#1-system-architecture)
  2. [Prerequisites & Required PHP Extensions](#2-prerequisites--required-php-extensions)
  3. [Getting a Free Google Gemini API Key](#3-getting-a-free-google-gemini-api-key)
  4. [Local Setup on Windows with Laragon & HeidiSQL](#4-local-setup-on-windows-with-laragon--heidisql)
  5. [Deploying to Free Shared Hosting (InfinityFree, ByetHost, cPanel)](#5-deploying-to-free-shared-hosting-infinityfree-byethost-cpanel)
  6. [Configuration Reference (`config.php`)](#6-configuration-reference-configphp)
  7. [Core Features & Liquid Glass Design](#7-core-features--liquid-glass-design)
  8. [Security & Authentication Architecture](#8-security--authentication-architecture)
  9. [Troubleshooting & FAQ](#9-troubleshooting--faq)
  10. [Automated Testing](#10-automated-testing)
- [الدليل الكامل باللغة العربية (Arabic Documentation)](#-الدليل-الكامل-باللغة-العربية)
  1. [نظرة عامة على النظام والمعمارية](#1-نظرة-عامة-على-النظام-والمعمارية)
  2. [المتطلبات الأساسية وإضافات PHP المطلوبة](#2-المتطلبات-الأساسية-وإضافات-php-المطلوبة)
  3. [الحصول على مفتاح Google Gemini API مجاناً](#3-الحصول-على-مفتاح-google-gemini-api-مجانا)
  4. [التشغيل المحلي عبر Laragon على ويندوز مع HeidiSQL](#4-التشغيل-المحلي-عبر-laragon-على-ويندوز-مع-heidisql)
  5. [النشر على الاستضافات المجانية (InfinityFree و ByetHost و cPanel)](#5-النشر-على-الاستضافات-المجانية-infinityfree-و-byethost-و-cpanel)
  6. [دليل إعداد ملف التكوين (config.php)](#6-دليل-إعداد-ملف-التكوين-configphp)
  7. [المميزات الرئيسية ونظام الزجاج السائل Liquid Glass](#7-المميزات-الرئيسية-ونظام-الزجاج-السائل-liquid-glass)
  8. [معمارية الأمان والخصوصية وحسابات المستخدمين](#8-معمارية-الأمان-والخصوصية-وحسابات-المستخدمين)
  9. [استكشاف الأخطاء وإصلاحها (Troubleshooting)](#9-استكشاف-الأخطاء-وإصلاحها-troubleshooting)
  10. [تشغيل حزم الاختبارات المؤتمتة](#10-تشغيل-حزم-الاختبارات-المؤتمتة)

---

# 🇬🇧 English Documentation

## 1. System Architecture

```
ai-design-studio/
├── api/
│   ├── enhance.php          # POST: Expands brief prompts into rich design descriptions
│   ├── generate.php         # POST: Generates structured canvas & elements JSON (with hybrid AI bg support)
│   ├── edit.php             # POST: Applies conversational edits to existing canvas JSON
│   ├── remove-bg.php        # POST: Background removal via Hugging Face RMBG-1.4 / GD engine
│   ├── extract-text.php     # POST: Vision OCR structured text extraction via Gemini Vision
│   ├── history.php          # GET/POST: Generation history endpoints with ownership isolation
│   ├── projects.php         # GET/POST: Saved projects CRUD (list, get, create, update, duplicate, rename, delete)
│   ├── templates.php        # GET: Curated starter templates loader and category filter
│   ├── i18n.php             # GET: Dynamic bilingual client translations dictionary
│   ├── proxy-image.php      # GET: SSRF-protected CORS-safe image proxy for canvas rendering
│   └── upload-image.php     # POST: Secure image uploader (5MB limit, MIME verification)
├── templates/               # 10 Bilingual starter templates (JSON)
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
├── includes/                # Server-side business logic & security
│   ├── db.php               # PDO connection pool (utf8mb4, strict prepared statements)
│   ├── security.php         # CSRF protection, secure sessions, input sanitization, i18n
│   ├── auth.php             # User accounts, secure auth, password reset, guest migration
│   ├── history.php          # Generation history recorder (error-resilient) & pruner
│   ├── rate-limiter.php     # MySQL-backed IP rate limiting (10/min, 100/day)
│   ├── sanitizer.php        # Strict AI JSON schema validator, clamping, font whitelist
│   └── ai-client.php        # Google Gemini API client with fallback & retry support
├── lang/                    # Bilingual translation dictionaries
│   ├── en.json / en.php     # English dictionary (257+ keys)
│   └── ar.json / ar.php     # Arabic dictionary (257+ keys)
├── database/
│   ├── schema.sql           # Database schema (tables, foreign keys, indexes)
│   └── migration_phase_1_5.sql
├── storage/
│   ├── logs/                # Daily application, auth, and error logs
│   └── uploads/             # User image uploads
├── assets/
│   ├── css/
│   │   └── style.css        # Apple Liquid Glass CSS design system with Dark/Light themes
│   └── js/
│       ├── app.js           # Main ES6 application bootstrap
│       └── editor/          # Modular Fabric.js canvas components
│           ├── canvas-manager.js    # Responsive canvas scaling & grid
│           ├── history-manager.js   # 50-step undo/redo stack
│           ├── font-manager.js      # Google Fonts loader (Cairo, Poppins, etc.)
│           ├── ai-renderer.js       # JSON to Fabric.js deserializer
│           ├── layers-panel.js      # Element ordering, visibility, locking, deletion
│           ├── properties-panel.js  # Contextual object inspector
│           ├── toolbar.js           # Tools (Text, Shapes, Images, Icons)
│           ├── shortcuts.js         # Hotkey controller (?, Ctrl+Z, Ctrl+C/V/D, etc.)
│           ├── project-manager.js   # Cloud MySQL project saves & local autosave
│           ├── export-manager.js    # Multi-resolution PNG, JPG, SVG, JSON export
│           ├── theme-manager.js     # Dark/Light theme manager with persistent state
│           ├── toast-manager.js     # Floating glass notification engine
│           └── ai-bar.js            # Bottom floating conversational AI command bar
├── index.php                # Main Studio Canvas Editor
├── projects.php             # "My Designs" project gallery
├── templates.php            # Curated Templates gallery
├── about.php                # About & Help Guide page
├── 404.php                  # Custom Liquid Glass 404 page
├── register.php             # User registration
├── login.php                # User login
├── logout.php               # Session termination
├── profile.php              # User profile & password settings
├── history.php              # Generation history log
├── install.php              # Automatic database setup wizard
├── diagnostics.php          # System health check bench
└── .htaccess                # Apache security rules & protection
```

---

## 2. Prerequisites & Required PHP Extensions

The application requires **PHP 8.0 or newer** and a **MySQL 5.7+ / MariaDB 10.3+** database.

Ensure the following PHP extensions are enabled in your `php.ini`:
- `pdo_mysql`: Database communication via PDO.
- `curl`: Outgoing HTTPS requests to the Gemini API.
- `mbstring`: Multibyte string support for Arabic text handling.
- `fileinfo`: MIME type verification for image uploads.
- `json`: JSON encoding and decoding (built into PHP 8+).
- `session`: Native PHP session management.

In Laragon, these extensions are enabled by default. On cPanel or shared hosting, you can toggle them under **Select PHP Version** -> **Extensions**.

---

## 3. Getting a Free Google Gemini API Key

AI Design Studio utilizes Google's Gemini Flash model, which offers a generous **free tier**:

1. Visit [Google AI Studio](https://aistudio.google.com/).
2. Sign in with any standard Google account.
3. Click **Get API key** in the top navigation bar.
4. Click **Create API key** (create in a new or existing Google Cloud project).
5. Copy the generated API key (it begins with `AIzaSy...`).
6. Paste the key into your `config.php` under `GEMINI_API_KEY`.

> [!TIP]
> Google provides 15 Requests Per Minute (RPM) completely free for Gemini 2.5 Flash, which is more than enough for design generation and iteration.

---

## 4. Local Setup on Windows with Laragon & HeidiSQL

Laragon provides an isolated, zero-configuration WAMP environment on Windows:

1. **Clone or Copy Files:**
   Place the project folder inside your Laragon web root:
   ```
   C:\laragon\www\ai-design-studio
   ```

2. **Create Database in HeidiSQL:**
   - Launch Laragon and click **Start All**.
   - Click the **Database** button in Laragon to open HeidiSQL.
   - Right-click in the left database tree -> **Create new** -> **Database**.
   - Name: `ai_design_studio`.
   - Collation: `utf8mb4_unicode_ci` (critical for Arabic typography).
   - Click **OK**.

3. **Configure Environment:**
   - In `C:\laragon\www\ai-design-studio`, duplicate `config.example.php` and rename it to `config.php`.
   - Open `config.php` and verify:
     ```php
     define('DB_HOST', '127.0.0.1');
     define('DB_PORT', 3306);
     define('DB_NAME', 'ai_design_studio');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('GEMINI_API_KEY', 'your_actual_gemini_api_key_here');
     ```

4. **Run Automated Installer:**
   - Open your browser and navigate to:
     ```
     http://localhost/ai-design-studio/install.php
     ```
   - Click **Run Installation / Upgrade**. All tables (`users`, `projects`, `generations`, `password_resets`, `rate_limits`) will be created automatically.
   - For security, delete `install.php` after successful setup.

5. **Access Studio:**
   - Open `http://localhost/ai-design-studio/` to start designing!

---

## 5. Deploying to Free Shared Hosting (InfinityFree, ByetHost, cPanel)

AI Design Studio is engineered to run seamlessly on restricted free shared hosting with **no shell access**, **no Composer**, and **no custom php.ini**:

1. **Prepare Database on Hosting cPanel:**
   - Log in to your hosting control panel (VistaPanel / cPanel).
   - Navigate to **MySQL Databases**.
   - Create a new database (e.g., `epiz_12345678_design_studio`).
   - Note the **MySQL Hostname** provided by your host (e.g., `sql200.epizy.com` or `127.0.0.1`), username, and password.

2. **Upload Files via FTP / File Manager:**
   - Connect via FileZilla or the web File Manager.
   - Upload the entire contents of `ai-design-studio` into your web root directory (usually `htdocs/` or `public_html/`).

3. **Update `config.php`:**
   - Rename `config.example.php` to `config.php` on the server.
   - Enter your hosting MySQL credentials:
     ```php
     define('DB_HOST', 'sql200.epizy.com'); // From your cPanel
     define('DB_NAME', 'epiz_12345678_design_studio');
     define('DB_USER', 'epiz_12345678');
     define('DB_PASS', 'your_cpanel_password');
     define('GEMINI_API_KEY', 'your_gemini_api_key');
     define('DEBUG', false);
     ```

4. **Set Directory Permissions:**
   Ensure `storage/logs/` and `storage/uploads/` are writable (chmod `755` or `777` if required by your host).

5. **Initialize Database:**
   - Visit `http://your-domain.com/install.php` in your browser.
   - Once completed, delete `install.php` via FTP or File Manager.

---

## 6. Configuration Reference (`config.php`)

| Constant | Description | Default |
|---|---|---|
| `DB_HOST` | MySQL hostname | `127.0.0.1` |
| `DB_PORT` | MySQL connection port | `3306` |
| `DB_NAME` | Database name | `ai_design_studio` |
| `DB_USER` | Database username | `root` |
| `DB_PASS` | Database password | `''` |
| `DB_CHARSET`| Database character set | `utf8mb4` |
| `GEMINI_API_KEY` | Google AI Studio API Key | `''` |
| `GEMINI_MODEL` | Primary Gemini model | `gemini-2.5-flash` |
| `HUGGINGFACE_API_TOKEN` | Hugging Face Inference API user token | `''` (optional) |
| `HUGGINGFACE_RMBG_MODEL` | Hugging Face background removal model | `briaai/RMBG-1.4` |
| `POLLINATIONS_ENABLED` | Enable Pollinations.ai hybrid AI background art | `true` |
| `RATE_LIMIT_PER_MINUTE` | AI API requests per minute per IP | `10` |
| `RATE_LIMIT_PER_DAY` | AI API requests per day per IP | `100` |
| `DIAGNOSTICS_SECRET` | Secret token to access `diagnostics.php` | Random secret string |
| `DEBUG` | Enable verbose errors and reset simulation | `false` |

---

## 7. Core Features & Liquid Glass Design

### 💎 Apple-Style Liquid Glass UI
- **Translucent Floating Panels:** Engineered with `backdrop-filter: blur(24px) saturate(175%)`, 1px subtle gradient borders, and soft specular top highlights (`inset 0 1px 0 rgba(255,255,255,0.4)`).
- **GPU-Accelerated Mesh Orbs:** Soft drifting colored orbs creating atmospheric depth, automatically paused under `prefers-reduced-motion`.
- **Light & Dark Themes:** Seamless switching via the `#theme-toggle` button in the header, synchronized with system preferences and persisted across reloads.
- **60fps Drag Optimization:** Blur effects are disabled on panels during canvas element dragging (`body.canvas-interacting`) to guarantee fluid 60fps performance on laptops and low-spec hardware.

### 🌐 Native Bilingual Arabic/English & RTL
- Genuine bidirectional layout using CSS logical properties (`margin-inline-start`, `padding-inline`, `inset-inline-start`).
- Mirroring of directional icons and toolbar controls when switching to Arabic (`dir="rtl"`).
- Curated Google Fonts: **Cairo**, **Tajawal**, **Almarai**, **Amiri**, and **Reem Kufi** for Arabic; **Poppins**, **Montserrat**, **Playfair Display**, and **Roboto** for English.
- Pre-rendering font readiness checks prevent text clipping or layout shifts on the canvas.

### 🎨 Modular Fabric.js Canvas Studio
- **Vector Independence:** Every element returned by the AI (text, geometric shapes, icons, images) is an autonomous, transformable object.
- **Layers Panel:** Visual stacking order, drag-to-reorder, lock/unlock, show/hide, and rename elements.
- **Floating AI Command Bar:** Stationed at the bottom center of the canvas viewport for natural language canvas revisions (e.g., "Change background to warm amber", "Make title bolder").
- **Keyboard Shortcuts:** Press `?` to open the interactive shortcuts cheatsheet (Ctrl+Z undo, Ctrl+Y redo, Ctrl+C/V/D copy/paste/duplicate, arrow keys for nudging).

### ⚡ Multi-Format High-Res Export
- **PNG:** Crisp transparent or opaque export at 1x, 2x, or 3x scale.
- **JPG:** High-quality print-ready JPG with customizable compression and background fill.
- **SVG:** Scalable vector graphics export for production publishing.
- **JSON:** Complete project blueprint export and import for portable backup.

### 🖼️ Hybrid AI Canvas & Computer Vision (Phase 5)
- **Text-Free Hybrid AI Backgrounds (Pollinations.ai):** When "Generate AI background" is checked in the AI generator bar, the AI writes a dedicated prompt for an illustration containing **no letters, words, or logos**. The generated background is served through the SSRF-protected proxy (`/api/proxy-image.php`) to avoid tainted canvas errors, and locked at the base layer (`objects[0]`). All headings, shapes, and badges remain independently editable above it.
- **Separate Main Subject (Hugging Face RMBG-1.4):** Selecting any image layer surfaces the "Separate Main Subject" tool in the properties inspector. The backend sends the image to Hugging Face Inference API (`briaai/RMBG-1.4`) or the internal GD cutout engine, creating a transparent cutout as a new layer while preserving the original. Handles model cold starts (503), rate limits (429), and missing tokens (401) with friendly bilingual notifications.
- **Vision OCR Text Extraction (Gemini Vision):** Clicking "Extract Text from Image" sends the image to Gemini Vision with structured schema validation. The AI extracts all visible headlines, badges, and labels along with their relative coordinates, dimensions, font sizes, weights, and colors, instantly placing them onto the canvas as editable Textbox objects, with an optional mask over the underlying raster text.

---

## 8. Security & Authentication Architecture

1. **CSRF Protection:** Cryptographically secure tokens validated on every state-changing POST/PUT request.
2. **SSRF-Protected Image Proxy (`/api/proxy-image.php`):** Resolves external URLs while strictly blocking private, loopback (`127.0.0.1`, `localhost`), internal subnets (`10.0.0.0/8`, `192.168.0.0/16`), and non-standard ports to protect host infrastructure.
3. **Strict Input Sanitization & AI Clamping:**
   - All AI output passes through `includes/sanitizer.php`.
   - Dimensions are clamped between 200px and 4000px.
   - Text colors and fonts are validated against strict whitelists to prevent XSS.
4. **Session Hardening:** Native PHP cookies configured with `HttpOnly`, `SameSite=Lax`, and `Secure` over HTTPS, with session ID regeneration on login.
5. **Anonymous Guest Mode & Account Migration:** Guests work freely using an anonymous `guest_token`. Upon registration or login, their previous generation history and saved projects are automatically migrated to their permanent user account.

---

## 9. Troubleshooting & FAQ

### Q: Why do AI calls fail with a cURL error on my hosting?
**A:** Some free shared hosts (such as free-tier ByetHost or InfinityFree) restrict outgoing port 443 requests via cURL to external domains.
- Check `diagnostics.php?secret=YOUR_SECRET` to test external cURL connectivity.
- If outgoing cURL is blocked on your free host, consider hosting on a provider with open outgoing HTTP/HTTPS (like Render, Railway, or standard shared cPanel hosting).

### Q: Why do Arabic characters show up as `????` in the database?
**A:** Ensure your database and tables were created with `utf8mb4` character set and `utf8mb4_unicode_ci` collation. `install.php` handles this automatically.

### Q: The canvas looks blurred on high-DPI (Retina) screens?
**A:** Fabric.js automatically adjusts for `window.devicePixelRatio`. For crisp exports, select **2x** or **3x** scale in the Export Modal.

### Q: Where are application error logs located?
**A:** Detailed diagnostic logs are written to `storage/logs/` (e.g., `app.log`, `auth.log`, `ai.log`, `security.log`).

---

## 10. Automated Testing

Run the automated test suites from your PowerShell or bash terminal:

```powershell
# Phase 1: Core AI endpoints & schema sanitizer tests
php tests/test_suite.php

# Phase 1.5: Authentication, password hashing, and guest migration tests
php tests/test_phase_1_5.php

# Phase 2: Fabric.js editor unit tests & UI element bindings
php tests/test_phase_2.php

# Phase 3: Export formats, project saves, and starter templates tests
php tests/test_phase_3.php

# Phase 4: Full bilingual dictionary, RTL, and Liquid Glass tests
php tests/test_phase_4.php

# Phase 5: Hybrid Pollinations background, RMBG subject separation, and Vision OCR
php tests/test_phase_5.php
python tests/browser_test_phase_5.py
```

---

# 🇸🇦 الدليل الكامل باللغة العربية

## 1. نظرة عامة على النظام والمعمارية

منصة **AI Design Studio** هي تطبيق ويب فائق التطور وخفيف الوزن ثنائي اللغة (العربية والإنجليزية)، يتيح للمستخدمين توليد تصاميم جرافيكية عبر وصف الفكرة باللغة الطبيعية أو الاختيار من بين قوالب احترافية جاهزة.

يتم تحويل استجابة الذكاء الاصطناعي إلى عناصر متجهة مستقلة (نصوص، أشكال هندسية، صور، أيقونات) تُعرض داخل محرر كانفاس تفاعلي مبني بـ **Fabric.js**، مع واجهة مستخدم ثورية بنمط **الزجاج السائل (Liquid Glass)** المستوحى من أحدث لغات تصميم أبل، ودعم كامل وأصيل لاتجاه الكتابة من اليمين إلى اليسار (RTL) مع خطوط عربية مختارة بعناية.

المشروع مبني بالكامل باستخدام:
- **PHP 8+ نقي (Vanilla PHP):** بدون أي أطر عمل أو حزم Composer خارجية.
- **MySQL عبر PDO:** استعلامات مُجهزة (Prepared Statements) وترميز `utf8mb4`.
- **Vanilla JavaScript (ES6 Modules):** وحدات معيارية نقية خالية من أطر العمل الثقيلة.
- **Fabric.js 5.3.1:** للتعامل مع كائنات الكانفاس المتجهة.
- **CSS3 مع تأثيرات الزجاج السائل:** ألوان وتدرجات مدعومة بتسريع العتاد (GPU).

---

## 2. المتطلبات الأساسية وإضافات PHP المطلوبة

يعمل النظام على أي بيئة تدعم **PHP 8.0 أو أحدث** وقاعدة بيانات **MySQL 5.7+ / MariaDB 10.3+**.

تأكد من تفعيل إضافات PHP التالية في ملف `php.ini`:
- `pdo_mysql`: للاتصال الآمن بقاعدة البيانات عبر PDO.
- `curl`: لإرسال الطلبات المشفرة إلى واجهة Google Gemini API.
- `mbstring`: للتعامل الصحيح مع النصوص العربية المتعددة البايتات.
- `fileinfo`: للتحقق الآمن من أنواع ملفات الصور المرفوعة (MIME types).
- `json`: لمعالجة بيانات JSON (مضمنة افتراضياً في PHP 8).
- `session`: لإدارة جلسات المستخدمين وحمايتها.

> في برنامج Laragon، جميع هذه الإضافات مفعلة افتراضياً.

---

## 3. الحصول على مفتاح Google Gemini API مجاناً

يستخدم التطبيق نموذج **Gemini 2.5 Flash** فائق السرعة والمجاني بالكامل:

1. ادخل إلى بوابة [Google AI Studio](https://aistudio.google.com/).
2. سجّل الدخول بحساب Google الشخصي.
3. اضغط على زر **Get API key** في الشريط العلوي.
4. اضغط على **Create API key** لإنشاء المفتاح فوراً.
5. انسخ المفتاح الذي يبدأ بـ `AIzaSy...`.
6. ضع المفتاح في ملف `config.php` داخل الثابت `GEMINI_API_KEY`.

---

## 4. التشغيل المحلي عبر Laragon على ويندوز مع HeidiSQL

1. **نسخ ملفات المشروع:**
   ضع مجلد المشروع داخل مسار خادم Laragon:
   ```
   C:\laragon\www\ai-design-studio
   ```

2. **إنشاء قاعدة البيانات في HeidiSQL:**
   - افتح تطبيق Laragon واضغط على **Start All**.
   - اضغط على زر **Database** لفتح HeidiSQL.
   - انقر بالزر الأيمن على شجرة الخادم ثم اختر **Create new** -> **Database**.
   - اكتب اسم القاعدة: `ai_design_studio`.
   - اختر الترميز: `utf8mb4_unicode_ci` (ضروري جداً لدعم الحروف العربية).
   - اضغط **OK**.

3. **إعداد ملف التكوين (config.php):**
   - انسخ الملف `config.example.php` وقم بتسميته `config.php`.
   - تأكد من صحة البيانات:
     ```php
     define('DB_HOST', '127.0.0.1');
     define('DB_PORT', 3306);
     define('DB_NAME', 'ai_design_studio');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('GEMINI_API_KEY', 'مفتاح_gemini_هنا');
     ```

4. **تشغيل المثبت الآلي (install.php):**
   - افتح المتصفح وانتقل إلى الرابط:
     ```
     http://localhost/ai-design-studio/install.php
     ```
   - اضغط على زر **Run Installation / Upgrade**. سيتم إنشاء جميع الجداول (`users`, `projects`, `generations`, `password_resets`, `rate_limits`) بشكل تلقائي وآمن.
   - بعد انتهاء التثبيت، قم بحذف ملف `install.php` لزيادة الأمان.

5. **بدء الاستخدام:**
   - توجه إلى الرابط `http://localhost/ai-design-studio/` وابدأ في التصميم!

---

## 5. النشر على الاستضافات المجانية (InfinityFree و ByetHost و cPanel)

تم تصميم النظام ليعمل بكفاءة مطلقة على الاستضافات المشتركة المقيدة التي لا توفر وصولاً للطرفية (No SSH) ولا تسمح بتعديل `php.ini`:

1. **إنشاء قاعدة البيانات من لوحة تحكم الاستضافة (cPanel / VistaPanel):**
   - ادخل إلى قسم **MySQL Databases**.
   - أنشئ قاعدة بيانات جديدة (مثلاً: `epiz_12345678_studio`).
   - احفظ اسم الخادم المعطى من الاستضافة (مثل: `sql200.epizy.com`) واسم المستخدم وكلمة المرور.

2. **رفع الملفات عبر FTP أو مدير الملفات:**
   - اتصل عبر FileZilla أو استخدم مدير الملفات بالمتصفح.
   - ارفع محتويات المجلد داخل المجلد الرئيسي للموقع (`htdocs` أو `public_html`).

3. **تعديل ملف `config.php`:**
   - أعد تسمية `config.example.php` إلى `config.php` واكتب بيانات الاتصال:
     ```php
     define('DB_HOST', 'sql200.epizy.com'); // من لوحة التحكم
     define('DB_NAME', 'epiz_12345678_studio');
     define('DB_USER', 'epiz_12345678');
     define('DB_PASS', 'كلمة_مرور_الاستضافة');
     define('GEMINI_API_KEY', 'مفتاح_gemini_الخاص_بك');
     define('DEBUG', false);
     ```

4. **صلاحيات المجلدات:**
   - تأكد من أن المجلدين `storage/logs/` و `storage/uploads/` يملكان صلاحيات الكتابة (`755` أو `777`).

5. **تشغيل التثبيت:**
   - افتح الرابط `http://your-domain.com/install.php` في المتصفح.
   - بعد نجاح العملية، احذف `install.php`.

---

## 6. دليل إعداد ملف التكوين (config.php)

- `DB_HOST`: عنوان خادم MySQL (`127.0.0.1` للمحلي أو عنوان الخادم من cPanel).
- `DB_NAME`: اسم قاعدة البيانات.
- `DB_USER`: اسم مستخدم قاعدة البيانات.
- `DB_PASS`: كلمة مرور قاعدة البيانات.
- `DB_CHARSET`: ترميز الاتصال، مضبوط دائماً على `utf8mb4`.
- `GEMINI_API_KEY`: مفتاح واجهة الذكاء الاصطناعي من Google AI Studio.
- `GEMINI_MODEL`: النموذج الافتراضي، مضبوط على `gemini-2.5-flash`.
- `HUGGINGFACE_API_TOKEN`: مفتاح منصة Hugging Face لإزالة الخلفيات (اختياري).
- `HUGGINGFACE_RMBG_MODEL`: نموذج إزالة الخلفية المستخدم، مضبوط على `briaai/RMBG-1.4`.
- `POLLINATIONS_ENABLED`: تفعيل توليد الخلفيات الفنية الذكية الهجينة عبر Pollinations.ai (افتراضياً `true`).
- `RATE_LIMIT_PER_MINUTE`: الحد الأقصى للطلبات بالدقيقة لكل عنوان IP (افتراضياً 10).
- `RATE_LIMIT_PER_DAY`: الحد الأقصى للطلبات باليوم لكل عنوان IP (افتراضياً 100).
- `DIAGNOSTICS_SECRET`: رمز سري لحماية صفحة الفحص `diagnostics.php`.
- `DEBUG`: تفعيل وضع تصحيح الأخطاء (يُفضل تعطيله `false` على الخوادم الحية).

---

## 7. المميزات الرئيسية ونظام الزجاج السائل Liquid Glass

### 💎 واجهة الزجاج السائل الانسيابية (Apple Liquid Glass)
- ألواح عائمة شفافة ذات تأثير ضبابي متطور (`backdrop-filter: blur(24px) saturate(175%)`).
- إضاءات علوية حادة ناعمة وحواف زجاجية خفيفة بتدرجات لونية هادئة.
- كرات لونية متدرجة تسبح في الخلفية بسلاسة مع تسريع عتاد كرت الشاشة، وتتوقف تلقائياً عند تفعيل خيار تقليل الحركة في النظام.
- دعم الوضع الليلي والنهاري (Dark / Light Theme) مع زر التبديل الفوري وحفظ التفضيل في المتصفح.
- تحسين أداء السحب بمعدل **60 إطاراً في الثانية**: يتم تعطيل الفلاتر الثقيلة تلقائياً أثناء تحريك العناصر على الكانفاس لضمان سلاسة تامة.

### 🌐 دعم كامل وأصيل للغة العربية واتجاه RTL
- تخطيط حقيقي من اليمين إلى اليسار باستخدام الخصائص المنطقية في CSS (`margin-inline`, `padding-inline`).
- انعكاس تلقائي للأيقونات الاتجاهية عند التبديل إلى العربية.
- مجموعة خطوط عربية احترافية من Google Fonts: **كايرو (Cairo)**، **تجوال (Tajawal)**، **المراعي (Almarai)**، **أميري (Amiri)**، و**ريم كوفي (Reem Kufi)**.
- محاذاة وتشبيك سليم للحروف العربية مع دعم خاصية التحقق من اكتمال تحميل الخطوط قبل الرسم لتجنب تشوه الكانفاس.

### 🎨 محرر الكانفاس المتطور
- **عناصر متجهة مستقلة:** كل عنصر يتم توليده هو كائن منفصل يمكن تحريكه، تغيير أبعاده، تدويره، وتعديل خصائصه.
- **لوحة الطبقات (Layers Panel):** إعادة ترتيب العناصر بالسحب، القفل/إلغاء القفل، الإخفاء/الإظهار، وتغيير التسمية.
- **شريط التعديل الذكي العائم:** شريط مثبت أسفل الكانفاس لإعطاء أوامر تعديل للذكاء الاصطناعي بلغة طبيعية (مثلاً: "اجعل الخلفية تدرجاً ليلياً"، "كبر العنوان واجعله باللون الذهبي").
- **اختصارات لوحة المفاتيح:** اضغط `?` في أي وقت لعرض الاختصارات (تراجع Ctrl+Z، إعادة Ctrl+Y، نسخ ولصق Ctrl+C/V، تكرار Ctrl+D، تحريك بالأسهم).

### ⚡ تصدير متعدد الصيغ بدقة فائقة
- **PNG:** تصدير صور فائقة الوضوح مع خيار الخلفية الشفافة بمقاسات 1x و 2x و 3x.
- **JPG:** تصدير بجودة عالية للطباعة والويب مع ملء تلقائي للخلفية.
- **SVG:** تصدير رسومي متجه قابل للتحجيم لأي دقة بدون فقدان الجودة.
- **JSON:** تصدير وحفظ نسخة احتياطية من المشروع وإمكانية استيرادها لاحقاً.

### 🖼️ محرك التصميم الهجين والرؤية الحاسوبية (المرحلة 5)
- **توليد خلفيات فنية ذكية هجينة خالية من النصوص (Pollinations.ai):** عند تفعيل خيار "توليد خلفية ذكية" في شريط الذكاء الاصطناعي، يوجه النموذج لكتابة وصف فني دقيق لصورة خلفية خالية تماماً من أي حروف أو كلمات أو شعارات، وتوليدها وتقديمها عبر البروكسي الآمن `proxy-image.php` لمنع أخطاء التصدير وتثبيتها كطبقة سفلية مؤمنة (`objects[0]`). وتظل كافة النصوص والأشكال والعناوين حرة ومستقلة فوقها.
- **فصل العنصر الرئيسي وإزالة الخلفية (Hugging Face RMBG-1.4):** عند تحديد أي طبقة صورة في الكانفاس، يظهر زر "فصل العنصر الرئيسي". يقوم الخادم بالمعالجة عبر Hugging Face أو محرك GD الاحتياطي لإنتاج طبقة PNG شفافة كعنصر مستقل مع الإبقاء على الصورة الأصلية، والتعامل الذكي مع حالات بدء تشغيل النموذج (503) وتجاوز حدود الطلبات (429) برسائل تفاعلية ثنائية اللغة.
- **استخراج النصوص وتحويلها لكائنات كانفاس قابلة للتحرير (Gemini Vision OCR):** عند الضغط على "استخراج النصوص من الصورة"، يقوم نموذج Gemini Vision بتحليل الصورة ومسحها ضوئياً لاستخراج كافة الكتل النصية وتحديد مواقعها وأحجامها وأوزانها وألوانها بدقة، وتوليد عناصر Textbox حية ومباشرة فوق الصورة، مع إمكانية تغطية النصوص الأصلية بمستطيلات تمويه بيضاء.

---

## 8. معمارية الأمان والخصوصية وحسابات المستخدمين

1. **حماية CSRF:** توليد رموز مشفرة والتحقق منها مع كل طلب تعديل للبيانات.
2. **بروكسي الصور الآمن لمنع هجمات SSRF (`proxy-image.php`):** حظر كامل لعناوين IP المحلية والشبكات الداخلية لحماية الخادم المستضيف من الاستغلال.
3. **تطهير وتنقية استجابات الذكاء الاصطناعي:** فحص صارم لمخطط الـ JSON والتأكد من توافق المقاسات والألوان والخطوط مع القائمة البيضاء لمنع ثغرات XSS.
4. **حماية الجلسات:** كوكيز الجلسة مضبوطة بـ `HttpOnly` و `SameSite=Lax` مع تجديد معرف الجلسة فور تسجيل الدخول.
5. **وضع الزائر والترحيل الذكي للبيانات:** يمكن للزوار غير المسجلين التصميم بحرية تامة؛ وعند قيام الزائر بإنشاء حساب أو تسجيل الدخول، يتم ربط وتوريث جميع مشاريع وتاريخ تصاميمه السابقة بحسابه الجديد فوراً!

---

## 9. استكشاف الأخطاء وإصلاحها (Troubleshooting)

### س: تظهر رسالة خطأ cURL عند محاولة التوليد على الاستضافة المجانية؟
**ج:** بعض الاستضافات المجانية تغلق الاتصالات الخارجية عبر منفذ 443. يمكنك زيارة `diagnostics.php?secret=الرمز_السري` لفحص الاتصال. في حال كان المنفذ محظوراً من شركة الاستضافة، يُنصح بالنشر على استضافة تدعم الاتصال الخارجي.

### س: الحروف العربية تظهر على شكل علامات استفهام `???` في قاعدة البيانات؟
**ج:** تأكد من أن قاعدة البيانات وجداولها تستخدم الترميز `utf8mb4` وترتيب `utf8mb4_unicode_ci`. ملف `install.php` يضبط هذا تلقائياً.

### س: أين أجد سجلات الأخطاء؟
**ج:** يتم تسجيل تفاصيل العمليات والأخطاء في المجلد `storage/logs/` في ملفات منفصلة (`app.log`, `ai.log`, `auth.log`, `security.log`).

---

## 10. تشغيل حزم الاختبارات المؤتمتة

لاختبار وظائف المشروع والتأكد من سلامة كافة الوحدات البرمجية، نفّذ الأوامر التالية في الطرفية:

```powershell
# المرحلة 1: اختبار محرك الذكاء الاصطناعي ومطهر البيانات
php tests/test_suite.php

# المرحلة 1.5: اختبار نظام المصادقة وترحيل بيانات الزوار وتأمين الجلسات
php tests/test_phase_1_5.php

# المرحلة 2: اختبارات محرر Fabric.js وعناصر الكانفاس
php tests/test_phase_2.php

# المرحلة 3: اختبار التصدير وحفظ المشاريع وقوالب البداية
php tests/test_phase_3.php

# المرحلة 4: اختبار القواميس ثنائية اللغة وتوافق RTL ونظام الزجاج السائل
php tests/test_phase_4.php

# المرحلة 5: اختبار توليد الخلفيات الهجينة وفصل العناصر واستخراج النصوص بالرؤية الحاسوبية
php tests/test_phase_5.php
python tests/browser_test_phase_5.py
```

---

<div align="center">
  <p>صُنع بكل إتقان وشغف لدعم المحتوى والتصميم العربي الحديث 🚀</p>
  <p>Crafted with excellence for modern bilingual generative design.</p>
</div>
