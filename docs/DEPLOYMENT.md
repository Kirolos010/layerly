# Production Deployment Guide

This guide describes how to securely deploy AI Design Studio to a production server (Apache, Nginx, or cPanel / Shared Hosting).

---

## 1. Web Server Configuration

### Apache (`.htaccess`)
The project includes a root `.htaccess` with:
- `mod_rewrite` enabled for clean URLs (`/login`, `/projects`, `/templates`, `/history`, etc.).
- 403 Forbidden access denial rules protecting sensitive directories and files:
  - `config.php`
  - `includes/`
  - `storage/` (direct file access blocked; uploads served via `api/upload.php`)
  - `database/`
  - `starter-templates/`
  - `lang/`
  - `tests/`
  - `tools/`
  - `docs/`
- Automatic 301 redirects from old `.php` file URLs to clean routes.

Ensure your Apache host configuration has:
```apache
<Directory /path/to/ai-design-studio>
    AllowOverride All
    Require all granted
</Directory>
```
And verify that `mod_rewrite` and `mod_headers` are enabled.

---

### Nginx (Alternative)
If using Nginx, configure clean URLs and access blocks in your server block:
```nginx
server {
    listen 80;
    server_name example.com;
    root /path/to/ai-design-studio;
    index index.php;

    # Protect sensitive paths
    location ~ ^/(config\.php|includes|storage|database|starter-templates|lang|tests|tools|docs) {
        deny all;
        return 403;
    }

    # Clean URL routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

---

## 2. Directory Permissions

Ensure the web server (e.g. `www-data` or cPanel user) has write access to the `storage/` directory:
```bash
chmod -R 775 storage/
chmod -R 775 storage/logs/
chmod -R 775 storage/uploads/
```

---

## 3. Environment & Configuration Security

1. **NEVER commit `config.php` to Git.**
2. On your production server, copy `config.example.php` to `config.php`:
   ```bash
   cp config.example.php config.php
   ```
3. Set your production database credentials:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
4. Set your AI provider API keys:
   - `GEMINI_API_KEY`
   - `HUGGINGFACE_API_KEY` (optional, for RMBG background removal)
   - `GROQ_API_KEY` (optional failover)
5. Set `APP_ENV` to `'production'` and set `BASE_URL` to match your deployment domain or subpath.
6. Set a strong `DIAGNOSTICS_SECRET` token to protect `admin/diagnostics.php`.

---

## 4. Database Migrations

Run database migrations via CLI:
```bash
php tools/migrate.php
```
Or execute `admin/install.php` once through the browser, then delete or protect `admin/install.php`.

---

## 5. Non-Deployment Folders

If packaging a lightweight release archive (tar/zip) for shared hosting or production deployment, the following directories can be excluded:
- `tests/` (automated test suite)
- `tools/` (CLI maintenance and migration scripts)
- `docs/` (documentation and screenshots)
- `.git/` (VCS metadata)
