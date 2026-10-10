# Spec & Plan: Rebuild Calculation Hub sebagai 8 Modul CKPN Calculation

**Dokumen:** Spec/Plan arsitektur ulang Calculation Hub
**Sistem:** CKPN Calculator BPRS
**Tanggal:** 2026-10-04
**Basis:** Permintaan user untuk menghapus Calculation Hub & membuat 8 modul menu di bawah CKPN Calculation

---

## 1. Tujuan & Non-Tujuan

### Tujuan
- Hapus entitas "Calculation Hub" & "Calculation Stages Pipeline" sebagai menu single-page.
- Ganti dengan 8 modul menu independen di bawah grup navigasi "CKPN Calculation", masing-masing dengan halaman, aksi, dan export sendiri.
- Setiap modul eksekusi **satu tahap kalkulasi** (atau satu set tahap yang kohesi) dengan data terisolasi.
- **Combo segmentation** (multi-dimensi dinamis): OFF, OFF|AKD, OFF|USG, AKD|USG, OFF|AKD|USG — tanpa restart tahap.
- LGD dihitung **per-segment** menggunakan 2 metode (Expected Recovery + Collateral Shortfall) yang tersedia simultan.
- Export CSV/XLSX per modul: **input data + parameter + output** agar validasi backtesting bisa dilakukan offline.
- Layout & navigasi konsisten dengan modul lain.

### Non-Tujuan (di luar scope iterasi ini)
- Perhitungan ulang model bisnis (formula CKPN tetap PSAK).
- Migrasi ke reporting framework baru (Tetap pakai Blade + Chart.js).
- Tambah dependency baru (fokus stdlib + library yang sudah ada).
- Multi-tenancy / RBAC granular.

---

## 2. Struktur Modul

| # | Modul Menu | Route Prefix | Tujuan | Input | Output |
|---|---|---|---|---|---|
| 1 | **Konfigurasi CKPN** | `calculation.config` | Set metode PD, metode LGD, window, threshold, dimensi segmentasi | Form edit | `stage_configs` (1 row per period) |
| 2 | **Klasifikasi & Segmentasi** | `calculation.classification` | Tentukan INDIVIDUAL vs COLLECTIVE per loan + segment kolektif | Snapshot periode | `stage_classifications` |
| 3 | **PD Net Flow** | `calculation.pd-netflow` | Hitung PD via net-flow method, dengan segmentasi | `stage_classifications` COLLECTIVE + historical snapshots | `stage_pd_results` (method=NET_FLOW) + `stage_segmented_snapshots` |
| 4 | **PD Migration** | `calculation.pd-migration` | Hitung PD via migration matrix (alternatif) | Sama dgn #3 | `stage_pd_results` (method=MIGRATION) |
| 5 | **LGD Expected Recovery** | `calculation.lgd-er` | Hitung recovery rate historis WO → LGD per segment | Historical write-offs + recovery | `stage_lgd_results` (method=ER) |
| 6 | **LGD Collateral Shortfall** | `calculation.lgd-cs` | Hitung shortfall agunan WO → LGD per segment | Collateral + WO data | `stage_lgd_results` (method=CS) |
| 7 | **Detail PD & EAD** | `calculation.detail-pd-ead` | Tabel kertas kerja: per-loan EAD, per-group PD, distribusi bucket | Hasil #2-#6 | Read-only view dari stage_* |
| 8 | **Detail & Hitung CKPN** | `calculation.detail-ckpn` | Detail per-loan (Individual) + per-group (Kolektif); tombol finalisasi | Hasil #1-#6 | `stage_individual_ckpn`, `stage_collective_ckpn` |

Tambahan menu (informasi):
- **Laporan & Backtesting** (sudah ada): `reporting/*` route dipakai ulang, plus 3 export baru per modul kalkulasi.

---

## 3. Arsitektur Modular (Controller per Modul)

Ganti 1 controller monolith (`CalculationStageController`, ~1050 LOC) → 8 controller kecil + 1 base:

```
app/Http/Controllers/Calculation/
├── BaseCalculationController.php          # helper: loadConfig/saveConfig, bulkLoadSnapshots, redirectWithFlash
├── ConfigurationController.php            # modul 1
├── ClassificationController.php           # modul 2
├── PdNetFlowController.php                # modul 3
├── PdMigrationController.php              # modul 4
├── LgdExpectedRecoveryController.php      # modul 5
├── LgdCollateralShortfallController.php   # modul 6
├── DetailPdEadController.php              # modul 7
└── DetailCkpnController.php               # modul 8

app/Http/Controllers/Export/
└── CalculationExportController.php        # export XLSX untuk 8 modul + reporting
```

**Kenapa pecah:** Setiap modul = 1 use-case tertutup. SSE stream dihapus total — eksekusi **sync** dengan asumsi < 60 detik per modul. Redirect kembali ke view modul dengan flash message berisi summary hasil.

**Tanpa queue infrastructure** — tidak ada tabel `calculation_jobs`, tidak ada polling. User klik tombol → tunggu ≤ 60 detik → kembali ke halaman dengan hasil.

---

## 4. Schema Baru (Replace stage_* Lama)

**Strategi:** Rename lama → `_legacy` lalu build baru dengan struktur lebih kuat. Migration final: copy data valid, drop legacy.

### 4.1 Tabel Baru

```
stage_classifications (replace)
├── + combo_segmentation_key (varchar 50) -- hasil: "OFF|AKD" atau "OFF|AKD|USG"
├── + combo_segments_json (json)            -- list of segment keys aktif
├── + pd_method (varchar 20 nullable)     -- NET_FLOW | MIGRATION | null
├── + created_by, run_id (uuid)           -- tracking

stage_segmented_snapshots (replace)
├── + combo_segmentation_key (varchar 50)
├── + segmentation_dimension_set (varchar 50)  -- 'OFFICE|AKAD|USAGE' dsb
├── + bucket_no (TETAP)

stage_pd_results (replace)
├── + combo_segmentation_key (varchar 50)
├── + compound_periods_used (json)         -- snapshot periods analyzed
├── + detail (json, lebih kaya)

stage_lgd_results (BARU — gabungan ER + CS per segment)
├── assessment_period_id, group_key, combo_segmentation_key
├── office_code, akad_code, usage_code
├── method (ER | CS | WEIGHTED)
├── recovery_rate (decimal nullable)       -- untuk ER
├── total_writeoff (decimal nullable)      -- untuk ER
├── total_recovery (decimal nullable)      -- untuk ER
├── years_analyzed (int nullable)
├── avg_shortfall (decimal nullable)        -- untuk CS
├── total_exposure_wo (decimal nullable)
├── total_shortfall (decimal nullable)
├── lgd_calculated (decimal 5,4)
├── detail (json)

stage_individual_ckpn (replace — TETAP sama, tambah run_id)
├── + run_id, created_by

stage_collective_ckpn (replace)
├── + combo_segmentation_key (varchar 50)
├── + pd_method (varchar 20)
├── + lgd_method (varchar 30)
├── + lgd_er (decimal nullable)
├── + lgd_cs (decimal nullable)
├── + run_id

stage_ead_per_loan (BARU — kertas kerja per loan untuk backtesting)
├── assessment_period_id, loan_number
├── combo_segmentation_key
├── ead_strategy (MURA|MJA|MUSY|IMBT)
├── ead_input_outstanding, ead_input_arrears
├── ead_input_effective_date, ead_input_expired_date
├── ead_calculated (decimal)
├── bucket_no (current assessment)
├── akad_code, office_code, usage_code

stage_lgd_input_snapshot (BARU — historical data yg dipakai untuk LGD per write-off)
├── assessment_period_id, loan_number, write_off_date
├── outstanding_at_wo, recovery_realized, collateral_liquidity_value, sale_cost
├── net_recovery_value, shortfall

stage_pd_input_snapshot (BARU — historical transitions yang dipakai PD)
├── assessment_period_id, group_key, loan_number, periode_snapshot
├── bucket_no_from, bucket_no_to, days_past_due_from, days_past_due_to
```

### 4.2 Migration Strategy

```
2026_10_05_000001_rename_old_stage_tables.php
  → rename: stage_classifications → stage_classifications_legacy
           stage_individual_ckpn → stage_individual_ckpn_legacy
           stage_collective_ckpn → stage_collective_ckpn_legacy
           stage_segmented_snapshots → stage_segmented_snapshots_legacy
           stage_pd_results → stage_pd_results_legacy
           stage_migration_rates → stage_migration_rates_legacy
           stage_compound_flow → stage_compound_flow_legacy
           stage_historical_buckets → stage_historical_buckets_legacy
           stage_ead_buckets → stage_ead_buckets_legacy
           stage_configs → stage_configs_legacy

2026_10_05_000002_create_calculation_jobs_table.php
2026_10_05_000003_create_new_stage_classifications_table.php
2026_10_05_000004_create_new_stage_segmented_snapshots_table.php
2026_10_05_000005_create_new_stage_pd_results_table.php
2026_10_05_000006_create_stage_lgd_results_table.php
2026_10_05_000007_create_new_stage_individual_ckpn_table.php
2026_10_05_000008_create_new_stage_collective_ckpn_table.php
2026_10_05_000009_create_stage_ead_per_loan_table.php
2026_10_05_000010_create_stage_lgd_input_snapshot_table.php
2026_10_05_000011_create_stage_pd_input_snapshot_table.php

2026_10_05_000012_backfill_from_legacy.php
  → copy data valid dari _legacy ke tabel baru (skip kolom yang tak ada di legacy)
  → untuk kolom baru yang required tapi tak ada di legacy (combo_segmentation_key): derive dari office_code+akad_code+usage_code default OFF|AKD|USG

2026_10_05_000013_drop_legacy_tables.php
  → optional: di hold, di-drop setelah 1 periode kalkulasi baru sukses
```

---

## 5. Routing Baru

```
// Modul 1: Konfigurasi
GET    /calculation/{period}/config                          → ConfigurationController@show
POST   /calculation/{period}/config                          → ConfigurationController@save

// Modul 2: Klasifikasi & Segmentasi
GET    /calculation/{period}/classification                 → ClassificationController@index  (view)
POST   /calculation/{period}/classification/run              → ClassificationController@run  (eksekusi, sync/async)
GET    /calculation/{period}/classification/export           → CalculationExportController@classification

// Modul 3: PD Net Flow
GET    /calculation/{period}/pd-netflow                      → PdNetFlowController@index
POST   /calculation/{period}/pd-netflow/run                  → PdNetFlowController@run
GET    /calculation/{period}/pd-netflow/export               → CalculationExportController@pdNetflow

// Modul 4: PD Migration
GET    /calculation/{period}/pd-migration                    → PdMigrationController@index
POST   /calculation/{period}/pd-migration/run                → PdMigrationController@run
GET    /calculation/{period}/pd-migration/export             → CalculationExportController@pdMigration

// Modul 5: LGD Expected Recovery
GET    /calculation/{period}/lgd-er                          → LgdExpectedRecoveryController@index
POST   /calculation/{period}/lgd-er/run                      → LgdExpectedRecoveryController@run
GET    /calculation/{period}/lgd-er/export                   → CalculationExportController@lgdEr

// Modul 6: LGD Collateral Shortfall
GET    /calculation/{period}/lgd-cs                          → LgdCollateralShortfallController@index
POST   /calculation/{period}/lgd-cs/run                      → LgdCollateralShortfallController@run
GET    /calculation/{period}/lgd-cs/export                   → CalculationExportController@lgdCs

// Modul 7: Detail PD & EAD (read-only workpaper)
GET    /calculation/{period}/detail-pd-ead                   → DetailPdEadController@index
GET    /calculation/{period}/detail-pd-ead/export            → CalculationExportController@detailPdEad

// Modul 8: Detail CKPN + Finalize
GET    /calculation/{period}/detail-ckpn                     → DetailCkpnController@index
POST   /calculation/{period}/detail-ckpn/individual          → DetailCkpnController@calculateIndividual
POST   /calculation/{period}/detail-ckpn/collective          → DetailCkpnController@calculateCollective
GET    /calculation/{period}/detail-ckpn/export              → CalculationExportController@detailCkpn

// Progress
GET    /calculation/jobs/{jobId}                             → BaseCalculationController@jobStatus

// Backtesting (combo dump — seluruh input + output untuk validasi)
GET    /calculation/{period}/backtest/export                 → CalculationExportController@backtestBundle (XLSX multi-sheet)
```

**Route dihapus:**
```
calculation.index, calculation.results, calculation.export
calculation.stages, calculation.stage.classify, calculation.stage.individual,
calculation.stage.pd-method, calculation.stage.config, calculation.stage.pd,
calculation.stage.ead-buckets, calculation.stage.split-snapshot,
calculation.stage.historical-buckets, calculation.stage.migration-rate,
calculation.stage.compound-flow, calculation.stage.collective-ckpn,
calculation.table.*
```

---

## 6. Sidebar Navigasi Baru

Hapus "Calculation Hub" & "CKPN Calculation" (current). Ganti:

```html
<div class="...">  <!-- "CKPN Calculation" group -->
  <p class="...">CKPN Calculation</p>
  <ul class="...">
    <li><a href="calculation.config" :active="...">⚙️ Konfigurasi CKPN</a></li>
    <li><a href="calculation.classification">📋 Klasifikasi & Segmentasi</a></li>
    <li><a href="calculation.pd-netflow">📊 PD Net Flow</a></li>
    <li><a href="calculation.pd-migration">🔄 PD Migration</a></li>
    <li><a href="calculation.lgd-er">💰 LGD Expected Recovery</a></li>
    <li><a href="calculation.lgd-cs">🏦 LGD Collateral Shortfall</a></li>
    <li><a href="calculation.detail-pd-ead">🔍 Detail PD & EAD</a></li>
    <li><a href="calculation.detail-ckpn">✅ Detail & Hitung CKPN</a></li>
  </ul>
</div>

<div class="...">  <!-- "Backtesting" group -->
  <p class="...">Backtesting & Export</p>
  <ul class="...">
    <li><a href="calculation.backtest">📦 Bundle Backtesting</a></li>
    <li><a href="reporting.ckpn-summary">📈 Reports</a></li>
  </ul>
</div>
```

---

## 7. Combo Segmentation (Multi-Dimensi)

**Konsep:** User pilih checkbox dimensi → system generate semua kombinasi efektif → applied ke semua modul.

**Contoh input di Modul 1 (Konfigurasi):**
```
[ ] OFFICE  [✓] AKAD  [✓] USAGE   → combo: "AKAD|USAGE"
                               → group_key: "MURA_xxx", "MJA_xxx", dst.
                               → 4 segments × N office

[✓] OFFICE  [✓] AKAD  [ ] USAGE  → combo: "OFFICE|AKAD"
                               → 49 office × 4 akad = 196 segments
```

**Multi-combo simultaneous:** User boleh pilih multiple combos (mis. simpan 3 run berbeda di 3 row config). Tiap run punya `run_id` (uuid), jadi tabel stage_* punya composite unique `(assessment_period_id, run_id, combo_segmentation_key)`.

**Default UI:** Single combo per period. Advanced: lihat semua combo → multi-select.

**Implementasi:** `combo_segmentation_key` disimpan di setiap row `stage_classifications`, `stage_collective_ckpn`, dll. `group_key` = concat of active dims (e.g. `"OFF01_AKD_MURA"`).

---

## 8. Export Format

### Per Modul (CSV — single sheet)

Header rows + input rows + output rows. Contoh modul #3 PD Net Flow:

```csv
[INPUT]
assessment_period_id,periode,combo_segmentation_key,historical_window_months,pd_method,loan_count_collective
17,2026-09,OFFICE|AKAD|USAGE,24,NET_FLOW,4885

[INPUT DETAIL — loans di collective]
loan_number,office_code,akad_code,usage_code,ead_amount,snapshot_loan_age_days
...

[OUTPUT — per group]
group_key,office_code,akad_code,usage_code,periods_analyzed,pd_calculated,method
01_MURA_1,01,MURA,1,24,0.0234,NET_FLOW

[OUTPUT DETAIL — transition matrix per group]
group_key,periode_from,periode_to,from_bucket,to_bucket,loan_count,rate
...
```

### Bundle Backtesting (XLSX multi-sheet)

Menggunakan `maatwebsite/excel` (existing dep) atau PhpSpreadsheet langsung. 8 sheet:

1. **README** — parameter run, timestamp, user
2. **Stage1_Classifications** — tiap loan + class + reason
3. **Stage2_PD_Inputs** — historical loans dipakai
4. **Stage3_PD_Migration** — transition matrix per group
5. **Stage4_LGD_Inputs_ER** — WO + recovery historis per tahun
6. **Stage4_LGD_Inputs_CS** — WO + collateral shortfall per loan
7. **Stage5_Individual_CKPN** — per-loan nilai tercatat, recovery, CKPN
8. **Stage6_Collective_CKPN** — per-group EAD, PD, LGD, CKPN

Sheet 1 berisi hash SHA256 dari sheet lain (untuk validasi integritas backtesting).

---

## 9. View Template per Modul (Struktur Konsisten)

```
┌──────────────────────────────────────────────────┐
│ Header: judul modul + breadcrumb                 │
├──────────────────────────────────────────────────┤
│ Period selector (dropdown assessment period)     │
├──────────────────────────────────────────────────┤
│ Status bar: sudah dihitung? Y/N + timestamp     │
├──────────────────────────────────────────────────┤
│ Action panel:                                    │
│   [Tombol Eksekusi]  [Tombol Export]            │
├──────────────────────────────────────────────────┤
│ Progress bar (jika running) + pesan              │
├──────────────────────────────────────────────────┤
│ Hasil tabel (read-only)                          │
│   - Pagination                                   │
│   - Filter per group_key / office                │
│   - Search                                       │
├──────────────────────────────────────────────────┤
│ Detail row (klik row → modal detail)            │
└──────────────────────────────────────────────────┘
```

Komponen Blade bersama di `resources/views/components/calculation/`:
- `period-selector.blade.php`
- `action-panel.blade.php`
- `result-table.blade.php`
- `progress-bar.blade.php`
- `detail-modal.blade.php`

---

## 10. Fase Implementasi

### Fase 1: Foundation (hari 1-2)
1. Migration schema baru + backfill (jadwal sesuai strategi §4.2)
2. `BaseCalculationController` + `CalculationJobs` infrastructure
3. Layout sidebar baru
4. Hapus route `calculation.*` lama (CalculationController + CalculationStageController)
5. Hapus views lama (`calculation.results.blade.php`, `calculation.stages.blade.php`)

### Fase 2: Modul 1 + 2 (hari 3)
1. `ConfigurationController` + view + form
2. `ClassificationController` + view + eksekusi (port logic dari stage 1)
3. Export CSV per modul

### Fase 3: Modul 3 + 4 (hari 4-5)
1. `PdNetFlowController` + eksekusi (port stage 4 NET_FLOW + stage 5/6/7/8/9)
2. `PdMigrationController` + eksekusi (alternatif)
3. Combo segmentation support
4. Export per modul

### Fase 4: Modul 5 + 6 (hari 6-7)
1. `LgdExpectedRecoveryController` + eksekusi (BARU, belum ada)
2. `LgdCollateralShortfallController` + eksekusi (BARU, belum ada)
3. Lookup data write-off & collateral history
4. Export per modul

### Fase 5: Modul 7 + 8 (hari 8)
1. `DetailPdEadController` (read-only)
2. `DetailCkpnController` (port stage 2 + stage 10 + integrasi LGD baru)
3. Aggregate final + tampilkan summary

### Fase 6: Backtesting & Validasi (hari 9-10)
1. `CalculationExportController::backtestBundle` (XLSX multi-sheet)
2. `reporting.ckpn-summary` rewrite pakai stage_collective_ckpn baru
3. `reporting.risk-matrix` & `reporting.aging-analysis` update path
5. Dokumentasi readme tiap modul

### Fase 7: Cleanup & Test (hari 11)
1. Drop tabel `_legacy`
2. `CalculationResults::forPeriod()` rewrite pakai key baru
3. Test suite update + integration test
4. Verify navigation, route list, cache clear

---

## 11. Mitigasi Risiko

| Risiko | Mitigasi |
|---|---|
| Backfill data lama gagal | Tahap 1: rename ke `_legacy` dulu, _jangan drop_. Kalau backfill gagal, rollback ke legacy dengan switch view |
| Performa hitung turun (8x eksekusi) | `calculation_jobs` + queue worker async; progress polling; lock per period |
| Combo segmentation state bocor antar modul | `combo_segmentation_key` selalu ditulis ke row + index; filter by combo di tiap query |
| Export file terlalu besar | Backtest bundle pakai streaming XLSX writer (row-by-row); limit per sheet 100k rows + compress |
| User bingung urutan modul | Progress bar di sidebar; tiap modul punya tombol "Lanjut ke modul berikutnya" |

---

## 12. Sign-off

**Disusun oleh:** AI Assistant (spec writer)
**Status:** APPROVED — eksekusi bertahap per fase
**Review:** [Technical Lead] ___________________

**Keputusan klarifikasi (2026-10-05):**
1. **Combo segmentation:** Multi-dimensi dinamis, dipilih user per eksekusi (OFF|AKD|USAGE, OFF|AKD, OFF|USAGE, AKAD|USAGE, atau salah satu dimensi saja). Tidak ada batasan jumlah dimensi dalam satu run; hasil dikunci via `combo_segmentation_key` di setiap row.
2. **Format export:** XLSX only. Setiap modul punya 1 sheet (input + parameter + output). Backtest bundle = 1 file XLSX multi-sheet berisi snapshot semua modul + SHA256 hash untuk validasi kesesuaian data sistem vs export.
3. **Eksekusi:** Always sync, tanpa queue. Asumsi total proses per modul < 60 detik pada dataset normal. Tidak ada progress polling — synchronous redirect dengan flash message.
4. **LGD ER + CS:** Selalu keduanya, hasil digabung weighted (`LGD = (1-rER)×wER + LGDcs×wCS`). Tidak ada opsi skip.

Setelah approval, eksekusi Fase 1 → 7 berurutan.