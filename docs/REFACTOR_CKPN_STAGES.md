# REFACTOR CKPN — Multi-Stage Pipeline

**Dokumen:** Rencana Teknis Refactor Perhitungan CKPN Bertahap
**Sistem:** CKPN Calculator BPRS
**Tanggal:** 4 Oktober 2026
**Status:** MENUNGGU PERSETUJUAN

---

## 1. Latar Belakang & Tujuan

Saat ini perhitungan CKPN dijalankan **sekali jalan** oleh
[AgentOrchestrator::executeWithProgress()](file:///d:/laragon/www/calculator_ckpn/app/Agents/AgentOrchestrator.php#L55-L89) —
15 agent dieksekusi berurutan dalam satu request SSE, hasil hanya berupa ringkasan
(`payload`) di `ckpn_results.payload_snapshot`. Tidak ada checkpoint, tidak ada hasil
intermediate yang bisa diperiksa, dan user tidak bisa menghentikan/mengulang satu tahap.

**Tujuan refactor:** pecah pipeline menjadi **9 tahap terpisah** yang:
- dapat dijalankan & diperiksa satu per satu (checkpoint),
- menyimpan hasil intermediate ke tabel masing-masing (bukan hanya JSON blob),
- menampilkan 3 tabel kertas kerja (EAD per bucket, pergerakan bucket, compound flow loss)
  dengan **pengelompokan segmentasi** (per kantor / kode akad / jenis penggunaan),
- memakai format kolom yang sama dengan `docs/Kertas kerja.xlsx` sheet `B1.PD-Net Flow`.

**Prinsip:** idempotent per tahap, immutable setelah `LOCKED`, auditable, tidak mengubah
formula inti (`CKPN = EAD × PD × LGD`).

---

## 2. Referensi Format Kertas Kerja (dari `Kertas kerja.xlsx`)

Sheet `B1.PD-Net Flow` berisi 3 tabel yang diminta user:

**Tabel 1 — Pengelompokan EAD/Outstanding per Bucket per Periode** (baris 11–27)

| No. Bucket | Hari Tunggakan | Dec-20 | Jan-21 | Feb-21 | … | Dec-21 |
|---|---|---|---|---|---|---|
| 1 | 0 hari | 77,620,800,000 | 80,034,000,000 | 82,811,600,000 | … | 90,811,200,000 |
| 2 | 1 - 30 hari | 576,400,000 | 847,100,000 | 1,601,100,000 | … | 1,058,400,000 |
| … | … | … | … | … | … | … |
| 14 | >360+WO | 62,200,000 | 52,500,000 | 60,500,000 | … | 48,100,000 |

**Tabel 2 — Persentase Pergerakan Bucket per Periode** (baris 31–44)

| Perpindahan Bucket | Jan-21 | Feb-21 | Mar-21 | … | Dec-22 |
|---|---|---|---|---|---|
| 1 to 2 | 1.09% | 2.00% | 2.27% | … | 1.57% |
| 2 to 3 | 66.93% | 53.26% | 62.28% | … | 68.23% |
| … | … | … | … | … | … |
| 13 to 14 | 97.95% | 74.32% | 80.44% | … | 108.11% |

**Tabel 3 — Compound Flow to Loss per Bucket per Periode** (baris 46–60)

| Bucket | Jan-21 | Feb-21 | … | Dec-21 | PD |
|---|---|---|---|---|---|
| 1 to 2 | 0.10% | 0.17% | … | 0.13% | 0.2% |
| 2 to 3 | 8.34% | 9.01% | … | 10.52% | 10.5% |
| … | … | … | … | … | … |
| 13 to 14 | 97.95% | 74.32% | … | 80.70% | 95.8% |

Catatan: setiap tabel **diulang per kombinasi segmentasi** (per kantor / per akad / per penggunaan).
Bila semua kategori diterapkan, group key = `office_code + akad_code + usage_code`.

---

## 3. Definisi Segmentasi

Kategori pengelompokan (dapat dipilih user, minimal 1, boleh kombinasi):

| Kode | Dimensi | Sumber kolom |
|---|---|---|
| `OFFICE` | Kode Kantor | `snapshot.office_code` |
| `AKAD` | Kode Akad | `product_akad_mappings.akad_code` (via `snapshot.product_code`) |
| `USAGE` | Jenis Penggunaan | `snapshot.usage_code` |

`group_key` = hasil join dimensi terpilih dengan pemisah `|`, contoh:
`01|MURA|01`. Jumlah tabel kertas kerja = jumlah group_key × 3 tabel.

---

## 4. Daftar Tahap (Stages)

Urutan sesuai permintaan user. Setiap tahap = 1 endpoint + 1 tabel hasil.

| # | Stage | Endpoint | Agent | Input | Output tabel |
|---|---|---|---|---|---|
| 1 | Klasifikasi Individu/Kolektif | `POST /calculation/stage/classify` | DataValidationAgent + EadCalculationAgent + ClassificationAgent | `assessment_period_id`, snapshot periode | `stage_classifications` |
| 2 | Perhitungan CKPN Individual | `POST /calculation/stage/individual` | CkpnIndividualAgent | hasil stage 1 (INDIVIDUAL) | `stage_individual_ckpn` |
| 3 | Penentuan Metode PD | `POST /calculation/stage/pd-method` | (config) | `pd_method` | `stage_configs` |
| 4 | Perhitungan PD | `POST /calculation/stage/pd` | PdNetFlowAgent **atau** PdMigrationAgent | hasil stage 3 | `stage_pd_results` |
| 5 | EAD per Bucket per Segmentasi | `POST /calculation/stage/ead-buckets` | BucketingAgent + GroupingAgent | snapshot periode + segmentasi | `stage_ead_buckets` |
| 6 | Pisah Snapshot per Segmentasi (PD & LGD) | `POST /calculation/stage/split-snapshot` | GroupingAgent | snapshot historical + segmentasi | `stage_segmented_snapshots` |
| 7 | Group Historical per Bucket per Periode | `POST /calculation/stage/historical-buckets` | BucketingAgent | hasil stage 6 + window bulan | `stage_historical_buckets` |
| 8 | Persentase Pergerakan Bucket | `POST /calculation/stage/migration-rate` | PdNetFlowAgent (rate calc) | hasil stage 7 | `stage_migration_rates` |
| 9 | Compound Flow Loss | `POST /calculation/stage/compound-flow` | PdNetFlowAgent (PD calc) | hasil stage 8 | `stage_compound_flow` |
| 10 | CKPN Kolektif per Segmentasi | `POST /calculation/stage/collective-ckpn` | CkpnCollectiveAgent | EAD, PD, LGD per group_key | `stage_collective_ckpn` |

Tahap lanjutan (LGD & konsolidasi — dari pipeline existing atau ditambahkan terpisah):
`lgd-er`, `lgd-cs`, `lgd-weighted`, `consolidate`, `reconcile`.

### Detail per Tahap

**Tahap 1 — Klasifikasi**
- Jalankan validasi + EAD + klasifikasi saja.
- Kriteria INDIVIDUAL: `ead > ead_threshold` ATAU `write_off_flag = true` (PPKA **tidak** dihitung).
- Simpan: `loan_number, office_code, akad_code, usage_code, ead_amount, classification, reason, applied_rule`.
- Update `assessment_periods.status = 'VALIDATING'`.

**Tahap 2 — CKPN Individual**
- Ambil baris `classification = INDIVIDUAL`.
- `nilai_tercatat = outstanding + principal_arrears`; `estimasi_recovery = MAX(arus_kas, likuidasi_agunan − sale_cost)`.
- `ckpn_individual = MAX(0, nilai_tercatat − estimasi_recovery)`.
- Simpan per loan. Update status `CALCULATING`.

**Tahap 3 — Penentuan Metode PD**
- Simpan pilihan `pd_method` (`NET_FLOW` / `MIGRATION`) + `historical_months` ke `stage_configs`.
- Tidak ada kalkulasi; menjadi prasyarat tahap 4.

**Tahap 4 — Perhitungan PD**
- `NET_FLOW` → PdNetFlowAgent; `MIGRATION` → PdMigrationAgent.
- Simpan `group_key, pd_calculated, periods_analyzed, method`.

**Tahap 5 — EAD per Bucket per Segmentasi** (= Tabel 1 kertas kerja)
- Bucket periode penilaian (14 bucket, dari `bucket_definitions`).
- Agregasi EAD per `bucket × group_key`. Kolom periode tunggal (posisi penilaian).

**Tahap 6 — Pisah Snapshot per Segmentasi**
- Load snapshot historical dalam window `historical_months` (default 24; min 24 untuk PD).
- Kelompokkan per `group_key`; simpan penanda segmentasi untuk dipakai PD & LGD.

**Tahap 7 — Group Historical per Bucket per Periode**
- Untuk tiap `group_key × periode_snapshot`, hitung jumlah & total EAD per bucket.
- Output = matriks bucket (baris 1–14) × periode (kolom), per group_key → **sumber Tabel 1 historis**.

**Tahap 8 — Persentase Pergerakan Bucket** (= Tabel 2 kertas kerja)
- Lacak migrasi loan antar bucket bulan berurutan:
  `rate[i→i+1] = count(bucket_i → bucket_{i+1}) / count(bucket_i pada periode asal)`.
- Output matriks `"1 to 2" … "13 to 14"` × periode, per group_key.

**Tahap 9 — Compound Flow Loss** (= Tabel 3 kertas kerja)
- `loss_bucket` = bucket ≥ 14 (default/WO).
- `compound = 1 − Π(1 − rate_j)`; kolom `PD` = hasil akhir per bucket asal.
- Output matriks `"1 to 2" … "13 to 14"` × periode + kolom PD, per group_key.

**Tahap 10 — CKPN Kolektif per Segmentasi** (EDITED)
- **Input:** EAD per group (dari `stage_ead_buckets` / `stage_historical_buckets`),
  PD per group (`stage_pd_results`), LGD per group (`lgd_weighted_results` atau default).
- Per `group_key`:
  ```
  total_ead_group = SUM(stage_ead_buckets.ead WHERE group_key = X)
  pd = stage_pd_results.pd_calculated (per group_key)
  lgd = lgd_weighted_results.lgd_final (per group_key) atau default 50%
  ckpn_kolektif = total_ead_group × pd × lgd
  ```
- Validasi: `ckpn_kolektif >= 0`, `ckpn_kolektif <= total_ead_group`.
- Simpan per `group_key` + total.
- Tampilkan sub-total CKPN Kolektif per office, per akad, per usage sesuai segmentasi.

---

## 5. Skema Tabel Baru

Semua tabel intermediate memakai `assessment_period_id` + FK cascade, dan kolom
`group_key` untuk segmentasi. Snapshot JSON tetap disediakan untuk detail penuh.

```
stage_classifications
  id, assessment_period_id FK cascade, loan_number, office_code, akad_code, usage_code,
  ead_amount DECIMAL(18,2), classification ENUM(INDIVIDUAL,COLLECTIVE),
  reason, applied_rule, created_at, updated_at
  INDEX(assessment_period_id, classification), INDEX(group_key)

stage_individual_ckpn
  id, assessment_period_id FK cascade, loan_number, nilai_tercatat DECIMAL(18,2),
  estimated_recovery DECIMAL(18,2), ckpn_individual DECIMAL(18,2),
  recovery_method, created_at, updated_at

stage_configs
  id, assessment_period_id FK cascade, pd_method, lgd_method,
  historical_months INT, sale_cost_percentage DECIMAL(5,4), created_at, updated_at

stage_pd_results
  id, assessment_period_id FK cascade, group_key, pd_calculated DECIMAL(9,6),
  method, periods_analyzed INT, detail JSON, created_at, updated_at
  UNIQUE(assessment_period_id, group_key)

stage_ead_buckets              -- Tabel 1 (posisi penilaian)
  id, assessment_period_id FK cascade, group_key, bucket_no TINYINT,
  hari_tunggakan VARCHAR(20), ead DECIMAL(18,2), member_count INT,
  created_at, updated_at
  INDEX(assessment_period_id, group_key, bucket_no)

stage_segmented_snapshots
  id, assessment_period_id FK cascade, group_key, periode_snapshot CHAR(6),
  loan_number, bucket_no TINYINT, ead DECIMAL(18,2), created_at, updated_at
  INDEX(assessment_period_id, group_key, periode_snapshot)

stage_historical_buckets       -- Tabel 1 historis
  id, assessment_period_id FK cascade, group_key, periode_snapshot CHAR(6),
  bucket_no TINYINT, hari_tunggakan VARCHAR(20), ead DECIMAL(18,2),
  member_count INT, created_at, updated_at
  UNIQUE(assessment_period_id, group_key, periode_snapshot, bucket_no)

stage_migration_rates          -- Tabel 2
  id, assessment_period_id FK cascade, group_key, periode_snapshot CHAR(6),
  from_bucket TINYINT, to_bucket TINYINT, rate DECIMAL(9,6),
  created_at, updated_at
  UNIQUE(assessment_period_id, group_key, periode_snapshot, from_bucket, to_bucket)

stage_compound_flow            -- Tabel 3
  id, assessment_period_id FK cascade, group_key, from_bucket TINYINT,
  periode_snapshot CHAR(6), compound_loss DECIMAL(9,6), created_at, updated_at
  INDEX(assessment_period_id, group_key, from_bucket)

stage_collective_ckpn          -- CKPN Kolektif per segmentasi
  id, assessment_period_id FK cascade, group_key,
  office_code, akad_code, usage_code,
  total_ead DECIMAL(18,2), pd DECIMAL(9,6), lgd DECIMAL(9,6),
  lgd_method VARCHAR(30), ckpn_kolektif DECIMAL(18,2),
  member_count INT, created_at, updated_at
  UNIQUE(assessment_period_id, group_key)
```

Catatan: kolom `group_key` = join dimensi terpilih. Bila `AKAD` dipakai, tambahkan kolom
`akad_code` di `stage_classifications`/`stage_segmented_snapshots` (via mapping product_code).

---

## 6. Endpoint & Route

```php
Route::prefix('calculation')->middleware('auth')->group(function () {
    Route::get('/', [CalculationController::class, 'index'])->name('calculation.index');
    Route::get('/{assessmentPeriod}/stages', [CalculationController::class, 'stages'])->name('calculation.stages');

    Route::post('/stage/classify',          [CalculationStageController::class, 'classify'])->name('calculation.stage.classify');
    Route::post('/stage/individual',        [CalculationStageController::class, 'individual'])->name('calculation.stage.individual');
    Route::post('/stage/pd-method',         [CalculationStageController::class, 'pdMethod'])->name('calculation.stage.pd-method');
    Route::post('/stage/pd',                [CalculationStageController::class, 'pd'])->name('calculation.stage.pd');
    Route::post('/stage/ead-buckets',       [CalculationStageController::class, 'eadBuckets'])->name('calculation.stage.ead-buckets');
    Route::post('/stage/split-snapshot',    [CalculationStageController::class, 'splitSnapshot'])->name('calculation.stage.split-snapshot');
    Route::post('/stage/historical-buckets',[CalculationStageController::class, 'historicalBuckets'])->name('calculation.stage.historical-buckets');
    Route::post('/stage/migration-rate',    [CalculationStageController::class, 'migrationRate'])->name('calculation.stage.migration-rate');
    Route::post('/stage/compound-flow',     [CalculationStageController::class, 'compoundFlow'])->name('calculation.stage.compound-flow');
    Route::post('/stage/collective-ckpn',   [CalculationStageController::class, 'collectiveCkpn'])->name('calculation.stage.collective-ckpn');

    Route::get('/{assessmentPeriod}/export', [CalculationController::class, 'exportResults'])->name('calculation.export');
});
```

Kontroler baru `CalculationStageController` memakai `response()->stream()` (SSE) seperti
[runCalculation()](file:///d:/laragon/www/calculator_ckpn/app/Http/Controllers/CalculationController.php#L19-L119),
dengan `agent_configurations.depends_on_agent` sebagai guard urutan (tolak tahap bila prasyarat belum ada).

---

## 7. Mockup View

### 7.1 Halaman Tahapan — `calculation/stages.blade.php`

Stepper vertikal 10 tahap; tiap kartu: nama tahap, status (Pending / Running / Done),
tombol **Run**, ringkasan hasil, dan link **Lihat Tabel**. Progress SSE per tahap
(pola log panel sama seperti `calculation/index.blade.php`).

### 7.2 Tabel 1 — EAD per Bucket per Segmentasi (`calculation/tables/ead-buckets.blade.php`)

- Header: dropdown segmentasi (`group_key`), toggle dimensi.
- Kolom: `No. Bucket | Hari Tunggakan | <periode>` (bila historis) / `EAD` (posisi).
- Format angka `id-ID` ribuan, rata kanan, subtotal per bucket.

### 7.3 Tabel 2 — Pergerakan Bucket (`calculation/tables/migration-rate.blade.php`)

- Kolom: `Perpindahan Bucket | Jan-21 | Feb-21 | … `.
- Nilai persen 2 desimal; highlight sel > 100% (indikasi anomali data, seperti `108.11%` di contoh).
- Warna gradien naik (hijau→merah) makin dekat bucket 14.

### 7.4 Tabel 3 — Compound Flow Loss (`calculation/tables/compound-flow.blade.php`)

- Kolom: `Bucket | <periode…> | PD`.
- Kolom `PD` = nilai akhir per bucket asal (dipakai `stage_pd_results`).
- Export CSV per tabel (reuse pola `exportResults`).

### 7.5 Tabel 4 — CKPN Kolektif per Segmentasi (`calculation/tables/collective-ckpn.blade.php`)

- Kolom: `Segmen (group_key) | Total EAD | PD | LGD | CKPN Kolektif | Jumlah Debitur`.
- Baris sub-total per kantor / per akad / per penggunaan (accordion sesuai segmentasi terpilih).
- Baris TOTAL di bawah; angka format `id-ID`, kolom CKPN di-highlight.
- Footer menampilkan `total_collective_ckpn` dan cross-check `Σ per group = total`.

### 7.6 Halaman Hasil Akhir

`calculation/results.blade.php` dipertahankan (ringkasan EAD/PD/LGD/CKPN) + tab ke 4 tabel
kertas kerja di atas (3 tabel PD + 1 tabel CKPN Kolektif).

---

## 8. Perubahan File

| File | Aksi |
|---|---|
| `app/Agents/AgentOrchestrator.php` | Tambah method `executeStage(string $stage)`; `executeWithProgress()` tetap untuk kompatibilitas |
| `app/Http/Controllers/CalculationStageController.php` | **Baru** — 10 endpoint SSE |
| `app/Http/Controllers/CalculationController.php` | Tambah `stages()`; `runCalculation()` tetap |
| `database/migrations/xxxx_create_calculation_stage_tables.php` | **Baru** — 10 tabel di §5 |
| `routes/web.php` | Tambah route §6 |
| `resources/views/calculation/stages.blade.php` | **Baru** — stepper |
| `resources/views/calculation/tables/*.blade.php` | **Baru** — 3 tabel kertas kerja |
| `resources/views/calculation/index.blade.php` | Tambah link ke halaman tahapan |
| `app/Models/CalculationStage*.php` | **Baru** — model per tabel (opsional bila pakai query builder) |

---

## 9. Acceptance Criteria

- [ ] Tiap tahap dapat dijalankan terpisah & idempotent (re-run → hasil sama).
- [ ] Tahap 4 menolak dijalankan bila tahap 3 belum ada.
- [ ] `group_key` konsisten di seluruh tabel intermediate.
- [ ] 3 tabel kertas kerja menampilkan kolom persis seperti `Kertas kerja.xlsx`.
- [ ] Tabel 2 mendeteksi & menandai rate > 100% (warning, tidak gagal).
- [ ] Compound flow (tahap 9) menghasilkan PD per bucket asal; cocok dengan kolom `PD` kertas kerja.
- [ ] Total CKPN akhir tetap = Individual + Collective.
- [ ] Audit log tercatat per tahap (`agent_name`, `action`, `record_id`).
- [ ] Payload cache tidak melebihi `LONGTEXT` (trim array mentah per-loan).

---

## 10. Catatan

- Formula inti tidak berubah; refactor hanya memecah eksekusi & menambah persistensi intermediate.
- PPKA tetap **tidak** dipakai (sudah ada di snapshot).
- Minimum data: PD ≥ 24 bulan, LGD ≥ 5 tahun; bila kurang → default + warning (sesuai `AGENTS.md`).
- Migrasi idempotent (`hasTable` guard) agar aman di-rerun.

---

## 11. Sign-off

**Disusun oleh:** AI Assistant
**Tanggal:** 2026-10-04
**Status:** MENUNGGU PERSETUJUAN USER

Bila disetujui, implementasi dimulai dari migrasi tabel (§5) → `CalculationStageController`
(§6) → view (§7).
