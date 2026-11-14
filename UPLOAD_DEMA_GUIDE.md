# e-DEMA Upload & Verification Guide

## Step 1: Upload Files to Hosting

Upload `dist/e-dema-upload.zip` to your hosting `public_html` directory:

```bash
# Extract the zip on your hosting (or via cPanel File Manager)
unzip dist/e-dema-upload.zip -d public_html
```

**Files uploaded include:**
- `index.html`, `portal.html`, `dashboard.html`, `scanner.html`, `agenda.html`
- `students.html`, `reports.html`, `akun.html` (all with branding, favicon, footer)
- `assets/css/app.css` (teal/navy theme)
- `assets/js/ui.js`, `assets/js/dashboard.js`, `assets/js/api.js`, `assets/js/scanner.js`
- `assets/img/DEMAASLI.png` (logo)
- `api/` directory with all PHP files (including `data.php` with barcode_legacy support)
- `database/schema.mysql.sql` (with barcode_legacy column)
- `var/akun-guru.csv` (653 rows, passwords `12345678`)
- `var/akun-admin.csv` (staff passwords)
- `.htaccess`, `router.php` (security headers)
- `api/config.local.example.php` (configuration template)

## Step 2: Create Database & Import Schema

### Create MySQL Database:
1. Go to cPanel → Databases → MySQL Databases
2. Create new database named `e_dema` (or similar)
3. Create MySQL user and assign to database
4. Set password for the user

### Import Schema:
```bash
mysql -u [username] -p [database_name] < database/schema.mysql.sql
```

**Schema includes:**
- `barcode_legacy` column in `students` table (NULLABLE)
- Index `idx_students_barcode_legacy` for lookups
- All tables: users, students, schedules, attendances
- Foreign key constraints with ON DELETE CASCADE

## Step 3: Configure API

### Copy & Edit Config:
```bash
cp api/config.local.example.php api/config.local.php
```

### Edit `api/config.local.php` with your credentials:
```php
<?php
$dbHost = 'localhost';      // or your DB host
$dbName = 'e_dema';         // your database name
$dbUser = 'db_username';    // your DB user
$dbPass = 'db_password';    // your DB password

$appUrl = 'https://e-dema.com';  // your domain

// Email domain for auto-generated guru emails
$config['app']['email_domain'] = 'student.unida.gontor.ac.id';
```

## Step 4: Run AutoSSL & DNS

### Enable AutoSSL in cPanel:
1. Go to SSL/TLS → Manage AutoSSL
2. Select the domain `e-dema.com`
3. Click "Run AutoSSL" to provision the certificate

### DNS Setup (if not already done):
- In your domain panel (Nameservers: ns4–ns7.ipdienste.net):
  - A `@` → hosting IP address
  - A `www` → hosting IP address
- Wait for DNS propagation (can take 24-48 hours, or use Cloudflare if nameservers point there)

## Step 5: Verify Go-Live

### Run the verification script:
```bash
php tools/check-go-live.php --url=https://e-dema.com/
```

### Or with teacher login:
```bash
php tools/check-go-live.php --teacher=guru@student.unida.gontor.ac.id:12345678 --url=https://e-dema.com/
```

### Or with admin login (from var/akun-admin.csv):
```bash
php tools/check-go-live.php --admin=admin@e-dema.id:PASSWORD --url=https://e-dema.com/
```

### Or with write-test:
```bash
php tools/check-go-live.php --admin=admin@e-dema.id:PASSWORD --url=https://e-dema.com/ --write-test
```

## Expected Results After Successful Deployment

### 49 Checks Total (expanded from 47 for DNS/HTTPS):

**Koneksi & HTTPS:**
- ✅ HTTPS scheme
- ✅ HTTP → HTTPS redirect
- ✅ HSTS header present

**Halaman Publik:**
- ✅ `/` - e-DEMA, login-form
- ✅ `/portal.html` - Portal Mahasiswa Guru, qr-mount, Scanner
- ✅ `/scanner.html` - Scanner Kehadiran, qr-reader
- ✅ `/agenda.html` - Agenda Kegiatan (NO classes.html/Kelas)
- ✅ `/dashboard.html` - Dashboard Kehadiran (NO classes)
- ✅ `/students.html` - Data Mahasiswa Guru
- ✅ `/reports.html` - Laporan (NO f-class/Kelas)
- ✅ `/akun.html` - Akun

**Aset Statis:**
- ✅ `/assets/css/app.css` - text/css
- ✅ `/assets/js/api.js` - javascript
- ✅ `/assets/js/ui.js` - javascript
- ✅ `/assets/js/agenda.js` - javascript
- ✅ `/assets/js/scanner.js` - javascript
- ✅ `/assets/img/DEMAASLI.png` - png

**Proteksi Berkas Internal:**
- ✅ `/var/akun-guru.csv` - 403/404 (terblokir)
- ✅ `/var/akun-admin.csv` - 403/404 (terblokir)
- ✅ `/var/eabsensi.sqlite` - 403/404 (terblokir)
- ✅ `/api/config.local.php` - 403/404 (terblokir - crucial!)
- ✅ `/database/schema.mysql.sql` - 403/404 (terblokir)
- ✅ `/tools/install.php` - 403/404 (terblokir)
- ✅ `/api/lib/data.php` - 403/404 (terblokir)
- ✅ `/README.md` - 403/404 (terbatas, tidak sensitif)

**API Publik:**
- ✅ `auth.me` - HTTP 200, OK=true
- ✅ Token CSRF - ada (karakter)
- ✅ Cache-Control API - no-store
- ✅ Aksi tidak dikenal - HTTP 404 JSON
- ✅ Dashboard tanpa login - HTTP 401

**Akun Guru (--teacher=guru@student.unida.gontor.ac.id:12345678):**
- ✅ Login guru - HTTP 200, peran student
- ✅ Portal pribadi - HTTP 200, NIM tersedia
- ✅ Akses agenda - HTTP 200, agenda hari ini
- ✅ Detail agenda & roster - HTTP 200
- ✅ Dashboard khusus staf - HTTP 403 (harus 403)
- ✅ Data guru khusus staf - HTTP 403 (harus 403)
- ✅ Laporan khusus staf - HTTP 403 (harus 403)

**Akun Admin (--admin=admin@e-dema.id:PASSWORD):**
- ✅ Login admin - HTTP 200, peran admin
- ✅ Dashboard admin - HTTP 200, ringkasan tersedia
- ✅ Daftar akun staf - HTTP 200, >= 2 akun
- ✅ Data mahasiswa guru - HTTP 200, >= 1 guru
- ✅ Rekap & laporan - HTTP 200
- ✅ Simpan agenda (write test) - HTTP 200
- ✅ Hapus agenda (write test) - HTTP 200

## Barcode Scanning Verification

### Test Both Old (PMDG-) and New (DEMA-) Codes:

**Via Scanner Camera:**
1. Open `/scanner.html` on `https://e-dema.com`
2. Grant camera access
3. Scan **DEMA-001** through **DEMA-652** → Should record attendance
4. Scan **PMDG-001-A1** through **PMDG-GRD07001-15C2** → Should also work via `barcode_legacy` column

**Via API Direct Test:**
```php
// Test old PMDG code scanning
$student = student_by_barcode('PMDG-001-A1');
 // Should return student record if barcode_legacy is populated

// Test new DEMA code scanning  
$student = student_by_barcode('DEMA-001');
 // Should return student record via barcode_uuid
```

### Migration Already Done Locally:
- 652 teachers migrated: `PMDG-...` → `DEMA-001` through `DEMA-652`
- Old codes stored in `barcode_legacy` column for backward compatibility
- Both formats supported in `student_by_barcode()` and `scan_barcode()` functions

## Troubleshooting

### If barcode scanning fails after DNS:
1. Verify `barcode_legacy` column exists: `SHOW COLUMNS FROM students LIKE 'barcode_legacy'`
2. Verify data: `SELECT barcode_uuid, barcode_legacy FROM students LIMIT 5`
3. Check API: `GET /api/index.php?action=student_by_barcode&barcode=PMDG-001-A1`
4. Ensure database has `barcode_legacy` values populated (migrate-barcode.php ran locally)

### If HTTPS/SSL issues:
- Ensure AutoSSL completed successfully
- Wait for DNS propagation if using custom domain
- Verify cert is not expired in cPanel → SSL/TLS → Manage SSL

### If 403 on internal files:
- Check `.htaccess` rewrite rules are active
- Ensure `router.php` HSTX/X-Frame-Headers are configured
- Verify `config.local.php` has correct DB credentials

## Rollback

If issues arise after deployment:
1. Restore from `dist/e-dema-upload.zip` backup
2. Re-import original schema if needed
3. Use Cloudflare Tunnel temporarily: `https://peer-damaged-explain-priced.trycloudflare.com`
4. Re-run DNS verification with `php tools/check-go-live.php`

---
*Generated for e-DEMA go-live deployment*