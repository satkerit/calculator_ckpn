# MEMORY.md — Context Ringkas Project

> **Fungsi file ini:** memori jangka pendek project. Cukup baca file ini untuk memahami
> konteks, lalu buka file spesifik hanya saat butuh detail. **Jangan** menyalin isi file
> lain ke sini — cukup tautkan jalurnya.
>
> **Aturan perbarui:** tambahkan entri baru di [§10 Log Perubahan](#10-log-perubahan-append-only)
> setiap kali ada perubahan arsitektur, tabel, konvensi, atau keputusan desain.
> Hapus/rapikan bagian yang sudah basi.

**Terakhir diperbarui:** 11 Oktober 2026  
**Kondisi:** working tree ada perubahan belum di-commit (lihat §9)

---

## 1. Project dalam 30 detik

**CKPN Calculator BPRS** — sistem kalkulasi Cadangan Kerugian Penurunan Nilai (PSAK 414)
untuk Bank Perkreditan Rakyat Syariah. Alur: import Excel → setup master → konfigurasi →
jalankan 8 modul kalkulasi bertahap → review → Finalize (lock) → laporan & export XLSX.

- **Stack:** Laravel 13.34 (PHP ^8.3) · Filament ^5.9 · Laravel Breeze (Blade + Alpine)
  · Tailwind (Vite plugin v4 + `tailwind.config.js` gaya v3) · OpenSpout (Excel) · PHPUnit ^12.5
- **DB dev:** MySQL `db_calculator` di Laragon (`127.0.0.1:3306`, root tanpa password)
  — *bukan* SQLite. Test suite pakai SQLite `:memory:` (`phpunit.xml`).
- **OS:** Windows + Git Bash. Repo memakai `eol=lf` (`.gitattributes`); file working tree
  banyak CRLF → muncul warning "CRLF will replaced by LF" itu normal, jangan di-mass-replace.
- **Entry point dokumen:** `README.md` (cara pakai, routes, instalasi) ·
  `AGENTS.md` (spesifikasi 14 agent — **hanya referensi, tidak ada implementasinya**).

---

## 2. Perintah penting

```bash
php artisan test                          # semua test (~35 dtk)
php artisan test --testsuite=Feature      # terverifikasi: 36 passed, 1 skipped
php artisan test tests/Feature/ModularCalculationTest.php
php artisan serve                         # http://127.0.0.1:8000
composer run test                         # config:clear + test
vendor/bin/pint                           # format (Laravel Pint, tanpa pint.json → default preset)
php artisan migrate                       # migrasi
php artisan pd:test                       # simulasi PD Net Flow (app/Console/Commands/TestPdNetflowCalculator.php)
```

Test environment: `APP_ENV=testing`, SQLite `:memory:`, semua config di `phpunit.xml`
(ambil dari sana, **bukan** dari `.env.testing` yang isinya beda/obsolete).

---

## 3. Peta arsitektur

```
routes/web.php (semua di middleware auth)
  └─ app/Http/Controllers/**            → controller per modul
       ├─ app/Services/**               → logika bisnis murni
       ├─ DB (tabel stage_*)            → hasil intermediate PER PERIODE
       └─ resources/views/**            → Blade (layout: layouts/app.blade.php)
```

- **Semua modul eksekusi sinkron** dalam satu request (< 60 dtk). Tidak ada queue, tidak
  ada SSE. `app/Agents/AgentOrchestrator.php` (pipeline 15 agent satu tombol) **sudah dihapus**
  — `AGENTS.md` & `docs/REFACTOR_CKPN_STAGES.md` bicara soal hal itu, anggap arsip.
- **Hasil kalkulasi = tabel `stage_*`**, satu set per `assessment_period_id`.
  Sumber tunggal untuk report/export: `app/Support/CalculationResults.php`
  (menggantikan cache `calculation_results_*` dan tabel `ckpn_results`).
- RBAC: `App\Http\Middleware\CheckPermission` + alias `permission` ada di `bootstrap/app.php`,
  tapi **belum dipakai di route mana pun** — cukup `auth`.

---

## 4. Peta 8 modul kalkulasi

Route prefix `calculation/module/*`, nama route `calculation.module.*`,
view di `resources/views/calculation/modules/*.blade.php`.

| # | Modul | Route | Controller | Output (tulis) |
|---|---|---|---|---|
| 1 | Konfigurasi | `/config` | `ConfigurationController` | `stage_configs` (1 baris/periode) |
| 2 | Klasifikasi & Segmentasi | `/classification` | `ClassificationController` | `stage_classifications` + `snapshot.bucket_no` |
| 3 | PD Net Flow | `/pd-netflow` | `PdNetFlowController` | `stage_pd_results`, `stage_segmented_snapshots`, `stage_historical_buckets`, `stage_migration_rates`, `stage_compound_flow` + `pd_netflow_*` |
| 4 | PD Migration | `/pd-migration` | `PdMigrationController` | `stage_pd_results` (method=MIGRATION) |
| 5 | LGD Expected Recovery | `/lgd-er` | `LgdExpectedRecoveryController` | `stage_lgd_results` (method=ER) |
| 6 | LGD Collateral Shortfall | `/lgd-cs` | `LgdCollateralShortfallController` | `stage_lgd_results` (method=CS) |
| 7 | Detail PD & EAD | `/detail-pd-ead` | `DetailPdEadController` | read-only |
| 8 | Detail & Hitung CKPN | `/detail-ckpn` (POST = **Finalize**) | `DetailCkpnController` | `stage_individual_ckpn`, `stage_collective_ckpn` |

Lain-lain: `/pd-netflow/{period}/detail` → `PdNetflowDetailController`;
hasil tampil `calculation/ckpn/{individual,collective}` → `CkpnDetailController`;
export `calculation/export/*` → `Export/CalculationExportController`;
report `reporting/*` → `ReportingController`.

**Urutan wajib:** Config → Classification → (PD Net Flow *atau* PD Migration) →
LGD ER + LGD CS → Detail PD-EAD → Detail CKPN (Finalize).

### Helper bersama — `Calculation/BaseCalculationController.php` (baca dulu sebelum menulis modul baru)

| Method | Fungsi |
|---|---|
| `configFor()` / `saveConfig()` | baca/tulis `stage_configs` + `CONFIG_DEFAULTS` |
| `resolvePeriod()` / `periods()` | resolve periode dari request; **auto-create periode `DEFAULT`** bila kosong |
| `segmentDimensions()` / `buildSegment()` | dimensi segmentasi → `group_key` |
| `cleanStage()` / `safeInsert()` / `safeUpsert()` | tulis stage — selalu cek `Schema::hasTable()` dulu (aman di test/fresh install) |
| `markResults()` / `staleResultTypes()` / `fingerprintFrom()` | deteksi hasil basi: setiap run menulis fingerprint config ke `stage_calculation_marks`; config berubah → hasil PD/LGD/CKPN “TIDAK VALID” + Finalize diblokir |
| `audit()` | tulis `audit_logs` (no-op bila tabel tidak ada) |
| `backTo()` / `formatSummary()` | redirect + flash ringkasan |
| `loadCollectiveGroups()` / `collectiveGroupKey()` | grup kolektif dari `stage_classifications` |

`CONFIG_DEFAULTS` (fallback bila `stage_configs` kosong):
`pd_method=NET_FLOW`, `lgd_method=WEIGHTED`, `historical_months=36`,
`lgd_historical_months=60`, `sale_cost_percentage=0.05`, `segment_dimensions=OFFICE`,
`individual_top_n=30`, `npl_ratings=[3,4,5]`.

---

## 5. Model data kunci

### `snapshot` = SATU-SATUNYA sumber data financing

Tabel `financing` **sudah di-drop** (`2026_10_03_134316_refactor_drop_financing_add_columns_to_snapshot.php`).
Semua data kontrak ada di `snapshot`, per baris per periode.

Kolom: `loan_number`, `product_code`, `office_code`, `usage_type`, `tglwo`,
`effective_date`, `expired_date`, `outstanding_balance`, `principal_arrears`,
`days_past_due`, `quality_rating`, `financing_contract_code`, `active_flag`,
`write_off_flag`, `ppka_value`, `periode_snapshot`, `bucket_no`.

> Kolom `akad_code` & `usage_code` **di-drop** dari snapshot → resolve saat runtime:
> akad via `ProductAkadMappingService::resolveAkadCode(product_code)`,
> usage via `snapshot.usage_type`.

- `periode_snapshot` format **`Ym`** (mis. `202610`), diturunkan dari
  `assessment_periods.assessment_date`.
- Validasi "`assessment_date` tidak boleh di masa depan" **belum ada di kode** — itu
  hanya aturan di spesifikasi `AGENTS.md`. Jangan mengandalkannya.

### Tabel stage (hasil per tahap, selalu scope `assessment_period_id`)

`stage_configs`, `stage_classifications`, `stage_individual_ckpn`, `stage_pd_results`,
`stage_ead_buckets`, `stage_segmented_snapshots`, `stage_historical_buckets`,
`stage_migration_rates`, `stage_compound_flow`, `stage_collective_ckpn`,
`stage_lgd_results` + `pd_netflow_bucket_snapshots`, `pd_netflow_transitions`,
`pd_netflow_compound_flows` + `stage_calculation_marks` (penanda fingerprint config
per `result_type`: PD | LGD | CKPN — lihat §10, 11 Okt).

Daftar resmi juga dicek di `tests/Feature/ModularCalculationTest.php`
("stage table list covers all calculation outputs") — **tambah tabel stage ⇒ update test itu**.

### Master / operasional

`offices`, `akad_types` (kolom `kode_akad`, `ead_rule_type`, `requires_due_date`, `active`),
`usage_types`, `product_akad_mappings` (product_code → akad_code), `collateral`,
`collateral_setup`, `quality_ratings`, `bucket_definitions` (14 bucket, B0–B13),
`write_offs`, `recoveries`, `assessment_periods`, `users`/`roles`/`permissions`,
`audit_logs` (immutable).

---

## 6. Logika bisnis inti (yang harus tetap konsisten)

| Komponen | Rumus / aturan | Lokasi kode |
|---|---|---|
| EAD | Strategy per akad (`MURA`, `MJA`, `MUSY`, `IMBT`); fallback `MurabahahStrategy` | `Services/Ead/EadStrategyFactory.php` |
| Klasifikasi | **INDIVIDUAL** = NPL (write_off **atau** `quality_rating ∈ npl_ratings[3,4,5]`) **DAN** masuk TOP-N (`individual_top_n`, default 30) berdasarkan `outstanding+principal_arrears` terbesar. Sisanya COLLECTIVE. `applied_rule = NPL_TOP_N_OUTSTANDING` | `ClassificationController::run()` |
| PD | Net Flow (transition matrix antar bucket + compound multi-periode) **atau** Migration (rating 1–5 + WO). Bounded [0,1] | `Services/PdNetflow/*`, `PdMigrationController` |
| LGD ER | `LGD = 1 − avg(recovery_ratio)` dari riwayat write-off (window `lgd_historical_months`, default 60) | `LgdExpectedRecoveryController` |
| LGD CS | `LGD = shortfall / total_exposure_writeoff` | `LgdCollateralShortfallController` |
| LGD final | `lgd_method` = ER / CS / WEIGHTED → kolom `lgd` di `stage_lgd_results` | `DetailCkpnController` |
| CKPN Individual | `max(0, nilai_tercatat − net_recovery)`; `net_recovery = liquidity_value × (1 − sale_cost%)` | `Services/Ckpn/CkpnCalculator::calculateIndividual()` |
| CKPN Kolektif | `EAD × PD × LGD` per grup segmentasi | `CkpnCalculator::calculateCollective()` |
| Bucket | 14 bucket dari `days_past_due`; `BucketAssignmentService::assignBucketsForPeriodFast(Ym)` menulis `snapshot.bucket_no` | `Services/Bucket/BucketAssignmentService.php` |

### Segmentasi & `group_key`

Dimensi dipilih user: `OFFICE` (office_code), `AKAD` (akad_code), `USAGE` (usage_code) —
bisa kombinasi, minimal 1. `group_key` = nilai dimensi terpilih digabung `|`,
dimensi yang tidak dipilih diisi `NA` (mis. `01|03|NA`).

> ⚠️ **Ketidakcocokan yang belum diverifikasi:** `BaseCalculationController::buildSegment()`
> melewatkan dimensi tak dipilih (menghasilkan `01|03`), sementara komentar
> `loadCollectiveGroups()` menyebut format `01|03|NA`. **Sebelum mengubah format
> `group_key`, cek dulu format yang dipakai `PdNetFlowController` + `DetailCkpnController`
> + export**, karena seluruh join antar-modul memakai string ini.

---

## 7. Jebakan yang sudah diketahui (baca sebelum edit)

1. **`stage_ead_buckets` tidak ditulis modul mana pun saat ini** (tabel legacy dari
   pipeline `POST /calculation/stage/*` yang sudah dihapus). `DetailCkpnController`
   sudah fallback ke `stage_classifications` bila kosong. Jangan asumsikan ada isinya.
2. **Nama kolom ≠ nama config key:** DB `stage_configs.segmentation_dims`
   ↔ code `segment_dimensions`. Sudah dimapping di `saveConfig()`/`configFor()`; kalau
   tambah config baru, tambahkan di **kedua** tempat + `CONFIG_DEFAULTS` + whitelist
   `Schema::getColumnListing` (kalau tidak → error 42S22).
3. **Selalu tulis lewat `safeInsert`/`safeUpsert`/`cleanStage`** — helper ini cek
   `Schema::hasTable()`; query `DB::table()` polos akan gagal di test/fresh install
   (banyak migrasi dibungkus `if (!Schema::hasTable(...))`).
4. **Filter snapshot wajib:** `active_flag = true` dan `write_off_flag` null/false
   (lihat `ClassificationController`) — snapshot historis berisi baris non-aktif.
5. **`EadStrategyFactory` cache static `$configCache`** — perubahan `akad_types` tidak
   terlihat sampai request berikutnya (aman di web, jebak di long-running/test).
6. **LGD ER & CS menulis ke tabel yang sama** (`stage_lgd_results`) dibedakan kolom
   method — bersihkan scope periode sebelum tulis ulang (sudah dilakukan via `cleanStage`).
7. **File debug di root** (`debug_t1.php`, `test_calculator.php`, `check_fks.php`,
   `extract_pd_netflow.py`, `pd_output.txt`) bukan bagian aplikasi. Sebagian sudah dihapus
   di working tree — jangan di-commit lagi.
8. **Line endings CRLF** di working tree; jangan jalankan konversi massal.
9. **`.env` ≠ `.env.testing` ≠ `phpunit.xml`** — test selalu pakai `phpunit.xml`.
10. **Tailwind campur versi** (plugin v4 + config v3) — kalau `npm run build` gagal,
    samakan versinya (lihat README Troubleshooting).

---

## 8. Resep cepat

**Tambah modul kalkulasi baru**
1. Controller di `app/Http/Controllers/Calculation/` extends `BaseCalculationController`,
   method `index()` (tampil) + `run()` (eksekusi).
2. Route GET+POST di `routes/web.php` dalam blok `calculation/module`, name `calculation.module.*`.
3. View `resources/views/calculation/modules/<nama>.blade.php` (ikuti pola view modul lain).
4. Tulis hasil ke tabel `stage_*` (buat migration baru dengan guard `Schema::hasTable`),
   tambahkan ke daftar test `ModularCalculationTest`.
5. Tambah link menu di **`resources/views/layouts/app.blade.php`** (sidebar inline, blok
   "CKPN Calculation" ± baris 71). `layouts/navigation.blade.php` = sisa Breeze, tidak dipakai.

**Mengubah formula** → ubah di `app/Services/**` (bukan di controller), tambah/ubah test
di `tests/` lalu `php artisan test`.

**Menambah kolom config per periode** → migration `Schema::hasColumn` guard →
`CONFIG_DEFAULTS` → `saveConfig`/`configFor` → form di view konfigurasi.

**Mengubah rentang/tampilan export** → `app/Http/Controllers/Export/CalculationExportController.php`.

---

## 9. Status saat ini (10 Okt 2026)

- **Commit terakhir:** `e3236c2` "Revise LGD Collateral Shortfall & Weighted LGD per kertas kerja B4" (repo baru 2 commit).
- **Belum di-commit:** perbaikan PD Net Flow (`PdNetFlowController`, `PdNetflowCalculator`,
  `PdNetflowService`, `BucketAssignmentService`, `DetailCkpnController`, `PdnetflowDetailController`),
  update view modul + `layouts/app.blade.php`, dan penghapusan file debug root.
- **Test terakhir:** `php artisan test --testsuite=Feature` → **36 passed, 1 skipped,
  0 failed** (1 skip: "Skema master belum lengkap di environment ini").
- **Docs `docs/TASK-AI.md`** menampilkan status "PRODUCTION READY" tapi bagian TODO
  (Phase 6–10) tidak diperbarui — **anggap basi**, jangan jadi sumber status.

---

## 10. Log Perubahan (append-only)

> Tambah entri terbaru di **atas**. Format: `### YYYY-MM-DD — ringkasan`.

### 2026-10-11 — Invalidasi hasil kalkulasi saat konfigurasi berubah
Perubahan konfigurasi (segmentasi / historical window / metode PD) kini membatalkan
hasil kalkulasi lama: setiap run menulis **fingerprint** config ke tabel baru
`stage_calculation_marks` (`result_type` = PD | LGD | CKPN).

- Pemicu: segmentasi + window PD + metode PD membatalkan **PD & CKPN Kolektif**;
  hanya segmentasi yang membatalkan **LGD**.
- Hasil tanpa penanda (data lama) dianggap basi → wajib hitung ulang sekali.
- Banner merah “TIDAK VALID” di halaman PD Netflow, PD Migration, LGD ER/CS, Detail CKPN;
  Finalize CKPN **diblokir** bila PD/LGD basi (`DetailCkpnController::finalize`).
- Finalize kini juga mensyaratkan `stage_pd_results` sesuai `pd_method` aktif.
- Config save (`ConfigurationController`) memberi tahu hasil mana yang jadi tidak valid +
  audit `CONFIG/INVALIDATE_RESULTS`.
- Helper baru di `BaseCalculationController`: `fingerprintFrom()`, `markResults()`,
  `staleResultTypes()`, `staleResultLabel()`.
- Test: `tests/Feature/CalculationConfigInvalidationTest.php` (9 tes, RefreshDatabase).

### 2026-10-10 — Pembuatan MEMORY.md
File context ringkas ini dibuat agar perubahan/penambahan berikutnya cukup membaca
§1–§9 tanpa membaca seluruh repo. Menandai ulang: `stage_ead_buckets` legacy,
ketidakcocokan format `group_key`, dan working tree yang belum di-commit.

### 2026-10-05 — Refactor multi-stage selesai (arsip)
Pipeline dipecah jadi 8 modul + tabel `stage_*`; `financing` di-drop (pindah ke `snapshot`);
`snapshot.akad_code`/`usage_code` di-drop; `CalculationResults` jadi sumber tunggal report.

---

**Referensi detail (jangan duplikasi isinya ke sini):**
`README.md` (cara pakai & routes) · `AGENTS.md` (spesifikasi agent, arsip) ·
`docs/SPEC-CKPN-CALCULATION-MODULES.md` (spec 8 modul — paling relevan) ·
`docs/REFACTOR_CKPN_STAGES.md` (rencana refactor, sebagian sudah berubah) ·
`docs/CKPN.md` (metodologi PSAK 414) · `docs/Kertas kerja.xlsx` (format tabel target) ·
`docs/table.md` (skema).
