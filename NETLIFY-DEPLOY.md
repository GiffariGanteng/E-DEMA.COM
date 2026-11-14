# Deploy e-DEMA ke Netlify (Functions + Netlify Database)

Repo ini sudah disiapkan untuk **https://e-demag2.netlify.app**:

- Frontend statis hanya dari folder **`public/`** yang dibuat ulang saat build.
- API PHP sudah diporting ke **`netlify/functions/api.mjs`**.
- Database memakai **Netlify Database (PostgreSQL)** dengan migrasi di
  `netlify/database/migrations/`.
- Folder **`api/`, `tools/`, `database/`, dan `var/` tidak lagi ikut ter-deploy**,
  sehingga berkas PHP/SQL/CSV tidak bisa diunduh dari situs.

> Yang belum bisa dilakukan otomatis dari sesi ini: login ke akun Netlify.
> Netlify CLI terdeteksi `loggedIn: false` dan `linked: false`, jadi langkah
> autentikasi di bawah harus dijalankan sekali oleh pemilik akun.

---

## 1. Login dan hubungkan proyek

```bash
npx netlify-cli@latest login
npx netlify-cli@latest link --name e-demag2
```

Jika nama proyek berbeda, ganti `e-demag2` dengan nama site di dashboard Netlify
(atau gunakan `--id <site-id>`).

Cek koneksi:

```bash
npx netlify-cli@latest status
```

---

## 2. Siapkan rahasia sesi

Cookie login ditandatangani dengan `AUTH_SECRET`. Buat nilai acak dan simpan
sebagai environment variable Netlify **tanpa mencetak nilainya**:

```bash
SECRET=$(node -e 'process.stdout.write(require("crypto").randomBytes(32).toString("hex"))')
npx netlify-cli@latest env:set AUTH_SECRET "$SECRET" --secret --context production deploy-preview
unset SECRET
```

- Jangan commit nilai ini ke repository.
- Jika `AUTH_SECRET` diganti, semua sesi lama otomatis logout.
- Tanpa `AUTH_SECRET`, fungsi memakai turunan `NETLIFY_DB_URL` agar tetap berjalan,
  tetapi menetapkan `AUTH_SECRET` lebih disarankan.

---

## 3. Aktifkan Netlify Database

Paket `@netlify/database` sudah ada di `package.json`, dan migrasi awal sudah
tersedia. Pada deploy pertama Netlify akan:

1. mem-provision database PostgreSQL untuk site;
2. menjalankan
   `netlify/database/migrations/20261114090000_initial_schema/migration.sql`;
3. menyuntikkan koneksi database ke function secara otomatis.

Jika database belum muncul di dashboard:

1. Buka **Project configuration → Data & Storage → Database**.
2. Pilih **Create a database manually**.
3. Deploy ulang setelah database tersedia.

Netlify Database tersedia pada paket credit-based. Deploy preview mendapat branch
database terpisah yang disalin dari production, jadi perlakukan preview sebagai
data sensitif.

Migrasi memasukkan dua akun staf pertama:

| Peran | Email | Password |
| --- | --- | --- |
| Admin | `admin@e-dema.id` | password akun admin yang sudah ada di `var/akun-admin.csv` |
| Operator | `operator@e-dema.id` | password akun operator yang sudah ada di `var/akun-admin.csv` |

Password disimpan sebagai hash bcrypt, bukan plaintext. Setelah login pertama,
ganti melalui menu **Akun & Password**.

---

## 4. Deploy

```bash
npx netlify-cli@latest deploy --prod
```

Netlify akan memakai konfigurasi `netlify.toml`:

```toml
[build]
  command = "node tools/build-netlify.mjs"
  publish = "public"
  functions = "netlify/functions"
```

Build command menjalankan `tools/build-netlify.mjs`, yang:

1. menghapus `public/` lama;
2. menyalin ulang semua `*.html` dari root;
3. menyalin `assets/` beserta seluruh isinya;
4. menyalin `404.html` dan `_redirects`;
5. memastikan tidak ada `.php`, `.sql`, `.csv`, `.log`, atau `.mjs` di `public/`.

Dengan demikian `public/` tidak pernah basi atau berisi duplikat: satu-satunya
sumber adalah HTML dan `assets/` di root. Folder `public/` diabaikan Git
(`.gitignore`), tetapi tetap dibuat otomatis saat build Netlify.

Netlify membundel function dari `netlify/functions/api.mjs` secara otomatis.
Untuk mencoba build secara lokal:

```bash
node tools/build-netlify.mjs
```

Jika memakai Git:

1. Hubungkan repository ke site `e-demag2`.
2. Pastikan **Publish directory** = `public`.
3. Pastikan **Functions directory** = `netlify/functions`.
4. Deploy dari branch produksi.

---

## 5. Verifikasi setelah deploy

### Halaman dan API

```bash
curl -I https://e-demag2.netlify.app/
curl -s https://e-demag2.netlify.app/api/index.php?action=auth.me
```

Hasil yang benar:

- `/` → `200`
- `auth.me` → JSON `{"ok":true,...}` dan header `Set-Cookie: EABSID=...`

### Folder sensitif harus 404

```bash
for p in tools/install.php database/schema.mysql.sql api/config.local.php var/akun-guru.csv; do
  printf "%s -> " "$p"
  curl -o /dev/null -s -w "%{http_code}\n" "https://e-demag2.netlify.app/$p"
done
```

Semua harus **404** (bukan `200` dan bukan isi berkas).

### Login dan fitur

1. Buka `https://e-demag2.netlify.app/` → login admin.
2. Buka Dashboard, Data Guru, Agenda Kegiatan, Rekap & Laporan.
3. Tambah agenda, lalu scan QR agenda dari akun guru.
4. Unduh CSV Data Guru dan rekap CSV/XLS.
5. Login akun guru → portal tampil, Dashboard ditolak (403), Scanner agenda berfungsi.

### Uji otomatis lokal

```bash
node tools/test-netlify-api.mjs
```

Skrip menjalankan migrasi pada PostgreSQL (PGlite) dan menguji 54 pemeriksaan:
sesi/CSRF, login admin/operator/guru, dashboard, agenda, siswa, scan kartu,
scan QR agenda, presensi manual, laporan, ekspor CSV/XLS, pembatasan peran,
ganti password, dan hapus data.

---

## 6. Catatan penting

### Data guru lama belum otomatis pindah

Migrasi Netlify hanya membuat **skema + akun admin/operator pertama**. Database
MySQL lama berisi 652 guru dan akun loginnya tidak ikut tersalin otomatis.

Setelah situs stabil, data guru perlu dimigrasikan dari database MySQL lama
(misalnya lewat skrip ekspor/impor terpisah). Sampai itu dilakukan, portal dan
scanner untuk akun guru belum memiliki data.

### Deploy lama masih bisa menyimpan berkas lama

Mengubah publish directory menjadi `public/` menghapus `api/`, `tools/`, dan
`database/` dari **deploy baru**. Deploy lama di Netlify dapat memiliki URL
permalink sendiri. Setelah deploy baru terverifikasi:

1. Buka **Deploys** di dashboard Netlify.
2. Hapus deploy lama yang masih memuat berkas PHP/SQL jika permalink-nya bisa
   diakses publik.
3. Pastikan tidak ada deploy preview lama yang dipublikasikan.

### Kamus environment

| Variabel | Sumber | Keterangan |
| --- | --- | --- |
| `NETLIFY_DB_URL` | Netlify | Disuntik otomatis oleh Netlify Database |
| `AUTH_SECRET` | Netlify env | Kunci tanda tangan cookie sesi, wajib dirahasiakan |

Jangan menaruh `NETLIFY_DB_URL`, `AUTH_SECRET`, atau kredensial database di
`public/`, repository, atau chat.
