# Tax Processing System (TPS) REST API

REST API untuk pengelolaan kewajiban pajak wajib pajak, pencatatan pembayaran, dan perhitungan denda otomatis. Dibangun dengan Laravel 12, autentikasi JWT, dan kontrol akses berbasis peran (RBAC).

---

## Daftar Isi

- [Fitur](#fitur)
- [Arsitektur Sistem](#arsitektur-sistem)
- [Skema Database](#skema-database)
- [Keputusan Desain](#keputusan-desain)
- [Teknologi yang Digunakan](#teknologi-yang-digunakan)
- [Panduan Setup](#panduan-setup)
  - [Cara 1 — XAMPP / PHP Lokal](#cara-1--xampp--php-lokal)
  - [Cara 2 — Docker (Laravel Sail)](#cara-2--docker-laravel-sail)
- [Menjalankan Aplikasi](#menjalankan-aplikasi)
- [Referensi API](#referensi-api)
- [Dokumentasi Endpoint per Role](#dokumentasi-endpoint-per-role)
- [Menjalankan Test](#menjalankan-test)
- [Matriks Hak Akses](#matriks-hak-akses)

---

## Fitur

- Autentikasi JWT dengan invalidasi token saat logout
- Sistem tiga peran: **ADMIN**, **PETUGAS**, dan **WAJIB_PAJAK**
- Manajemen data wajib pajak — individu dan badan usaha
- Pelacakan kewajiban pajak dengan batas jatuh tempo
- Perhitungan denda otomatis pada setiap pencatatan pembayaran
- Audit log menyeluruh via Spatie Activity Log
- Laporan pembayaran dengan ringkasan statistik
- Dokumentasi OpenAPI 3.0 (L5-Swagger)

---

## Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────┐
│                     Klien HTTP                          │
│             (Postman / Frontend / Tester)               │
└─────────────────────────┬───────────────────────────────┘
                          │ HTTPS  Bearer Token
                          ▼
┌─────────────────────────────────────────────────────────┐
│                    Laravel 12 API                        │
│                                                          │
│  ┌──────────────┐   ┌─────────────────────────────────┐ │
│  │  Middleware  │   │           Routes (api.php)       │ │
│  │              │   │                                  │ │
│  │ ForceJSON    │──▶│  Publik:    POST /auth/login     │ │
│  │ JWT Auth     │   │  Terproteksi: semua endpoint     │ │
│  │ RoleCheck    │   └──────────────┬──────────────────┘ │
│  └──────────────┘                  │                     │
│                                    ▼                     │
│  ┌─────────────────────────────────────────────────────┐ │
│  │                   Controllers                        │ │
│  │  Auth │ User │ WajibPajak │ KewajibanPajak           │ │
│  │  Pembayaran │ Denda │ Laporan │ AuditLog             │ │
│  └──────────────────────┬──────────────────────────────┘ │
│                         │                                │
│         ┌───────────────┼────────────────┐               │
│         ▼               ▼                ▼               │
│  ┌─────────────┐ ┌───────────┐ ┌────────────────┐        │
│  │   Models    │ │  Services │ │ Form Requests  │        │
│  │  (Eloquent) │ │           │ │  (Validasi)    │        │
│  │             │ │DendaService└────────────────┘        │
│  └──────┬──────┘ └───────────┘                           │
│         │                                                │
└─────────┼────────────────────────────────────────────────┘
          ▼
┌─────────────────────────────────────────────────────────┐
│                   Database MySQL                         │
│  users │ wajib_pajak │ kewajiban_pajak                  │
│  pembayaran │ denda │ activity_log                      │
└─────────────────────────────────────────────────────────┘
```

### Alur Request

```
Request → ForceJsonResponse → JWTAuth → RoleMiddleware → Controller
        → FormRequest (validasi) → Service / Model → JSON Response
```

Setiap aksi yang mengubah data (create/update/delete) dicatat otomatis oleh Spatie Activity Log.

---

## Skema Database

```
users
├── id
├── name
├── username          (unique)
├── email             (unique, nullable)
├── password          (bcrypt)
└── role              ENUM(ADMIN | PETUGAS | WAJIB_PAJAK)

wajib_pajak
├── id
├── jenis             ENUM(INDIVIDU | BADAN)
├── nama
├── nik               (16 digit, wajib untuk INDIVIDU)
├── npwp              (15–16 digit, unique)
├── nib               (9–30 digit, wajib untuk BADAN)
├── email, telepon, alamat
└── status_aktif      BOOLEAN

kewajiban_pajak
├── id
├── wajib_pajak_id    → wajib_pajak.id (CASCADE DELETE)
├── jenis_pajak
├── masa_pajak
├── pokok_pajak       DECIMAL(18,2)
├── jatuh_tempo       DATE
└── status            ENUM(BELUM_LUNAS | LUNAS | LEBIH_BAYAR)

pembayaran
├── id
├── kewajiban_pajak_id → kewajiban_pajak.id (CASCADE DELETE)
├── nominal           DECIMAL(18,2)
├── tanggal_bayar     DATE
├── status            ENUM(LUNAS | KURANG_BAYAR | LEBIH_BAYAR)
├── keterangan
└── dicatat_oleh      → users.id (SET NULL)

denda
├── id
├── pembayaran_id     → pembayaran.id (CASCADE DELETE)
├── denda_telat       DECIMAL(18,2)   — 2% dari pokok jika terlambat
├── denda_kurang      DECIMAL(18,2)   — 1% dari selisih jika kurang bayar
├── total_denda       DECIMAL(18,2)
├── is_telat          BOOLEAN
└── is_kurang_bayar   BOOLEAN
```

**Relasi antar tabel:**
`wajib_pajak` → `kewajiban_pajak` → `pembayaran` → `denda` (rantai one-to-many, setiap level cascade-delete ke bawah)

---

## Keputusan Desain

### 1. JWT, bukan Laravel Sanctum

Sanctum terpasang sebagai dependency bawaan Laravel, namun autentikasi menggunakan JWT (`php-open-source-saver/jwt-auth`). JWT bersifat stateless dan lebih sesuai untuk konsumen REST API (aplikasi mobile, SPA, integrasi pihak ketiga) dibanding sesi berbasis cookie milik Sanctum. Token dapat diinvalidasi secara eksplisit saat logout melalui mekanisme blacklist.

### 2. Denda Disimpan sebagai Model Terpisah, Bukan Kolom Kalkulasi

Data denda dipersisten di tabel `denda`, bukan dihitung ulang setiap kali dibutuhkan. Ini menjaga akurasi histori — jika aturan bisnis berubah (misal penyesuaian tarif denda), data denda lama tetap mencerminkan aturan yang berlaku saat itu. `DendaService` mengisolasi logika kalkulasi agar mudah diuji secara mandiri.

**Aturan perhitungan denda:**

| Kondisi | Denda |
|---------|-------|
| Pembayaran setelah `jatuh_tempo` | 2% × `pokok_pajak` |
| Pembayaran < sisa kewajiban | 1% × selisih kekurangan |
| Keduanya | Kedua denda dijumlahkan |
| Lebih bayar | Tidak ada denda, status = `LEBIH_BAYAR` |

### 3. Pembuatan Wajib Pajak Otomatis Membuat Akun User

`POST /wajib-pajak` membuat record `wajib_pajak` sekaligus record `users` terhubung dengan role `WAJIB_PAJAK` dalam satu database transaction. Ini menjaga alur registrasi tetap atomik dan mencegah wajib pajak terdaftar tanpa akses portal.

### 4. Middleware ForceJsonResponse

Perilaku default Laravel adalah redirect request yang tidak terautentikasi ke halaman `/login`. Untuk proyek API-only, ini tidak tepat — setiap respons harus berupa JSON. Middleware `ForceJsonResponse` menyetel header `Accept: application/json` secara global sehingga Laravel mengembalikan `401 JSON` alih-alih redirect.

### 5. Kontrol Akses via Custom Middleware, Bukan Gates/Policies

`RoleMiddleware` diterapkan di level route (`->middleware('role:ADMIN,PETUGAS')`). Ini disengaja: batas izin akses memetakan langsung ke seluruh route, bukan ke instance model individual (kecuali akses mandiri `WAJIB_PAJAK` yang diberlakukan di dalam controller). Ini menghindari boilerplate policy untuk set peran yang kecil dan terdefinisi jelas.

### 6. Dukungan Pembayaran Kumulatif / Cicilan

Satu `kewajiban_pajak` dapat memiliki banyak record `pembayaran`. Setiap pembayaran dievaluasi terhadap `totalDibayar()` — jumlah semua pembayaran sebelumnya — sehingga wajib pajak dapat membayar secara cicilan. Status diperbarui menjadi `LUNAS` hanya ketika total dibayar ≥ `pokok_pajak`.

### 7. SQLite In-Memory untuk Pengujian

Suite test menggunakan `DB_DATABASE=:memory:` (SQLite). Ini menghilangkan ketergantungan MySQL eksternal di CI, membuat test berjalan cepat (dalam hitungan detik), dan memastikan setiap test run dimulai dari skema bersih melalui `RefreshDatabase`.

---

## Teknologi yang Digunakan

| Layer | Teknologi |
|-------|-----------|
| Framework | Laravel 12 |
| PHP | 8.2+ |
| Autentikasi | `php-open-source-saver/jwt-auth` 2.8 |
| Database | MySQL 8.0 (produksi), SQLite in-memory (testing) |
| Audit Log | `spatie/laravel-activitylog` 4.x |
| Dokumentasi API | `darkaonline/l5-swagger` 11.x (OpenAPI 3.0) |
| Testing | PHPUnit 11, Mockery |
| Dev Tools | Laravel Sail (Docker), Laravel Pint (code style) |

---

## Panduan Setup

Tersedia dua cara menjalankan proyek ini. Pilih salah satu sesuai lingkungan yang digunakan.

---

### Cara 1 — XAMPP / PHP Lokal

#### Prasyarat

- PHP 8.2+
- Composer 2.x
- MySQL 8.0 (atau MariaDB 10.6+)
- Node.js 18+ dan npm

#### Langkah-langkah

**1. Clone repositori**

```bash
git clone https://github.com/your-username/tax-processing-system-rest-api.git
cd tax-processing-system-rest-api
```

**2. Install dependensi PHP**

```bash
composer install
```

**3. Konfigurasi environment**

```bash
cp .env.example .env
```

Buka `.env` dan sesuaikan konfigurasi database:

```env
APP_KEY=                       # akan di-generate di langkah berikutnya

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tps_db
DB_USERNAME=root
DB_PASSWORD=password_anda

JWT_SECRET=                    # akan di-generate di langkah berikutnya
JWT_TTL=60                     # masa berlaku token dalam menit
JWT_ALGO=HS256
```

**4. Generate application key dan JWT secret**

```bash
php artisan key:generate
php artisan jwt:secret
```

**5. Buat database**

```bash
mysql -u root -p -e "CREATE DATABASE tps_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**6. Jalankan migrasi**

```bash
php artisan migrate
php artisan db:seed          # opsional: mengisi data awal termasuk akun admin
```

**7. Install aset frontend** *(lewati jika hanya menggunakan API)*

```bash
npm install && npm run build
```

**8. Generate dokumentasi API**

```bash
php artisan l5-swagger:generate
```

**9. Jalankan server**

```bash
php artisan serve
```

API tersedia di: `http://localhost:8000/api`
Swagger UI di: `http://localhost:8000/api/documentation`

---

### Cara 2 — Docker (Laravel Sail)

#### Prasyarat

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- WSL2 (untuk pengguna Windows) — aktifkan melalui *Turn Windows features on or off* → *Windows Subsystem for Linux*

#### Langkah-langkah

**1. Clone repositori**

```bash
git clone https://github.com/rahmatirvan16/tax-processing-system-rest-api.git
cd tax-processing-system-rest-api
```

**2. Install dependensi PHP** *(tanpa Docker terlebih dahulu)*

```bash
composer install
```

> Jika belum ada PHP di mesin lokal, gunakan Docker untuk install dependensi:
> ```bash
> docker run --rm -v $(pwd):/app -w /app composer:2 install
> ```

**3. Konfigurasi environment untuk Docker**

```bash
cp .env.example .env
```

Edit `.env` dan sesuaikan nilai berikut agar cocok dengan konfigurasi Sail:

```env
APP_URL=http://localhost:8000
APP_PORT=8000

DB_CONNECTION=mysql
DB_HOST=mysql              # nama service di docker-compose.yml
DB_PORT=3306
DB_DATABASE=tps_db
DB_USERNAME=sail
DB_PASSWORD=password

FORWARD_DB_PORT=3307       # port MySQL yang diekspos ke host
```

**4. Jalankan container**

```bash
./vendor/bin/sail up -d
```

> **Windows (PowerShell/CMD):** gunakan `vendor\bin\sail up -d`
>
> Sail akan otomatis membangun image PHP 8.2 dan MySQL 8.0, lalu menjalankan keduanya.

**5. Generate application key dan JWT secret**

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan jwt:secret
```

**6. Jalankan migrasi**

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed    # opsional
```

**7. Generate dokumentasi API**

```bash
./vendor/bin/sail artisan l5-swagger:generate
```

API tersedia di: `http://localhost:8000/api`
Swagger UI di: `http://localhost:8000/api/documentation`

#### Perintah Sail yang Sering Digunakan

```bash
# Menghentikan container
./vendor/bin/sail down

# Menghentikan dan menghapus volume database
./vendor/bin/sail down --volumes

# Menjalankan perintah Artisan
./vendor/bin/sail artisan <perintah>

# Menjalankan Composer
./vendor/bin/sail composer <perintah>

# Masuk ke shell container
./vendor/bin/sail shell

# Melihat log container
./vendor/bin/sail logs -f
```

#### Berpindah antara XAMPP dan Docker

| Mode | `DB_HOST` di `.env` |
|------|---------------------|
| XAMPP / PHP lokal | `127.0.0.1` |
| Docker (Laravel Sail) | `mysql` |

---

## Menjalankan Aplikasi

### Mode development (dengan queue worker dan log watcher)

```bash
# XAMPP
composer run dev

# Docker
./vendor/bin/sail up -d && ./vendor/bin/sail artisan queue:work
```

`composer run dev` menjalankan PHP server, queue worker, Pail log viewer, dan Vite secara bersamaan.

---

## Referensi API

**Base URL:** `http://localhost:8000/api`

Semua endpoint terproteksi memerlukan header:
```
Authorization: Bearer <token>
```

Semua respons berformat `application/json`.

### Autentikasi

| Method | Endpoint | Akses | Keterangan |
|--------|----------|-------|------------|
| POST | `/auth/login` | Publik | Login, mengembalikan JWT token |
| GET | `/auth/me` | Auth | Informasi pengguna aktif |
| POST | `/auth/logout` | Auth | Invalidasi token |

**Contoh request login:**
```json
{
  "username": "admin",
  "password": "password"
}
```

**Contoh respons login:**
```json
{
  "token": "eyJ...",
  "token_type": "bearer",
  "expires_in": 3600,
  "user": { "id": 1, "name": "Admin", "role": "ADMIN" }
}
```

---

### Pengguna (Users)

| Method | Endpoint | Akses | Keterangan |
|--------|----------|-------|------------|
| GET | `/users` | ADMIN | Daftar pengguna (filter pencarian & peran) |
| GET | `/users/{id}` | ADMIN | Detail pengguna |
| POST | `/users` | ADMIN | Buat pengguna baru |
| PUT | `/users/{id}` | ADMIN | Perbarui pengguna |
| DELETE | `/users/{id}` | ADMIN | Hapus pengguna |
| GET | `/audit-log` | ADMIN | Riwayat aktivitas sistem |

---

### Wajib Pajak

| Method | Endpoint | Akses | Keterangan |
|--------|----------|-------|------------|
| GET | `/wajib-pajak` | Auth | Daftar (WAJIB_PAJAK hanya melihat data sendiri) |
| GET | `/wajib-pajak/me` | WAJIB_PAJAK | Shortcut data profil sendiri |
| GET | `/wajib-pajak/{id}` | Auth | Detail (WAJIB_PAJAK: hanya data sendiri) |
| POST | `/wajib-pajak` | ADMIN, PETUGAS | Buat wajib pajak + akun user otomatis |
| PUT | `/wajib-pajak/{id}` | ADMIN, PETUGAS | Perbarui data |
| DELETE | `/wajib-pajak/{id}` | ADMIN | Hapus data |

**Contoh request buat wajib pajak (INDIVIDU):**
```json
{
  "jenis": "INDIVIDU",
  "nama": "Budi Santoso",
  "nik": "3201234567890001",
  "npwp": "123456789012345",
  "email": "budi@example.com",
  "telepon": "081234567890",
  "alamat": "Jl. Merdeka No. 1, Jakarta",
  "username": "budi.santoso",
  "password": "secret123"
}
```

---

### Kewajiban Pajak

| Method | Endpoint | Akses | Keterangan |
|--------|----------|-------|------------|
| GET | `/kewajiban-pajak` | Auth | Daftar kewajiban pajak |
| GET | `/kewajiban-pajak/{id}` | Auth | Detail beserta riwayat pembayaran & ringkasan |
| POST | `/kewajiban-pajak` | ADMIN, PETUGAS | Buat kewajiban pajak baru |

---

### Pembayaran

| Method | Endpoint | Akses | Keterangan |
|--------|----------|-------|------------|
| POST | `/pembayaran` | ADMIN, PETUGAS | Catat pembayaran (denda dihitung otomatis) |

**Contoh request:**
```json
{
  "kewajiban_pajak_id": 1,
  "nominal": 500000,
  "tanggal_bayar": "2026-06-25",
  "keterangan": "Cicilan pertama"
}
```

**Respons menyertakan:**
```json
{
  "pembayaran": { "..." },
  "denda": {
    "denda_telat": 0,
    "denda_kurang": 5000,
    "total_denda": 5000,
    "is_telat": false,
    "is_kurang_bayar": true
  },
  "pokok_pajak": 1000000,
  "total_dibayar": 500000,
  "sisa_kewajiban": 500000
}
```

---

### Denda

| Method | Endpoint | Akses | Keterangan |
|--------|----------|-------|------------|
| GET | `/denda/{pembayaran_id}` | Auth | Detail denda untuk suatu pembayaran |

---

### Laporan

| Method | Endpoint | Akses | Keterangan |
|--------|----------|-------|------------|
| GET | `/laporan` | ADMIN, PETUGAS | Laporan pembayaran dengan filter & ringkasan |

**Query parameter:** `wajib_pajak_id`, `tanggal_mulai`, `tanggal_akhir`, `jenis_pajak`, `status`

---

## Dokumentasi Endpoint per Role

Dokumentasi lengkap seluruh endpoint yang dikelompokkan per role tersedia dalam format HTML:

**[docs/Endpoint_API_per_Role.html](docs/Endpoint_API_per_Role.html)**

File tersebut mencakup:
- Daftar endpoint yang dapat diakses oleh setiap role (ADMIN, PETUGAS, WAJIB_PAJAK)
- Method HTTP, URL, keterangan, dan contoh body/parameter untuk tiap endpoint
- Tabel ringkasan akses per endpoint (matriks ✅ / ❌)

### Akun Bawaan untuk Pengujian

Setelah menjalankan `php artisan db:seed`, akun berikut tersedia:

| Username | Password | Role | Keterangan |
|----------|----------|------|------------|
| `admin` | `Pretest@2025` | ADMIN | Akses penuh ke seluruh sistem |
| `petugas` | `Petugas@2025` | PETUGAS | Input data & lihat laporan |
| `budi` | `Wajib@2025` | WAJIB_PAJAK | Hanya melihat data miliknya sendiri |

> Gunakan akun di atas untuk login via `POST /api/auth/login`, kemudian sertakan token yang dikembalikan sebagai `Authorization: Bearer <token>` di setiap request berikutnya.

---

## Menjalankan Test

### Jalankan semua test

```bash
php artisan test
```

Atau via Composer (membersihkan config cache terlebih dahulu):

```bash
composer run test
```

Via Docker:

```bash
./vendor/bin/sail test
```

### Jalankan suite tertentu

```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

### Jalankan file atau method tertentu

```bash
php artisan test tests/Unit/DendaServiceTest.php
php artisan test --filter test_telat_bayar_denda_2_persen_pokok
```

### Dengan laporan coverage *(memerlukan Xdebug atau PCOV)*

```bash
php artisan test --coverage
php artisan test --coverage --min=80    # gagal jika coverage < 80%
```

---

### Struktur Test

```
tests/
├── Unit/
│   ├── ExampleTest.php
│   └── DendaServiceTest.php        # 6 test — logika perhitungan denda
└── Feature/
    ├── ExampleTest.php
    └── ApiFlowTest.php             # 10 test — skenario API end-to-end
```

**Unit Test (`DendaServiceTest`)** — menguji `DendaService::hitung()` secara terisolasi tanpa database:

| Test | Skenario |
|------|----------|
| `test_tepat_waktu_dan_lunas_tanpa_denda` | Bayar tepat waktu, lunas → tidak ada denda |
| `test_telat_bayar_denda_2_persen_pokok` | Pembayaran terlambat → denda 2% dari pokok |
| `test_kurang_bayar_denda_1_persen_selisih` | Kurang bayar tepat waktu → denda 1% dari selisih |
| `test_denda_gabungan_telat_dan_kurang_bayar` | Terlambat + kurang bayar → denda gabungan |
| `test_lebih_bayar` | Lebih bayar → status `LEBIH_BAYAR`, tidak ada denda |
| `test_pembayaran_kumulatif` | Cicilan bertahap → total akumulatif benar |

**Feature Test (`ApiFlowTest`)** — menguji seluruh HTTP stack dengan SQLite in-memory:

| Test | Skenario |
|------|----------|
| `test_login_berhasil_mengembalikan_token` | Kredensial valid → token dikembalikan |
| `test_login_gagal_kredensial_salah` | Kredensial salah → 401 |
| `test_endpoint_dilindungi_tanpa_token_ditolak` | Tanpa token → 401 |
| `test_buat_wajib_pajak_individu_validasi_nik` | Validasi format NIK |
| `test_pembayaran_menghitung_denda_otomatis` | Pembayaran memicu kalkulasi denda di respons |
| `test_pembayaran_nominal_nol_ditolak` | Nominal nol → 422 |
| `test_pembayaran_tanggal_masa_depan_ditolak` | Tanggal di masa depan → 422 |
| `test_pembayaran_wajib_pajak_nonaktif_ditolak` | Wajib pajak nonaktif → 409 |
| `test_wajib_pajak_hanya_lihat_data_sendiri` | Isolasi data role WAJIB_PAJAK |
| `test_petugas_tidak_boleh_hapus_wajib_pajak` | PETUGAS tidak bisa hapus → 403 |

---

### Environment Test

Test menggunakan **database SQLite in-memory** yang dikonfigurasi di `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Tidak diperlukan instance MySQL eksternal untuk menjalankan test suite. Setiap kelas test yang menggunakan `RefreshDatabase` dimulai dari skema yang bersih.

---

## Matriks Hak Akses

| Fitur | ADMIN | PETUGAS | WAJIB_PAJAK |
|-------|-------|---------|-------------|
| Manajemen pengguna | Penuh | — | — |
| Audit log | Baca | — | — |
| Wajib pajak — list & detail | Semua | Semua | Data sendiri |
| Wajib pajak — buat & ubah | Ya | Ya | — |
| Wajib pajak — hapus | Ya | — | — |
| Kewajiban pajak — buat | Ya | Ya | — |
| Kewajiban pajak — baca | Semua | Semua | Data sendiri |
| Pembayaran — catat | Ya | Ya | — |
| Denda — baca | Semua | Semua | Data sendiri |
| Laporan | Ya | Ya | — |

---

## Lisensi

MIT
