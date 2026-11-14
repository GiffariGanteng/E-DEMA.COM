# e-DEMA — Sistem Manajemen Kehadiran (Barcode/QR)

Portal kehadiran **mahasiswa guru** dengan **backend PHP + MySQL** dan antarmuka admin
beridentitas **DEMA (Dewan Mahasiswa)** — sidebar navy, aksen teal, dan logo Student Council.
Peran admin/operator, portal pribadi guru, scanner kamera untuk semua guru, agenda kegiatan,
rekap + ekspor, dan kartu QR.

Alamat resmi: **https://e-dema.com/**

Dua jalur deploy tersedia:

- **Netlify Functions + Netlify Database** — lihat
  **[NETLIFY-DEPLOY.md](NETLIFY-DEPLOY.md)**. Hanya folder `public/` yang
  dipublikasikan; API PHP sudah diporting ke `netlify/functions/api.mjs`.
- **Hosting PHP + MySQL** — lihat **[PANDUAN-DEPLOY.md](PANDUAN-DEPLOY.md)**.

Panduan lengkap mengaktifkan domain, MySQL, dan HTTPS ada di
**[PANDUAN-DEPLOY.md](PANDUAN-DEPLOY.md)**.

## Teknologi

| Lapisan | Keterangan |
| --- | --- |
| Backend | PHP 8.1+ (diuji pada PHP 8.4), PDO prepared statements |
| Basis data | MySQL 8 / MariaDB 10.4+ (driver `pdo_mysql`), SQLite untuk mode demo lokal |
| Frontend | HTML + CSS + JavaScript vanilla, memanggil API JSON |
| Pustaka CDN | Chart.js, html5-qrcode, qrcodejs, Font Awesome, Inter |

## Struktur folder

```
api/
  index.php            Router API tunggal (?action=...), respons JSON
  config.php           Konfigurasi (MySQL default) + override config.local.php / env
  config.local.php     Konfigurasi lokal mesin ini (tidak untuk di-commit)
  lib/db.php           Koneksi PDO, helper query, cek tabel
  lib/http.php         Sesi, CSRF, JSON, validasi tanggal/jam
  lib/auth.php         Login/logout berbasis sesi + password_hash
  lib/data.php         Query & aturan bisnis (dashboard, scan, rekap, ekspor)
database/
  schema.mysql.sql     Skema MySQL 8 (dipakai produksi)
  schema.sqlite.sql    Skema SQLite (dev/uji)
tools/
  install.php          Pemasang skema + data contoh (CLI)
var/                   SQLite demo, log PHP (dibuat otomatis)
assets/, *.html        Halaman antarmuka
```

## 1. Menjalankan cepat (tanpa MySQL)

Mesin ini sudah disertakan PHP portabel di `.runtime/php`, dan `api/config.local.php`
diarahkan ke SQLite sehingga demo langsung jalan.

```bat
start.bat
```

Lalu buka http://127.0.0.1:8080/ (server PHP juga bisa dijalankan manual):

```bat
.runtime\php\php.exe -S 0.0.0.0:8080 -t .
```

`start.bat` mengikat ke `0.0.0.0`, jadi halaman juga dapat dibuka dari HP atau
komputer lain di Wi-Fi yang sama melalui `http://IP-komputer:8080/` (cek IP dengan
`ipconfig`). Windows Firewall mungkin meminta izin saat pertama kali dijalankan.
Pemindai kamera tetap memerlukan `localhost` atau HTTPS; dari HP lewat `http://IP`
kamera diblokir browser, gunakan input manual barcode.

Buat ulang / reset data demo:

```bat
.runtime\php\php.exe tools\install.php --driver=sqlite --fresh
```

## 2. Menjalankan dengan MySQL (XAMPP / Laragon)

1. Salin folder proyek ke `htdocs` (mis. `htdocs/eabsensi`), lalu jalankan Apache + MySQL.
2. Buat `api/config.local.php`:

   ```php
   <?php
   return [
       'driver' => 'mysql',
       'mysql' => [
           'host' => '127.0.0.1',
           'port' => 3306,
           'database' => 'eabsensi',
           'username' => 'root',
           'password' => '',
       ],
   ];
   ```

3. Dari terminal (folder proyek):

   ```bat
   php tools\install.php
   ```

   Skrip ini membuat database `eabsensi` bila belum ada, memasang
   `database/schema.mysql.sql`, lalu mengisi data contoh. Gunakan `--fresh`
   untuk menghapus tabel lama, atau `--no-seed` bila hanya ingin skema.
4. Buka `http://localhost/eabsensi/index.html`.

Kredensial juga dapat diberikan lewat environment (`DB_DRIVER`, `DB_HOST`, `DB_PORT`,
`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

## Akun resmi

| Peran | Email | Password |
| --- | --- | --- |
| Admin | `admin@e-dema.id` | lihat `var/akun-admin.csv` |
| Operator | `operator@e-dema.id` | lihat `var/akun-admin.csv` |

Password akun staf dibuat acak saat instalasi dan disimpan di `var/akun-admin.csv`.
Akun mahasiswa guru memakai email institusi dengan **password `12345678`**
dan daftar lengkapnya ada di `var/akun-guru.csv`. Tidak ada akun demo bawaan;
password disimpan dengan `password_hash()` (tidak ada plaintext di basis data).

## Fitur

- **Login & peran** — sesi PHP (`EABSID`, HttpOnly, SameSite=Lax), opsi "Ingat saya"
  30 hari, operator tidak melihat menu Data Master.
- **Dashboard** — KPI harian, donut komposisi status, grafik per kegiatan, progres agenda,
  pemindaian terbaru, dan daftar belum hadir dengan tandai-hadir.
- **Scanner** — kamera (`html5-qrcode`) + input manual, bunyi sukses/gagal, deteksi
  duplikat, toleransi terlambat 10 menit, penandaan izin/sakit. Dapat dibuka oleh
  **semua mahasiswa guru** (bukan hanya admin/operator).
- **Data Mahasiswa Guru** — direktori guru Tahun 1 sampai Last Team: kode `GRD-XX-NNN`,
  email institusi, NIM (boleh kosong), prodi, semester, tahun, kamar, daerah, kartu QR,
  cetak kartu massal, dan ekspor CSV. Kode, email, dan semester diisi otomatis oleh server
  bila dikosongkan; tombol **Lengkapi Otomatis** mengisi semua baris yang masih kosong.
- **Agenda Kegiatan** — CRUD agenda kegiatan per tanggal. Tidak ada pembagian kelas:
  satu agenda berlaku untuk seluruh mahasiswa guru, lengkap dengan shortcut scan.
- **Rekap & Laporan** — filter tanggal/kegiatan/status/kata kunci, ringkasan status,
  paginasi server, ekspor CSV & Excel, cetak/PDF.
- Status `alfa` tidak disimpan sebagai baris; dihitung dari peserta terjadwal yang
  belum memiliki catatan kehadiran.

## Endpoint API

Semua permintaan melalui `api/index.php?action=...`. Balasan: `{"ok":true,"data":...}`
atau `{"ok":false,"message":...,"code":...}`.

| Aksi | Metode | Keterangan |
| --- | --- | --- |
| `auth.me` | GET | Sesi aktif, token CSRF, tanggal server |
| `auth.login` / `auth.logout` | POST | Masuk / keluar |
| `dashboard` | GET | Ringkasan harian (`date`, `schedule_id`) |
| `absents` | GET | Peserta belum tercatat |
| `schedules.list` / `schedules.detail` | GET | Jadwal per tanggal / detail + roster |
| `schedules.save` / `schedules.delete` | POST | CRUD agenda kegiatan (admin) |
| `students.list` | GET | Daftar anggota (filter + paginasi) |
| `students.get` | GET | Detail anggota + statistik |
| `students.save` / `students.delete` | POST | CRUD anggota (admin) |
| `students.export` | GET | Unduh CSV guru/anggota (kode, NIM, email, prodi, semester, tahun, ...) |
| `students.options` | GET | Pilihan filter tahun, semester, prodi + domain email |
| `students.autofill` | POST | Isi otomatis kode, email, dan semester yang kosong (admin) |
| `scan` | POST | Catat kehadiran dari barcode |
| `attendance.mark` | POST | Tandai hadir/izin/sakit manual |
| `report` | GET | Rekap terpaginasi + ringkasan |
| `report.options` | GET | Opsi filter kegiatan (60 agenda terakhir) |
| `report.export` | GET | Unduh CSV/Excel (`format=csv\|xls`) |

Permintaan `POST` wajib menyertakan header `X-CSRF-Token` (diambil dari `auth.me`
atau respons login). `Content-Type: application/json`.

## Keamanan

- Semua query memakai **prepared statement** dengan parameter terikat.
- Password disimpan dengan `password_hash()` (bcrypt), verifikasi `password_verify()`.
- Sesi diperbarui (`session_regenerate_id`) saat login; cookie HttpOnly + SameSite=Lax.
- Token CSRF per sesi wajib untuk semua operasi tulis.
- Otorisasi per peran diperiksa **di server** (`require_admin()`), bukan hanya di UI.
- Respons JSON memakai `Cache-Control: no-store`; error PHP dicatat ke `var/php-error.log`
  dan tidak pernah bocor ke keluaran.
- Folder `database/` dan `var/` dilindungi `.htaccess` (Apache).

## Impor data guru (opsional)

Data guru dapat diimpor dari halaman "Data Mahasiswa Guru Gorda" (berkas HTML yang
memuat `const BASE = [...]`) atau dari berkas JSON berisi array record:

```bat
php tools/import-guru.php --file=gorda.html --dry-run   # simulasi
php tools/import-guru.php --file=gorda.html             # impor sungguhan
```

Impor bersifat idempoten (dicocokkan lewat kode, lalu NIM): data yang sudah ada
diperbarui, yang baru ditambahkan. Email dan kode yang kosong dibuat otomatis,
semester dihitung dari angkatan pada NIM, dan NIM yang belum keluar dibiarkan kosong
agar dapat diisi menyusul lewat halaman Data Guru.

Selisih NIM terhadap daftar Excel dapat diperiksa sebelum menulis apa pun:

```bat
php tools/sync-nim.php --file="C:\path\DATABASE.xlsx" --file="C:\path\NIM.xlsx"
php tools/sync-nim.php --file="C:\path\DATABASE.xlsx" --apply   :: tulis NIM yang cocok
```

Tanpa `--apply` skrip hanya melaporkan: NIM yang sudah sama, NIM yang bisa diisi,
NIM yang berbeda, nama ambigu, guru tanpa NIM yang tidak ada di Excel, serta baris
Excel tanpa pasangan. Laporan lengkap tersimpan di `var/nim-report.txt`.

### Kode barcode guru (DEMA-001)

Kode barcode baru berurutan: **DEMA-001, DEMA-002, dst.** Data lama yang masih
memakai format `PMDG-...` dapat dimigrasikan tanpa menghapus kehadiran atau akun:

```bat
php tools/migrate-barcode.php --dry-run   :: lihat rencana perubahan
php tools/migrate-barcode.php             :: jalankan migrasi
```

Migrasi hanya mengubah `barcode_uuid`; kode lama disimpan di kolom
`barcode_legacy`, sehingga **kartu/QR lama tetap bisa dipindai**. Database SQLite
otomatis dicadangkan ke `var/eabsensi.sqlite.bak-YYYYmmdd-Hisss` sebelum ditulis.
Untuk MySQL, buat `mysqldump` terlebih dahulu. Skrip aman dijalankan berulang.

### Portal pribadi guru

Setiap guru dapat memeriksa datanya sendiri di `portal.html`: kehadiran, NIM,
keterangan lulus tahfidzh, dan jumlah pelanggaran. Akun login dibuat dari email
guru yang tersimpan di database:

```bat
php tools/create-accounts.php              :: akun baru + CSV kredensial
php tools/create-accounts.php --reset      :: buat ulang password semua akun guru
php tools/create-accounts.php --reset --password=12345678  :: samakan semua password
```

Kredensial ditulis ke `var/akun-guru.csv` (kolom Email & Password). Peran akun
adalah `student` sehingga hanya bisa membuka portal dan scanner, serta tidak bisa
masuk ke halaman admin. Status tahfidz (Belum Lulus / Sedang Proses / Lulus) dan
jumlah pelanggaran diisi admin lewat form Data Guru.

Berkas `var/`, `database/`, dan `tools/` tidak boleh dapat diunduh. `start.bat`
memakai `router.php` yang memblokirnya; untuk Apache, `.htaccess` sudah disiapkan.

### Pemeriksaan go-live otomatis

Setelah aplikasi dideploy, verifikasi seluruh checklist dengan satu perintah:

```bash
php tools/check-go-live.php --url=https://e-dema.com/ \
  --teacher=guru@student.unida.gontor.ac.id:12345678 \
  --admin=admin@e-dema.id:PASSWORD_ADMIN
```

Skrip memeriksa halaman publik, aset, proteksi folder internal, API, login guru,
portal, pembatasan hak akses, dan login admin. Tambahkan `--write-test` untuk
menguji simpan + hapus agenda (default hanya membaca). Kode keluar `1` bila ada
pemeriksaan yang gagal. Detail opsinya: `php tools/check-go-live.php --help`.

### Alamat publik https://e-dema.com/

Alamat resmi aplikasi adalah **https://e-dema.com/**. Agar alamat ini aktif,
arahkan domain ke server yang menjalankan aplikasi ini (hosting PHP 8.1+ dan
MySQL, atau VPS), pasang sertifikat HTTPS, lalu salin berkas ke document root.
Pengaturan domain dilakukan di penyedia domain/hosting, bukan dari dalam aplikasi.

Untuk uji coba sementara di komputer lokal, tunnel Cloudflare dapat dipakai:

```bat
var\cloudflared.exe tunnel --url http://127.0.0.1:8080 --no-autoupdate
```

Alamat `https://….trycloudflare.com` bersifat sementara dan mati bila jendela
cloudflared ditutup; alamat itu bukan alamat resmi aplikasi.

## Catatan

- Akses kamera hanya berjalan pada `localhost` atau HTTPS.
- Ikon, grafik, QR, dan pustaka kamera dimuat dari CDN sehingga perlu internet;
  tanpa CDN aplikasi tetap berfungsi, hanya komponen tersebut tidak tampil.
- Zona waktu server: `Asia/Jakarta` (ubah di `api/config.php` → `app.timezone`).
- Data contoh dibuat relatif terhadap tanggal pemasangan (6 hari terakhir + hari ini).
- Sebelum dipakai produksi: atur kredensial MySQL yang aman, ganti password
  `12345678` secara berkala, dan aktifkan HTTPS di https://e-dema.com/ (agar cookie
  `secure` dan kamera berfungsi di jaringan).
