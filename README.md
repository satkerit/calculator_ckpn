# CKPN Calculator BPRS

**Credit Risk Management System untuk Bank Perkreditan Rakyat Syariah**

Sistem kalkulasi CKPN (Cadangan Kerugian Penurunan Nilai) berbasis PSAK 414 untuk manajemen risiko kredit di BPRS. Aplikasi ini menyediakan alur end-to-end: import data financing & snapshot, konfigurasi mapping produk-akad, kalkulasi EAD / Klasifikasi / PD / LGD / CKPN per modul, rekonsiliasi, sampai laporan dan export Excel.

---

## 📋 Daftar Isi

- [Fitur Utama](#-fitur-utama)
- [Teknologi](#-teknologi)
- [Requirement Sistem](#-requirement-sistem)
- [Instalasi](#-instalasi)
- [Konfigurasi](#-konfigurasi)
- [Penggunaan](#-penggunaan)
- [Alur Kalkulasi CKPN](#-alur-kalkulasi-ckpn)
- [Routes](#-routes)
- [Struktur Project](#-struktur-project)
- [Database](#-database)
- [Testing](#-testing)
- [Dokumentasi Tambahan](#-dokumentasi-tambahan)
- [Lisensi](#-lisensi)

---

## ✨ Fitur Utama

### 1. Master Data & Setup
- **Office** — kelola kantor cabang
- **Collateral & Collateral Setup** — tipe agunan dan konfigurasinya
- **Akad Type** — murabahah, multijasa, musyarakah, IMBT (CRUD + bulk + import)
- **Product Akad Mapping** — pemetaan `product_code` → `akad_type` (dasar pemilihan strategi EAD)
- **User & Role Management** — dengan permission per module (`CheckPermission` middleware)

### 2. Import Data (Excel/XLSX & XLS, via OpenSpout)
- Import **Snapshot** financing periode (outstanding, arrears, write-off, dll.)
- Import **Collateral**, **Office**, **Collateral Setup**, **Akad Type**, **Product Akad Mapping**
- Download **template file** untuk setiap jenis import
- Validasi real-time saat upload dengan report error detail

### 3. Kalkulasi CKPN (8 Modul)
- **Configuration** — set assessment period aktif & method PD (Net Flow / Migration) + LGD (ER / CS / Weighted)
- **Classification** — klasifikasi INDIVIDUAL vs COLLECTIVE sekaligus **EAD** per financing
- **PD Net Flow** — transition matrix antar bucket + compound multi-periode, dengan detail transition & bucket snapshot
- **PD Migration** — migration matrix rating kualitas (Rating 1–5 + WO)
- **LGD Expected Recovery** — recovery rate dari riwayat write-off
- **LGD Collateral Shortfall** — shortfall dari hasil eksekusi agunan
- **Detail PD–EAD** — tampilan gabungan EAD & PD untuk review
- **Detail CKPN** — konsolidasi individual + kolektif, dengan tombol **Finalize** untuk mengunci hasil periode

### 4. Views & Export
- Hasil CKPN per komponen: **Individual** & **Kolektif** (`calculation/ckpn/*`)
- Export **XLSX per modul** per assessment period
- Export **Backtest Bundle** untuk kebutuhan backtest regulator/OJK
- Laporan: **CKPN Summary**, **Risk Matrix** (PD vs LGD), **Aging Analysis**
- **Snapshot History** — riwayat data snapshot per periode + export

### 5. Filament Admin Panel
- Resource **Assessment Periods** untuk pengelolaan periode assessment secara cepat dari panel admin

---

## 🧰 Teknologi

| Komponen | Versi | Keterangan |
|---|---|---|
| PHP | ^8.3 | Runtime |
| Laravel | ^13.17 | Framework |
| Filament | ^5.9 (`filament/filament`, `forms`, `tables`) | Admin panel |
| Laravel Breeze | ^2.4 | Autentikasi (Blade + Alpine) |
| Tailwind CSS | ^4.0 (Vite plugin) + `tailwind.config.js` | Styling (lihat catatan migrasi di bawah) |
| Vite | ^8 | Asset bundler |
| OpenSpout | ^4.32 | Baca/tulis file Excel besar secara memory-efficient |
| PHPUnit | ^12.5 | Testing |
| Database | SQLite (development) / MySQL, PostgreSQL (production) | |

> **Catatan:** `package.json` masih memuat `@tailwindcss/vite` v4 bersama `tailwind.config.js` v3-style config. Jika build asset gagal, samakan pendekatan konfigurasi Tailwind ke satu versi (lihat [Troubleshooting](#-troubleshooting)).

---

## 🖥️ Requirement Sistem

- **PHP** 8.3+ dengan ekstensi umum Laravel (`pdo_sqlite`/`pdo_mysql`, `mbstring`, `xml`, `zip`, dll.)
- **Composer** 2
- **Node.js** 18+ dan **npm**
- **SQLite** (bawaan, untuk development) atau **MySQL/PostgreSQL** untuk production

---

## 📦 Instalasi

### Cara Cepat (script `setup` bawaan composer)

```bash
git clone <repository-url>
cd calculator_ckpn

composer setup
```

Script `setup` akan otomatis menjalankan:
1. `composer install`
2. Copy `.env.example` → `.env` (jika `.env` belum ada)
3. `php artisan key:generate`
4. `php artisan migrate --force`
5. `npm install --ignore-scripts`
6. `npm run build`

### Cara Manual (step-by-step)

```bash
# 1. Install dependencies PHP & JS
composer install
npm install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Database
php artisan migrate
# (opsional) bootstrap SQLite jika belum ada:
#   touch database/database.sqlite

# 4. Build asset
npm run build

# 5. Jalankan development server
composer run dev
# atau:
php artisan serve
```

Aplikasi tersedia di `http://127.0.0.1:8000`. Endpoint `/` otomatis redirect ke `dashboard` jika sudah login, atau ke `login` jika belum.

### Membuat User Pertama

```bash
php artisan tinker
>>> \App\Models\User::create([
...     'name' => 'Admin',
...     'email' => 'admin@example.com',
...     'password' => bcrypt('password'),
... ]);
```
*(Breeze menyediakan halaman register juga; gunakan sesuai kebijakan deployment.)*

---

## ⚙️ Konfigurasi

### Environment Variables (`.env`)

```env
APP_NAME="CKPN Calculator"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=sqlite
# Untuk MySQL/PostgreSQL, sesuaikan:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=ckpn_db
# DB_USERNAME=root
# DB_PASSWORD=

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

- `.env.testing` tersedia untuk kebutuhan test suite (SQLite `:memory:`)
- `.env.production` tersedia sebagai referensi konfigurasi production

### Testing Environment (`.env.testing`)

Gunakan konfigurasi ini untuk menjalankan test tanpa menyentuh data development:

```bash
php artisan test --env=testing
```

---

## 🚀 Penggunaan

### Workflow Dasar

1. **Setup Master Data** (`/office`, `/collateral`, `/collateral-setup`, `/akad-type`, `/product-akad`)
2. **Import Data** (`/import/snapshot`, `/import/collateral`, `/import/office`, `/import/collateral-setup`, `/import/akad-type`, `/import/product-akad`)
   — unduh template via `/template/download/{type}` sebelum mengisi
3. **Konfigurasi** (`/calculation/module/config`) — pilih assessment period, method PD & LGD
4. **Jalankan Modul Kalkulasi** secara berurutan:
   - `/calculation/module/classification` → EAD + klasifikasi
   - `/calculation/module/pd-netflow` **atau** `/calculation/module/pd-migration`
   - `/calculation/module/lgd-er` dan `/calculation/module/lgd-cs`
   - `/calculation/module/detail-pd-ead` → review
   - `/calculation/module/detail-ckpn` → konsolidasi lalu **Finalize**
5. **Review Hasil** (`/calculation/ckpn/individual`, `/calculation/ckpn/collective`)
6. **Export** (`/calculation/export/{module}/{period}`, `/calculation/export/backtest/{period}`)
7. **Reporting** (`/reporting/ckpn-summary`, `/reporting/risk-matrix`, `/reporting/aging-analysis`)
8. **Snapshot History** (`/snapshot-history`) — riwayat data & export

### Metodologi Singkat

| Komponen | Formula/Logika Utama |
|---|---|
| **EAD** | Strategi per akad via `EadStrategyFactory` (Murabahah, Multijasa, Musyarakah, IMBT, Due-Date-Based) |
| **Klasifikasi** | INDIVIDUAL jika memenuhi rule threshold/write-off/PPKAT, selebihnya COLLECTIVE |
| **PD Net Flow** | Transition matrix antar-bucket dari snapshot historis, compound multi-periode |
| **PD Migration** | Migration matrix rating 1–5 + WO, default state = rating ≥ 4 / WO |
| **LGD ER** | `LGD = 1 − avg(recovery_ratio)` dari riwayat write-off |
| **LGD CS** | `LGD = shortfall / total_exposure_writeoff` dari eksekusi agunan |
| **CKPN Kolektif** | `CKPN = EAD × PD × LGD` per collective group (`office + product + usage`) |
| **CKPN Individual** | `CKPN = max(0, nilai_tercatat − estimasi_recovery)` |

---

## 🛣️ Routes

### Autentikasi (Breeze)
```
GET  /login              POST /login
POST /logout
GET  /register           POST /register
GET  /forgot-password    POST /forgot-password
GET  /reset-password     POST /reset-password
GET  /verify-email       GET  /email/verification-notification
GET  /confirm-password   POST /confirm-password
```

### Dashboard
```
GET  /dashboard
```

### Resources (CRUD, `Route::resource`)
```
/assessment              → AssessmentPeriodController (termasuk bulk-destroy)
/office                  → OfficeController (termasuk bulk-destroy)
/collateral              → CollateralController (termasuk bulk-destroy)
/collateral-setup        → CollateralSetupController (termasuk bulk-destroy)
/user                    → UserController (termasuk bulk-destroy)
/role                    → RoleController (termasuk bulk-destroy)
/akad-type               → AkadTypeController (CRUD + bulk-store / bulk-update / bulk-destroy)
/product-akad            → ProductAkadMappingController (CRUD + bulk-destroy)
GET  /api/product-akad/resolve   → resolve mapping product → akad (JSON)
```

### Snapshot History
```
GET  /snapshot-history                   → list riwayat snapshot
GET  /snapshot-history/{id}              → detail snapshot
POST /snapshot-history/export             → export Excel
```

### Import & Template
```
GET  /import/snapshot            POST /import/snapshot
GET  /import/collateral          POST /import/collateral
GET  /import/office              POST /import/office
GET  /import/collateral-setup    POST /import/collateral-setup
GET  /import/akad-type           POST /import/akad-type
GET  /import/product-akad        POST /import/product-akad
GET  /template/download/{type}   → unduh template Excel
```

### Kalkulasi (8 Modul)
```
GET/POST /calculation/module/config          → konfigurasi & save
GET/POST /calculation/module/classification  → run EAD + klasifikasi
GET/POST /calculation/module/pd-netflow      → run PD Net Flow
GET      /calculation/module/pd-netflow/{period}/detail
GET/POST /calculation/module/pd-migration    → run PD Migration
GET/POST /calculation/module/lgd-er          → run LGD Expected Recovery
GET/POST /calculation/module/lgd-cs          → run LGD Collateral Shortfall
GET      /calculation/module/detail-pd-ead   → review EAD & PD
GET/POST /calculation/module/detail-ckpn     → konsolidasi + Finalize
GET      /calculation/ckpn/individual        → hasil CKPN individual
GET      /calculation/ckpn/collective        → hasil CKPN kolektif
```

### Export
```
GET  /calculation/export/{module}/{period}      → export XLSX per modul
GET  /calculation/export/module/{module}        → export modul period terakhir/aktif
GET  /calculation/export/backtest/{period}      → export Backtest Bundle
GET  /calculation/export/backtest               → export untuk period aktif
```

### Reporting
```
GET  /reporting/ckpn-summary
GET  /reporting/risk-matrix
GET  /reporting/aging-analysis
GET  /reporting/export/{type}
```

### Filament Admin
```
/admin  → Filament panel (resource AssessmentPeriods, antara lain)
```

Semua route utama berada di dalam `auth` middleware. Beberapa module dilindungi role/permission via `App\Http\Middleware\CheckPermission`.

---

## 📁 Struktur Project

```
calculator_ckpn/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── TestPdNetflowCalculator.php   # artisan command test PD Netflow
│   ├── Filament/
│   │   └── Resources/AssessmentPeriodResource/  # Filament admin resource
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/                     # Breeze auth controllers
│   │   │   ├── Calculation/              # Controller 8 modul kalkulasi
│   │   │   │   ├── ConfigurationController
│   │   │   │   ├── ClassificationController
│   │   │   │   ├── PdNetFlowController / PdNetflowDetailController
│   │   │   │   ├── PdMigrationController
│   │   │   │   ├── LgdExpectedRecoveryController
│   │   │   │   ├── LgdCollateralShortfallController
│   │   │   │   ├── DetailPdEadController / DetailCkpnController
│   │   │   │   └── CkpnDetailController
│   │   │   ├── Export/
│   │   │   │   └── CalculationExportController   # XLSX per modul + Backtest
│   │   │   ├── DataImportController.php  # Import snapshot/collateral/office/…
│   │   │   ├── ReportingController.php
│   │   │   └── … (Office, Collateral, User, Role, dll.)
│   │   ├── Middleware/
│   │   │   └── CheckPermission.php       # Proteksi module per permission
│   │   └── Requests/                     # Form Requests (Login, Profile)
│   ├── Models/
│   │   ├── AkadType.php, ProductAkadMapping.php
│   │   ├── AssessmentPeriod.php, Snapshot.php
│   │   ├── Office.php, Collateral.php, CollateralSetup.php, UsageType.php
│   │   ├── QualityRating.php, WriteOff.php, Recovery.php
│   │   ├── BucketDefinition.php
│   │   ├── PdNetflowTransition.php, PdNetflowCompoundFlow.php,
│   │   │   PdNetflowBucketSnapshot.php
│   │   ├── Role.php, Permission.php, User.php
│   │   └── …
│   ├── Services/
│   │   ├── Bucket/BucketAssignmentService.php
│   │   ├── Ckpn/CkpnCalculator.php
│   │   ├── DataQualityService.php
│   │   ├── Ead/                          # Strategy pattern EAD per akad
│   │   │   ├── EadStrategyFactory.php
│   │   │   ├── MurabahahStrategy.php, MultijasaStrategy.php
│   │   │   ├── MusyarakahStrategy.php, ImbtStrategy.php
│   │   │   └── DueDateBasedStrategy.php
│   │   ├── PdNetflow/
│   │   │   ├── PdNetflowCalculator.php
│   │   │   └── PdNetflowService.php
│   │   ├── ProductAkadMappingService.php
│   │   └── SpoutImportService.php        # Reader Excel via OpenSpout
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── Filament/AdminPanelProvider.php
│   └── View/Components/                  # AppLayout, GuestLayout
├── database/
│   ├── migrations/                       # Schema tables
│   ├── seeders/
│   └── factories/
├── docs/                                 # Dokumentasi teknis & spec
│   ├── CKPN.md
│   ├── CLASSIFICATION_EAD_CRITERIA.md
│   ├── PD_NETFLOW_PATTERN.md
│   ├── PRODUCT_AKAD_MAPPING.md
│   ├── REFACTOR_CKPN_STAGES.md
│   ├── SPEC-CKPN-CALCULATION-MODULES.md
│   └── table.md
├── resources/views/
│   ├── akad-type/  assessment/  collateral/  collateral-setup/
│   ├── calculation/                      # View 8 modul + detail EAD/CKPN
│   ├── dashboard/  import/  office/  product-akad/
│   ├── reporting/  role/  snapshot-history/  user/
│   ├── layouts/    components/   auth/
│   └── profile/
├── routes/
│   ├── web.php                           # Route utama
│   ├── auth.php                          # Route auth Breeze
│   └── console.php
├── tests/
│   ├── Feature/
│   │   ├── AgentExecutionTest.php
│   │   ├── AssessmentCalculationTest.php
│   │   ├── ModularCalculationTest.php
│   │   └── Auth/
│   └── Unit/
├── .env.example                          # Template environment
├── composer.json
├── package.json
├── vite.config.js
├── phpunit.xml
└── AGENTS.md                             # Spesifikasi agent pipeline (referensi)
```

---

## 🗄️ Database

### Tabel Utama

**Master & Konfigurasi**
- `offices` — kantor cabang
- `collaterals`, `collateral_setups` — agunan & konfigurasi agunan
- `akad_types` — daftar akad (murabahah, multijasa, musyarakah, IMBT)
- `product_akad_mappings` — mapping `product_code` → `akad_type`
- `usage_types` — jenis penggunaan
- `bucket_definitions` — definisi bucket hari tunggakan
- `assessment_periods` — periode assessment + method yg dipilih

**Data Operasional**
- `snapshots` — snapshot financing per periode (outstanding, arrears, dsb.)
- `quality_ratings` — rating kualitas historis
- `write_offs` — data write-off
- `recoveries` — riwayat recovery

**Hasil Kalkulasi**
- Hasil EAD & klasifikasi per financing
- `pd_netflow_transitions`, `pd_netflow_compound_flows`, `pd_netflow_bucket_snapshots` — detail PD Net Flow
- Hasil PD Migration, LGD ER, LGD CS, LGD Weighted
- CKPN kolektif & individual, hasil konsolidasi
- Data quality & laporan rekonsiliasi

**Auth & Audit**
- `users`, `roles` (Spatie atau custom sesuai migration)
- `permissions` + pivot per role
- `audit_logs` — immutable log aksi

> Rincian kolom per tabel dapat dilihat di `docs/table.md` dan folder `database/migrations/`.

### Relasi Tingkat Tinggi

```
AssessmentPeriod
  ├── Snapshot (many, per periode)
  ├── Hasil kalkulasi (EAD/PD/LGD/CKPN per periode)
  └── Audit log (many)

Snapshot → akad_type (via ProductAkadMapping)
CollectiveGroup (office + product + usage)
  ├── Transisi bucket (PD Net Flow)
  ├── PD Result
  ├── LGD Result (ER & CS)
  └── CKPN Result
```

---

## 🔄 Alur Kalkulasi CKPN

Pipeline mengacu ke **`AGENTS.md`** sebagai spesifikasi eksekusi berbasis agent:

```
PHASE 1 — Data Preparation
├── DataValidationAgent → validasi & normalisasi data import
└── status periode: VALIDATING

PHASE 2 — Core Calculation
├── EadCalculationAgent → EAD per akad strategy
├── ClassificationAgent → INDIVIDUAL vs COLLECTIVE
└── status: CALCULATING

PHASE 3 — Collective Processing
├── GroupingAgent → group by (office, product, usage)
├── PdNetFlowAgent ATAU PdMigrationAgent → PD per group
├── LgdExpectedRecoveryAgent + LgdCollateralShortfallAgent → LGD per group
├── LgdWeightedAgent → agregasi LGD
└── CkpnCollectiveAgent → CKPN kolektif = EAD × PD × LGD

PHASE 4 — Individual Processing
└── CkpnIndividualAgent → CKPN individual = nilai_tercatat − recovery

PHASE 5 — Consolidation & Reconciliation
├── CkpnConsolidationAgent → total CKPN + data quality check
├── ReconciliationAgent → validasi konsistensi antar hasil
└── status: REVIEW / PASS / FAIL

PHASE 6 — Audit & Approval
└── AuditLogAgent (continuous) → semua aksi tercatat immutable
    └── status: APPROVED → LOCKED (via tombol Finalize di module Detail CKPN)
```

Di aplikasi, pipeline ini dieksekusi secara **modular per halaman** (8 modul di `/calculation/module/*`) sehingga user bisa menjalankan tahapan secara bertahap dan terkontrol — bukan sekadar satu tombol besar.

---

## 🧪 Testing

### Menjalankan Semua Test
```bash
composer run test
# atau:
php artisan test
```

### Menjalankan Test Suite Tertentu
```bash
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

### Menjalankan Test Spesifik
```bash
php artisan test tests/Feature/ModularCalculationTest.php
php artisan test tests/Feature/AgentExecutionTest.php
php artisan test tests/Feature/AssessmentCalculationTest.php
```

### Test yang Tersedia
- `tests/Feature/ModularCalculationTest.php` — integrasi modul kalkulasi (EAD, klasifikasi, PD, LGD, CKPN)
- `tests/Feature/AgentExecutionTest.php` — pipeline agent sesuai spesifikasi `AGENTS.md`
- `tests/Feature/AssessmentCalculationTest.php` — end-to-end kalkulasi per assessment period
- `tests/Feature/Auth/` — fitur autentikasi Breeze
- `tests/Unit/` — unit test komponen kecil

### Test Utilities Bawaan
- `php artisan test:pd-netflow` — command artisan untuk simulation PD Net Flow (via `TestPdNetflowCalculator`)
- File di root seperti `test_calculator.php`, `test_lgd_formula.php`, `check_fks.php` adalah **script debug eksperimen**, bukan bagian dari test suite resmi.

---

## 🔧 Troubleshooting

### Build Tailwind gagal / konflik versi
Project menampung config Tailwind v3-style (`tailwind.config.js`) dengan Vite plugin v4 (`@tailwindcss/vite`). Jika `npm run build` error, pastikan konsisten:
```bash
# Pilih salah satu pendekatan (lihat docs Tailwind v4 migration):
npm run build
php artisan view:clear
```

### SQLite database locked
```bash
rm database/database.sqlite-shm database/database.sqlite-wal
php artisan migrate:fresh --seed
```

### Import XLSX gagal / file besar (OpenSpout)
OpenSpout membaca file secara streaming sehingga file besar umumnya aman. Jika PHP kehabisan memori, naikkan `memory_limit` di `php.ini` atau cek format file harus `.xlsx`/`.xls` yang valid.

### Error 500 saat login
```bash
php artisan key:generate
php artisan config:clear
php artisan cache:clear
```

### Permission denied pada module kalkulasi
Periksa role & permission user di `/role` dan pastikan user ter-associasi role yang tepat.

---

## 📝 Debugging Utility (Root Files)

Beberapa file di root project adalah helper experimental dan **bukan bagian dari aplikasi run-time**:

| File | Fungsi |
|---|---|
| `test_calculator.php` | Eksperimen kalkulasi cepat via CLI |
| `test_lgd_formula.php` | Eksperimen formula LGD |
| `check_fks.php` | Validasi foreign key di database |
| `dump_lgd_sheet.php`, `parse_lgd_xml*.php` | Ekstrak data LGD dari source bawaan |
| `extract_pd_netflow.py`, `pd_output.txt` | Eksperimen output PD Net Flow |
| `lgd_sheets_dump.txt` | Dump data sheet LGD |

---

## 📚 Dokumentasi Tambahan

Folder `docs/`:

| File | Isi |
|---|---|
| `CKPN.md` | Spesifikasi metodologi CKPN lengkap |
| `CLASSIFICATION_EAD_CRITERIA.md` | Kriteria klasifikasi & aturan EAD per akad |
| `PD_NETFLOW_PATTERN.md` | Pola & pseudo-code PD Net Flow |
| `PRODUCT_AKAD_MAPPING.md` | Logika mapping product_code → akad |
| `REFACTOR_CKPN_STAGES.md` | Catatan refactor stage kalkulasi |
| `SPEC-CKPN-CALCULATION-MODULES.md` | Spec UI/UX 8 modul kalkulasi |
| `table.md` | Design schema tabel |

`AGENTS.md` di root menjelaskan spesifikasi **agent pipeline** (14 agent) yang jadi dasar arsitektur kalkulasi.

---

## 🤝 Contributing

1. Fork repository
2. Buat branch fitur: `git checkout -b feature/my-feature`
3. Jalankan test: `composer run test` sebelum push
4. Commit & push: `git commit -m "Add my feature"` lalu `git push origin feature/my-feature`
5. Buka Pull Request

Gaya kode mengikuti **Laravel Pint**: `vendor/bin/pint` untuk auto-fix style.

---

## 📄 Lisensi

Lisensi project: **MIT** (lihat `composer.json`). Framework Laravel berlisensi MIT.

---

**Versi Dokumen:** 1.1 (diperbarui 10 Oktober 2026)
**Dokumen:** README.md — CKPN Calculator BPRS
