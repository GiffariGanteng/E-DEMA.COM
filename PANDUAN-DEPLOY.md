# Panduan Deploy e-DEMA ke https://e-dema.com/

Panduan ini menjelaskan langkah lengkap memindahkan aplikasi dari komputer lokal
(`http://127.0.0.1:8080`) ke hosting **PHP 8.1+ / MySQL 8** dengan alamat resmi
**https://e-dema.com/** dan HTTPS aktif.

> Kondisi 12 Nov 2026: `e-dema.com` mengarah ke `78.46.250.213` (server pihak lain)
> dan dialihkan ke `https://www.e-dema.de/`. Ganti record A domain ke IP hosting
> Anda dan hapus redirect lama. Langkah 1–3 di bawah harus diselesaikan lebih dulu
> di panel DNS/registrar dan hosting.

---

## 0. Yang dibutuhkan

| Kebutuhan | Keterangan |
| --- | --- |
| Domain | `e-dema.com` sudah terdaftar di registrar (Namecheap, Cloudflare, Rumahweb, dll.) |
| Hosting | Shared hosting cPanel dengan **Terminal/SSH**, atau VPS (Ubuntu + Nginx/Apache) |
| PHP | 8.1–8.4, ekstensi `pdo_mysql`, `mbstring`, `zip`, `openssl`, `session`, `json` |
| Database | MySQL 8 / MariaDB 10.4+ (1 database + 1 user) |
| HTTPS | AutoSSL cPanel, Let's Encrypt, atau Cloudflare |

**Penting:** folder aplikasi harus menjadi *document root* (web root). Jangan
menaruhnya di subfolder kecuali Anda menyesuaikan semua tautan.

---

## 1. Arahkan domain ke hosting

### Opsi A — Shared hosting / cPanel (paling mudah)

1. Catat **IP server** dari email welcome hosting (mis. `103.xx.xx.xx`).
2. Buka pengaturan DNS di registrar domain.
3. Buat record:

   | Tipe | Nama | Nilai | TTL |
   | --- | --- | --- | --- |
   | A | `@` | IP server hosting | 3600 |
   | CNAME | `www` | `e-dema.com` | 3600 |

4. Tunggu propagasi 5 menit–24 jam. Cek dengan:

   ```bash
   nslookup e-dema.com
   ```

5. Di cPanel, tambahkan domain `e-dema.com` sebagai **Addon Domains** atau
   **Primary Domain**, arahkan document root ke folder tempat aplikasi diunggah
   (mis. `public_html`).

### Opsi B — Cloudflare (DNS + HTTPS)

1. Tambahkan domain ke Cloudflare, lalu ganti nameserver di registrar ke nameserver
   yang diberikan Cloudflare.
2. Buat record **A** `@` → IP server dan **CNAME** `www` → `e-dema.com`; aktifkan
   proxy (awan oranye).
3. Atur SSL/TLS mode **Full (strict)** setelah sertifikat di server aktif
   (jangan pakai Flexible untuk aplikasi login).

### Opsi C — VPS

1. Arahkan A record `@` ke IP VPS, `www` CNAME ke `e-dema.com`.
2. Pasang Nginx + PHP-FPM + MySQL (contoh Ubuntu):

   ```bash
   sudo apt update
   sudo apt install nginx mysql-server php-fpm php-mysql php-mbstring php-zip unzip
   ```

---

## 2. Buat database MySQL

### cPanel

1. **MySQL Databases** → buat database, mis. `demauser_edema`.
2. Buat user database + password kuat.
3. Tambahkan user ke database dengan **ALL PRIVILEGES**.
4. Catat: nama database, username, password, host (`localhost`).

### VPS (terminal MySQL)

```sql
CREATE DATABASE edema CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'edema'@'localhost' IDENTIFIED BY 'PASSWORD_KUAT_ANDA';
GRANT ALL PRIVILEGES ON edema.* TO 'edema'@'localhost';
FLUSH PRIVILEGES;
```

---

## 3. Unggah aplikasi

Unggah isi proyek ke document root, **kecuali**:

- `.runtime/` (PHP portabel Windows, tidak dipakai di server)
- isi `var/` (database SQLite, CSV kredensial lokal, log)
- `api/config.local.php` (dibuat baru di server)
- `.git/`

Cara umum:

```bash
# dari komputer lokal (rsync)
rsync -av --exclude='.runtime' --exclude='var/*' --exclude='api/config.local.php' \
  ./ user@server:/var/www/e-dema/
```

Atau gunakan **File Manager cPanel → Upload** dalam bentuk ZIP, lalu Extract.
Struktur akhir di server harus seperti:

```
index.html
portal.html
scanner.html
agenda.html
dashboard.html
students.html
reports.html
akun.html
api/            (config.php, index.php, lib/)
assets/
database/
tools/
var/            (kosong / hanya log)
.htaccess
```

Buat folder `var/` dan pastikan dapat ditulis:

```bash
chmod 775 var
chmod 644 api/config.php database/*.sql
```

Jangan memakai `chmod 777`.

---

## 4. Buat `api/config.local.php`

Buat berkas baru `api/config.local.php` di server (jangan diunggah dari laptop):

```php
<?php
return [
    'driver' => 'mysql',
    'mysql' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'demauser_edema',
        'username' => 'demauser_edema',
        'password' => 'PASSWORD_KUAT_ANDA',
        'charset'  => 'utf8mb4',
    ],
    'app' => [
        'url' => 'https://e-dema.com/',
    ],
];
```

Berkas ini sudah diblokir dari akses web oleh `.htaccess` dan `router.php`.
Jangan pernah meng-commit berkas ini ke Git.

---

## 5. Pasang skema database

### Jika hosting punya Terminal / SSH (disarankan)

```bash
cd /home/USER/public_html
php tools/install.php --no-seed
php tools/reset-staff.php
```

- `--no-seed` = pasang tabel saja, **tanpa data contoh** (disarankan untuk produksi).
- `reset-staff.php` membuat akun `admin@e-dema.id` dan `operator@e-dema.id`
  dengan password acak, lalu menuliskannya ke `var/akun-admin.csv`.

Catat password dari `var/akun-admin.csv`, lalu **hapus berkas itu** setelah disimpan
di tempat aman.

### Jika tidak ada SSH (hanya cPanel)

1. Buka **phpMyAdmin** → pilih database → tab **Import** →
   unggah `database/schema.mysql.sql` → **Go**.
2. Buka **Cron Jobs** → tambahkan satu jadwal sekali jalan:

   ```bash
   /usr/local/bin/php /home/USER/public_html/tools/reset-staff.php
   ```

   Tunggu satu menit, lalu **hapus cron** tersebut.
3. Ambil `var/akun-admin.csv` lewat File Manager, simpan, lalu hapus.

---

## 6. Isi data 652 mahasiswa guru

Database MySQL baru masih kosong. Data guru harus dimasukkan ulang dari dua berkas
Excel asli (Gorda 1 dan Gontor 2).

1. Unggah kedua berkas Excel ke `var/` (folder ini tidak bisa diakses dari web).
2. Jalankan (SSH/Terminal):

   ```bash
   php tools/import-xlsx.php --gorda=/home/USER/public_html/var/GORDA.xlsx \
       --gontor2="/home/USER/public_html/var/GONTOR 2.xlsx" --apply
   ```

   > `--apply` menghapus data guru lama berkode `GRD-*` beserta kehadirannya,
   > lalu menggantinya dengan roster baru. Jalankan tanpa `--apply` dulu untuk
   > melihat laporan simulasi.

3. Buat akun login semua guru dengan password awal `12345678`:

   ```bash
   php tools/create-accounts.php --reset --password=12345678
   ```

   Hasil: `var/akun-guru.csv` berisi 652 email + password. Bagikan secara pribadi,
   lalu hapus berkas dari server setelah dicatat.

4. Hapus berkas Excel dari `var/` setelah impor selesai.

5. Jika database masih memakai kode barcode lama `PMDG-...`, migrasikan ke
   `DEMA-001`, `DEMA-002`, dst. (kode lama tetap berlaku untuk QR yang sudah dicetak):

   ```bash
   php tools/migrate-barcode.php --dry-run
   php tools/migrate-barcode.php
   ```

   Skrip hanya mengubah `barcode_uuid`, menyimpan kode lama di `barcode_legacy`,
   dan tidak menyentuh kehadiran/akun. Buat `mysqldump` terlebih dahulu.

Jika tidak ada SSH, jalankan langkah 2, 3, dan 5 lewat **Cron Jobs** (satu perintah per
jadwal), lalu hapus cron setelah selesai.

---

## 7. Atur web server

### Apache / cPanel

`.htaccess` bawaan sudah:

- mematikan directory listing,
- memblokir `/var`, `/database`, `/tools`, `/api/lib`,
- memblokir berkas `.sql`, `.csv`, `.log`, `.md`, dan `api/config.local.php`.

Pastikan `mod_rewrite` dan `AllowOverride All` aktif (biasanya sudah di shared hosting).

### Nginx (VPS)

```nginx
server {
    listen 80;
    server_name e-dema.com www.e-dema.com;
    root /var/www/e-dema;
    index index.html;

    location ~ ^/(var|database|tools|api/lib)/ { deny all; }
    location = /api/config.local.php { deny all; }

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

Aktifkan lalu muat ulang:

```bash
sudo ln -s /etc/nginx/sites-available/e-dema /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## 8. Aktifkan HTTPS

### cPanel

**SSL/TLS Status** → pilih domain → **Run AutoSSL**. Tunggu sampai status *Valid*.
Aktifkan **Force HTTPS Redirect**.

### VPS dengan Let's Encrypt

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d e-dema.com -d www.e-dema.com
sudo systemctl enable --now certbot.timer
```

Sertifikat diperbarui otomatis. Uji di browser: harus muncul ikon gembok dan
`http://` otomatis dialihkan ke `https://`.

> Kamera scanner hanya berfungsi di HTTPS atau `localhost`. Setelah HTTPS aktif,
> scanner di HP akan berfungsi normal.

---

## 9. Uji setelah go-live

Jalankan dari komputer mana pun:

```bash
curl -I https://e-dema.com/
curl -I https://e-dema.com/portal.html
curl -I https://e-dema.com/scanner.html
curl -o /dev/null -s -w "%{http_code}\n" https://e-dema.com/var/akun-guru.csv
curl -o /dev/null -s -w "%{http_code}\n" https://e-dema.com/tools/install.php
curl -o /dev/null -s -w "%{http_code}\n" https://e-dema.com/api/config.local.php
```

Hasil yang benar:

| URL | Status |
| --- | --- |
| `/`, `/portal.html`, `/scanner.html` | `200` |
| `/var/akun-guru.csv`, `/tools/install.php`, `/api/config.local.php` | `404` atau `403` |

### Pemeriksaan otomatis

Skrip `tools/check-go-live.php` memeriksa 40+ hal sekaligus: status halaman,
penanda HTML, aset CSS/JS, proteksi `var/`/`database/`/`tools/`, JSON API,
penolakan akses tanpa login, login guru, portal, hak akses, serta login admin.

```bash
php tools/check-go-live.php --url=https://e-dema.com/ \
  --teacher=guru@student.unida.gontor.ac.id:12345678 \
  --admin=admin@e-dema.id:PASSWORD_ADMIN
```

- Tanpa `--teacher`/`--admin`, bagian tersebut dilewati dengan status SKIP.
- Tambahkan `--write-test` untuk menguji simpan **dan hapus** satu agenda uji
  (agenda otomatis dihapus kembali; default tidak menulis apa pun).
- Kode keluar `0` = tidak ada FAIL, `1` = ada masalah, `2` = kesalahan pemakaian.
- Jalankan juga saat domain masih HTTP lokal:
  `php tools/check-go-live.php --url=http://127.0.0.1:8080 --teacher=...`
  (pemeriksaan HTTPS akan berstatus WARN).

Checklist manual:

- [ ] Login guru (email dari `var/akun-guru.csv`, password `12345678`) → masuk portal.
- [ ] Portal menampilkan NIM, kehadiran, status tahfidz, jumlah pelanggaran, QR.
- [ ] Login `admin@e-dema.id` → dashboard, Data Guru, Agenda Kegiatan, Akun terbuka.
- [ ] Tambah agenda di **Agenda Kegiatan**, lalu buka **Scanner** dari akun guru.
- [ ] Scan QR guru → status tercatat; scan ulang → pesan "sudah tercatat".
- [ ] Rekap & Laporan tampil dan ekspor CSV/Excel berhasil.
- [ ] Di HP: buka `https://e-dema.com/scanner.html`, izinkan kamera.

---

## 10. Keamanan dan perawatan

1. **Ganti password `12345678`** setelah masa orientasi. Gunakan:

   ```bash
   php tools/create-accounts.php --reset
   ```

   Password acak baru akan ditulis ke `var/akun-guru.csv`.

2. Simpan `var/akun-admin.csv` dan `var/akun-guru.csv` di tempat aman, lalu hapus
   dari server.
3. Jangan menaruh `api/config.local.php` di Git atau membagikannya.
4. Backup rutin:
   - database: `mysqldump -u USER -p DATABASE > backup-$(date +%F).sql`
   - berkas: arsipkan document root (tanpa `var/*.csv`).
5. Periksa `var/php-error.log` bila ada masalah; log ini tidak dapat diakses publik.
6. Perbarui PHP dan MySQL mengikuti dukungan hosting.

---

## 11. Troubleshooting

| Gejala | Penyebab umum | Solusi |
| --- | --- | --- |
| Halaman putih / HTTP 500 | Error PHP atau permission | Cek `var/php-error.log` dan error log hosting; pastikan `var/` writable |
| "Tidak dapat terhubung ke basis data" | Kredensial salah / `pdo_mysql` mati | Periksa `api/config.local.php`; aktifkan ekstensi `pdo_mysql` |
| "Basis data belum disiapkan" | Skema belum dipasang | Jalankan `php tools/install.php` (atau impor `database/schema.mysql.sql`) |
| 419 "Sesi kedaluwarsa" | Cookie sesi tidak tersimpan | Pastikan HTTPS, `session.save_path` writable, domain tidak berubah-ubah (www vs non-www) |
| Login berhasil tapi kembali ke halaman login | Cookie `secure` / domain | Paksa satu domain (https://e-dema.com), aktifkan HTTPS penuh |
| 403 di semua halaman | ModSecurity / permission | Nonaktifkan aturan yang mencurigakan di cPanel; set file 644, folder 755 |
| `/var` atau `/tools` bisa dibuka | `.htaccess` tidak dibaca | Pastikan Apache + `AllowOverride`; untuk Nginx tambahkan blok `deny` |
| Kamera tidak muncul | Bukan HTTPS | Buka lewat `https://e-dema.com/` |
| Grafik/QR tidak tampil | CDN diblokir jaringan | Izinkan `cdnjs.cloudflare.com`, `cdn.jsdelivr.net`, `unpkg.com`, Google Fonts |
| Impor Excel gagal | Ekstensi `zip` mati / nama berkas salah | Aktifkan `php-zip`; gunakan path lengkap dan tanda kutip untuk nama ber-spasi |

---

## 12. Rollback

Jika deployment bermasalah:

1. Kembalikan berkas dari backup ke document root.
2. Impor kembali dump database terakhir.
3. Jika memakai Cloudflare, nonaktifkan proxy sementara atau kembalikan DNS ke
   server lama.
4. Setelah stabil, ulangi langkah 8–9.

---

## Ringkasan perintah inti (SSH)

```bash
cd /home/USER/public_html
# 1) konfigurasi database dibuat manual di api/config.local.php
php tools/install.php --no-seed
php tools/reset-staff.php
php tools/import-xlsx.php --gorda=var/GORDA.xlsx --gontor2="var/GONTOR 2.xlsx" --apply
php tools/create-accounts.php --reset --password=12345678
cat var/akun-admin.csv
# 2) simpan kredensial, lalu hapus berkas CSV dari server
rm -f var/GORDA.xlsx "var/GONTOR 2.xlsx"
```

Setelah itu buka **https://e-dema.com/** dan jalankan checklist langkah 9.
