# Rencana Optimasi Pipeline CKPN

**Tanggal:** 25 November 2026
**Cakupan:** penetapan periode assessment, klasifikasi & segmentasi, PD Net Flow / PD Migration, LGD (ER & CS), CKPN Individual & Kolektif, export Excel.
**Prinsip:** **tanpa mengubah formula/angka hasil** — hanya jalur baca/tulis, query, index, dan alokasi memori. Setiap fase diverifikasi dengan uji yang sama sebelum & sesudah perubahan.

---

## 0. Baseline (diukur sebelum perubahan)

| Item | Nilai |
|---|---|
| `snapshot` | 526.407 baris / 74 periode (`periode_snapshot`) |
| `collateral` | 25.732 baris |
| `write_offs` / `recoveries` | 1.789 / 479 baris |
| `bucket_definitions` aktif | 14 (bucket 1..14) |
| `assessment_periods` | 1 |
| Tabel `stage_*` | kosong (periode uji belum dihitung) |
| Feature test kalkulasi (`ModularCalculationTest`, `PdNetflowTableRulesTest`, `PdMigrationWindowTest`, `CalculationConfigInvalidationTest`, `LgdWeightedPageTest`, `AgentExecutionTest`) | **40 passed, 1 skipped** |

Cara ukur ulang:

```bash
php artisan test --filter="ModularCalculationTest|PdNetflowTableRulesTest|PdMigrationWindowTest|CalculationConfigInvalidationTest|LgdWeightedPageTest|AgentExecutionTest"
```

---

## 1. Temuan audit (prioritas menurun)

| # | Lokasi | Masalah | Dampak |
|---|---|---|---|
| F1 | `PdNetflowCalculator::updateMissingBuckets()` | `assignBucketsForPeriodFast()` (UPDATE seluruh baris satu periode) dipanggil **per grup × per periode historis** | G grup × H bulan × N baris update. Dengan G≈40, H≈37, N≈7.1k → ±10 juta row-write, padahal cukup 263k (**±40×**) |
| F2 | `BucketAssignmentService::getBucketLabel()` | 1 query per pemanggilan; dipanggil **per baris** di `CkpnDetailController`, `CalculationExportController`, `FullCkpnExportController`, `DetailPdEadController` | ±7k query per halaman/export |
| F3 | `LgdExpectedRecoveryController::run()` | Per debitur write-off: SELECT `write_offs`, INSERT/UPDATE, DELETE `recoveries`, N INSERT recovery, UPDATE rate | ±5 query × 1.789 WO ≈ 9k query; plus `Collection::first(fn)` berulang per tahun |
| F4 | `LgdCollateralShortfallController::run()` | `prevBalances` memuat **semua** loan bulan sebelumnya (bukan hanya populasi Kol 5); `write_off_flag`+`tglwo` difilter tanpa index | Full scan 526k × 3–4 kali |
| F5 | `PdMigrationController::run()` + `computeMigrationPd()` | Semua snapshot (loan × periode) dihidrasi jadi model Eloquent lalu di-loop per matriks × loan | ±90k model; O(matrik × loan) di memori |
| F6 | `CalculationExportController::writeDetailCkpn()`, `FullCkpnExportController` | `->get()` seluruh debitur kolektif + `sortByDesc` di PHP; label bucket per baris (F2) | Memori & waktu naik linear terhadap jumlah debitur |
| F7 | `AssessmentPeriodController::store()` | `period_code` diberi suffix `md5(microtime())` lalu dipakai sebagai kunci `updateOrCreate` → tidak pernah update, dan periode bulan yang sama bisa dibuat berulang | Periode ganda → perhitungan ganda, `resolvePeriod()` ambigu |
| F8 | `snapshot` | Tidak ada index komposit `(write_off_flag, tglwo)` untuk window LGD (filter grup historis sudah tercakup index `periode_snapshot`) | Scan table 526k per query LGD |
| F9 | `ClassificationController::run()` | Menyimpan seluruh baris snapshot (model Eloquent) di array `$rows` sebelum tulis | Memori puncak tinggi (per periode ±7k model; naik bila periode besar) |
| F11 | `ProductAkadMappingService::resolveAkadCode()` | `Cache::remember` (store `database`) tetap 1 SELECT `cache` **setiap** panggilan; dipanggil per baris di klasifikasi, LGD, export | ±1.150 SELECT cache per run LGD (terukur) |
| F10 | `DetailCkpnController::eadGroups()`, `CkpnDetailController` | Join `snapshot` per pemanggilan + `segmentDimensions(request())` di dalam method non-HTTP | Query berulang saat `index` dan `finalize` |

---

## 2. Fase pelaksanaan

### Fase 0 — Instrumentasi & jaring pengaman
- [ ] Tambah command `php artisan ckpn:benchmark <periode>` (query count + durasi per modul): **belum dikerjakan** — pengukuran sebelum/sesudah dilakukan langsung via `DB::listen` + skrip sementara pada data nyata (lihat angka per fase), dan perintah baru ini tidak bisa diuji bermakna karena tabel `stage_*` di DB dev kosong.
- [ ] Aktifkan log slow query (>1s) di `.env` lokal.
- [x] Catat baseline uji & ukuran data (bagian 0).

**Verifikasi:** command menghasilkan tabel `modul | query | detik | baris`.

---

### Fase 1 — Quick win (risiko rendah, tanpa ubah formula)
- [x] **F2** Hapus N+1 label bucket: tambah `BucketAssignmentService::bucketLabels()` (1 query) dan pakai lookup array di `CkpnDetailController`, `CalculationExportController`, `FullCkpnExportController`, `DetailPdEadController`, `ClassificationController`.
- [x] **F1** Dedupe assign bucket: `PdNetflowCalculator` mengingat periode yang sudah di-assign dalam satu run (`$bucketsAssigned`), sehingga UPDATE per periode hanya sekali walau grup banyak.
- [x] **F3** Batch tulis LGD ER: preload `write_offs` per window (1 query), insert/upsert massal, `recoveries` dihapus + disisipkan per batch.
- [x] **F11** Memo per request di `ProductAkadMappingService` (`$akadMemo` / `$eadRuleMemo`, direset oleh `invalidateCache()`), sehingga pemetaan product→akad tidak SELECT cache per baris.
- [x] **F8** Migration index `snapshot(write_off_flag, tglwo)` untuk window write-off LGD. Index per-dimensi grup (office/akad/usage) **tidak** ditambahkan: query historis PD sudah terfilter `periode_snapshot` (index existing) sehingga hanya membaca ±7k baris/periode; tambahan index hanya membebani import.
- [ ] **F8-lanjut** Bila data tumbuh: evaluasi `snapshot(periode_snapshot, active_flag)` lewat `EXPLAIN` sebelum menambah.

**Hasil terukur Fase 1:** label bucket 100 baris = 100 query → **1 query**; query LGD ER = 2.337 → **1.182** (1,87 s → 0,99 s) dan SELECT cache per baris hilang; index `snapshot_wo_tglwo_idx` dipakai (`EXPLAIN`). Sisa 1.156 query adalah UPDATE `write_offs` per debitur — dapat dijadikan satu `CASE WHEN` per 200 id bila data write-off tumbuh besar (belum dilakukan: manfaat kecil pada 1,8k baris).
**Verifikasi:** `php -l`, feature test kalkulasi (harus tetap 40 passed / 1 skipped), `EXPLAIN` memakai index baru, hitung query via `DB::listen`.

---

### Fase 2 — PD Net Flow / PD Migration
- [x] **F5** PD Migration: agregasi matriks di SQL (`aggregateMatrixSql()`: satu query `GROUP BY` rating_sumber × state_tujuan per matriks, join 1:1 via `snapshot_loan_period_unique`). Loop `foreach ($loanNumbers)` per matriks dihapus dari jalur produksi; hidrasi snapshot t_start/t_end/assessment tidak lagi dilakukan. Eksposur bobot PD grup juga diagregasi SQL (`aggregateAssessmentExposureSql()`). Jalur in-memory lama dipertahankan sebagai referensi unit test murni.
- [x] **F5** `PeriodResolver::getMigrationPeriods()` sudah dipanggil **sekali** per run (diverifikasi: satu pemanggilan di `PdMigrationController::run()`), dan mapping loan→grup sudah berupa array satu pass (`$loansByGroup` per `group_key`, lookup O(1) di loop grup).
- [x] PD Netflow: `resolvePeriodsInRange()` di-memo per rentang (`$periodRangeMemo`) — query `distinct()` 526k baris terukur **179 ms** dan sebelumnya jalan 2× per grup.
- [x] PD Netflow: `calculateCompoundFlows()` memakai indeks rate `[periode][from][to]` + cache tanggal periode sebelumnya (`Carbon::parse` tidak lagi per step).
- [x] Batasi window query historis: `loadHistoricalSnapshots()` sudah memakai `whereIn('periode_snapshot', ...)` dari daftar periode yang dinormalisasi; `distinct()->pluck()` 74 periode tidak lagi berulang per grup berkat memo.

**Hasil terukur Fase 2 (PD Migration, dataset nyata 7.222 loan, matriks 202509→202609):** hidrasi snapshot 2,105 s + agregasi in-memory 0,561 s = **2,666 s** → agregasi SQL **0,358 s** (tanpa hidrasi). Selisih angka SQL vs in-memory: `max_abs_diff` = 1,5e-5 pada nilai ±1,3e11 (galat akumulasi float, relatif ±1e-16; PD dibulatkan 6 desimal → nilai tersimpan identik). `mismatch_count` = 0 pada toleransi 1e-6.

**Hasil terukur Fase 2 (PD Netflow):** `distinct()->pluck('periode_snapshot')` = **179 ms** per panggilan; dengan memo, satu run 40 grup memanggilnya **1×** (bukan 80×) → ±**7 s** per run. Loop Tabel 3 (37 periode × 14 bucket): **0,85 ms → 0,15 ms** per grup (5,7×; 130 kombinasi). Verifikasi ekuivalensi grup `01|06|3` periode 67: **468 baris compound, 0 mismatch**, 198 nilai non-nol.

**Verifikasi:** `PdNetflowTableRulesTest`, `PdMigrationWindowTest`, `CalculationConfigInvalidationTest` tetap lulus; perbandingan `aggregateMatrixSql()` vs `aggregateMatrixFromSnapshots()` pada dataset nyata (lihat hasil terukur di atas).

---

### Fase 3 — LGD (ER & CS)
- [x] **F4** CS: `prevBalances` dibatasi `whereIn('loan_number', $csRows->where('quality_rating', 5)->pluck('loan_number'))`, bukan seluruh snapshot bulan lalu. `quality_rating` ditambahkan ke SELECT `csRows` sebagai integer (`CAST(... AS UNSIGNED)`).

**Hasil terukur Fase 3 (dataset nyata, assessment 202610):** baris `prevBalances` 6.869 → **264** (26× lebih sedikit); `value_mismatch` = 0, `exposure_diff` = 0 → eksposur Kol 5 tidak berubah.
- [x] **F4** `SUBSTRING(tglwo,1,6)` diganti range kolom `tglwo` (`>= startYm.01`, `<= assessYm.31`) di LGD ER, LGD CS, LGD detail, dan export FullCkpn → index `snapshot_wo_tglwo_idx` menjadi **range scan**, bukan filter setelah scan semua baris WO (57 ms → 42 ms pada window 5 tahun).
- [x] ER: pencarian snapshot per tahun (`Collection::first(fn)`) diganti indeks `[periode => baris]` sekali per debitur (O(1) per tahun), termasuk snapshot awal WO.
- [x] Hasil ER & CS tidak berubah — diverifikasi dengan replikasi algoritma lama vs baru pada data nyata: 1.699 loan window, `diff=0` himpunan loan, **0 mismatch** snapshot awal WO dan saldo akhir tahun. (`stage_lgd_results` tidak bisa dibandingkan karena tabel stage di DB dev kosong.)

**Verifikasi:** `LgdWeightedPageTest` + dump `stage_lgd_results` ER/CS sebelum-sesudah harus sama.

---

### Fase 4 — CKPN Individual & Kolektif
- [x] **F10** `eadGroups()` di-memo per request (`$eadGroupsMemo`, kunci `periode|dimensi`) dan dipakai guard `finalize` + `computeCollective`.
- [x] **F10** `segmentDimensions(?Request $request, ...)` menerima `$request` nullable sehingga bisa dipakai di luar siklus HTTP.
- [x] Individual: `computeIndividual()` memakai `select('id','loan_number','ead_amount')` + `chunkById(500)` (tulis per chunk) alih-alih `->get()` seluruh loan individual.
- [x] Kolektif: map PD/LGD `[group|bucket]` sudah precompute; total EAD per grup sudah diagregasi di SQL (`eadGroups()`), tidak ada join snapshot berulang.
- [x] Guard `missingPd`: query hanya `select('group_key','bucket_no')->distinct()` — kolom `detail` (JSON besar) tidak lagi dihidrasi.

**Verifikasi:** `ModularCalculationTest` & suite kalkulasi tetap lulus. Ekuivalensi `computeIndividual()` diuji pada 1.200 loan sintetis (sebagian dengan 2 agunan, dalam transaksi yang di-rollback): **0 baris berbeda**, total CKPN individual 114.574.278 identik.

---

### Fase 5 — Export Excel
- [x] **F6** Detail CKPN: pengurutan CKPN debitur di SQL (`ORDER BY ROUND(ead*pd*lgd,2) DESC`) lewat join `stage_collective_ckpn`, ditulis per 1.000 baris (`lazy()`); total diakumulasi satu pass.
- [x] **F2** Tidak ada query di dalam loop penulisan baris (label bucket & PD/LGD sudah dari array/join).
- [x] PD Migration: `writePdMigration()` kini `unique('group_key')` — sebelumnya 5 baris per grup (satu per rating) membuat ringkasan dan blok matriks tercetak 5×; JSON detail di-decode sekali per grup. `FullCkpnExportController::writePdMigrationSheet()` menyimpan hasil decode di `$r->decoded`. LGD export sudah decode sekali per baris.
- [ ] Uji regresi ringan (jumlah baris sheet = baris stage): **ditunda** — export memakai `CONCAT_WS` dan `CAST(... AS UNSIGNED)` yang hanya ada di MySQL, sedangkan suite berjalan di SQLite. Diverifikasi manual lewat skrip sintetis (transaksi rollback): 6 debitur × 4 kombinasi (dimensi 2/3 × NET_FLOW/MIGRATION) → 0 baris berbeda, urutan & total sama.

**Verifikasi:** export `detail-ckpn` & `pd-migration` untuk periode uji; bandingkan jumlah baris + total dengan halaman web.

---

### Fase 6 — Penetapan periode assessment
- [x] **F7** `period_code` deterministik `P{Y}{MM}` (`AssessmentPeriodController::store()`, dan regenerasi di `update()` bila tahun/bulan berubah). Duplikat `(fiscal_year, fiscal_month)` dicegah lewat `Rule::unique(...)->where(fiscal_year)` di `Store/UpdateAssessmentPeriodRequest` + pesan validasi Indonesia + guard eksplisit di controller.
- [ ] **F7-lanjut** Unique index DB `(fiscal_year, fiscal_month)` **belum** ditambahkan: perlu pembersihan data duplikat produksi lebih dulu agar migrasi tidak gagal saat deploy.
- [x] `resolvePeriod()`: tie-breaker `orderByDesc('assessment_date')->orderByDesc('id')` dipertahankan; `AssessmentPeriodController::index()` menampilkan banner peringatan bila ada bulan fiskal dengan lebih dari satu periode.
- [x] `financingCount`/`loanCount` di `AssessmentPeriodController::show()` **sudah** memakai satu query `Cache::remember('snapshot:distinct_loan_count', 300)` — tidak ada query ganda.

**Verifikasi:** validasi diuji langsung terhadap DB (identifier period 67, 2026-10): input tahun/bulan sama → **gagal** (`fiscal_month.unique`); tahun/bulan baru → **lulus**.

---

## 3. Aturan verifikasi (berlaku semua fase)

1. `php -l` pada setiap berkas yang diubah.
2. Feature test kalkulasi harus **tidak turun** dari baseline (40 passed / 1 skipped).
3. Perubahan yang menyentuh hasil hitung wajib disertai perbandingan dump tabel `stage_*` sebelum vs sesudah (harus identik).
4. Klaim percepatan hanya boleh ditulis bila ada angka query/durasi sebelum-sesudah.
5. Setiap fase selesai → catat di `CHANGELOG.md` (protokol AGENTS.md).

## 4. Risiko & rollback

| Risiko | Mitigasi |
|---|---|
| Index baru memperlambat INSERT snapshot | Index hanya pada kolom yang dipakai filter; ukur waktu import sebelum/sesudah |
| Batch write LGD mengubah id `write_offs` | Id tidak dipakai lintas proses; `recoveries` dihubungkan ulang setelah insert |
| Dedupe bucket melewati re-assign yang diperlukan | Dedupe hanya dalam satu run (properti instance), bukan cache lintas request |
| Perubahan urutan query mengubah hasil agregasi | Semua agregasi tetap di SQL dengan `GROUP BY` yang sama; uji dump identik |

## 5. Progres

| Fase | Status |
|---|---|
| 0 — Instrumentasi | sebagian (baseline tercatat) |
| 1 — Quick win | **selesai (F1, F2, F3, F8, F11)** |
| 2 — PD | **selesai (F5 matriks SQL, memo periode, compound flow ter-index)** |
| 3 — LGD | **selesai (F3 batch, F4 prevBalances + range index tglwo, ER indeks per debitur)** |
| 4 — CKPN | **selesai (F10 memo eadGroups, chunkById individual, guard agregat)** |
| 5 — Export | **selesai (kecuali uji regresi export — SQLite tidak mendukung CONCAT_WS/CAST UNSIGNED)** |
| 6 — Periode assessment | **sebagian (F7 + banner duplikat selesai; unique index DB menunggu pembersihan data)** |
