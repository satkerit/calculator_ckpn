# Perencanaan Perbaikan CKPN — GAP Analysis vs Brainstorming (Revisi 2)

**Tanggal:** 10 Oktober 2026
**Referensi:** `docs/brainstorming.md` (termasuk Bagian 3: Klarifikasi & Keputusan Owner)
**Revisi:** 2 — memuat resolusi GAP atas keputusan owner (K1–K5).

---

## 1. Status GAP Setelah Resolusi Owner

| #  | Area                                                        | Referensi (Bagian 3)                                                                                          | Kondisi Eksisting                                                                                                                                                                                                             | Status                                                                                           |
| -- | ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| G1 | EAD Murabahah/Multijasa                                     | K1: EAD = outstanding pembiayaan (data sumber sudah netto, tanpa marjin)                                      | `EAD = outstanding_balance` — `MurabahahStrategy.php:12`, `MultijasaStrategy.php:12`                                                                                                                                   | **CLOSED — sesuai** (rekomendasi Piutang Netto dari Bagian 2 §1 digantikan keputusan K1) |
| G2 | Populasi snapshot (semua rekening aktif, lancar = Bucket 1) | §3 Bagian 1 + K5                                                                                             | Semua`active_flag=1` masuk snapshot, DPD≤0 → Bucket 1 (`BucketAssignmentService.php:52`)                                                                                                                                | **CLOSED — sesuai**                                                                       |
| G3 | EAD & snapshot Musyarakah/IMBT                              | K2: snapshot = outstanding pokok utk semua akad; CKPN hanya bila menunggak/jatuh tempo; EAD = tunggakan pokok | `DueDateBasedStrategy.php:30-32`: EAD = `principal_arrears` bila `expired_date <= assessment_date`, else 0; snapshot memakai `outstanding_balance` (`loadHistoricalSnapshots`, `PdNetflowCalculator.php:192-214`) | **CLOSED — sesuai** (poin verifikasi ringan di R3)                                        |
| G4 | Klasifikasi Individual                                      | K3: tanpa ambang nominal; NPL 3,4,5 + TOP N                                                                   | Kriteria NPL (write_off_flag / rating 3,4,5) → TOP N outstanding (`ClassificationController.php:79-110`); `individual_top_n` default 30, configurable                                                                    | **CLOSED — sesuai** (tidak perlu ambang nominal, tidak perlu `ppka_value`)              |
| G5 | CKPN Individual                                             | §2A Bagian 2                                                                                                 | `ckpn = ead − (liquidity − liquidity×sale_cost_pct)`, floor 0 (`CkpnCalculator.php:16-26`)                                                                                                                             | **CLOSED — sesuai**                                                                       |
| G6 | CKPN Kolektif = PD×LGD×EAD                                | §2B Bagian 2                                                                                                 | `CkpnCalculator.php:36-44`                                                                                                                                                                                                  | **CLOSED — sesuai**                                                                       |
| G7 | Window historis PD                                          | K4: dinamis,**default wajib 36 bulan**                                                                  | Kode default 36 (`PdNetflowCalculator.php:68`) tapi default kolom migration = 24 (`2026_10_04_160000...php:54`)                                                                                                           | **OPEN — rencana R1**                                                                     |
| G8 | LGD ER+CS weighted, window 60 bln                           | §2B Bagian 2                                                                                                 | Sesuai; grup tanpa WO → LGD=1.0 (konservatif)                                                                                                                                                                                | **CLOSED — sesuai** (LGD=1.0 dipertahankan sebagai pendekatan konservatif)                |
| G9 | Skema 14 bucket, rantai 1→…→14, PD dari Bucket 1         | §3 Bagian 1–2                                                                                               | Sudah sesuai setelah renumbering                                                                                                                                                                                              | **CLOSED — sesuai**                                                                       |

**Kesimpulan Revisi 2:** Dari 9 GAP, **8 CLOSED** (sudah sesuai sistem atau terselesaikan oleh keputusan owner K1–K5). **1 OPEN: G7 (default window 36 bulan)** — satu-satunya perbaikan kode yang tersisa.

---

## 2. Rencana Perbaikan Tersisa

### R1 — Default Window PD = 36 Bulan, Dinamis (G7 / K4) — HIGH, diff kecil

**Masalah:** Default kolom DB `stage_configs.historical_months` = 24, sementara ketentuan: dinamis tapi default wajib 36.

**Langkah:**

1. Migration baru: ubah default kolom `historical_months` pada tabel `stage_configs` dari 24 → 36.
2. Data fix idempotent:
   ```sql
   UPDATE stage_configs SET historical_months = 36 WHERE historical_months < 36;
   ```

   (Config per periode yang sengaja > 36 tidak disentuh — dinamis tetap terjaga.)
3. Verifikasi: `stage_configs` periode aktif menunjukkan 36 bulan setelah fix.

**Keluaran:** Window PD Net Flow default 36 bulan, tetap configurable ke atas.

> ponytail: hanya default DB yang diubah, tidak menambah validasi/warning baru di controller — default sudah menjamin K4; validasi tambahan ditunda sampai ada kebutuhan riil.

### R2 — Bersihkan Dead Code Legacy (opsional, LOW)

`PdNetFlowController.php` masih memuat 5 method legacy tak terpakai (`buildSegmentedSnapshots`, `aggregateHistoricalBuckets`, `computeMigrationRates`, `computeCompoundFlow`, `finalizePd`) dengan skema bucket lama. Tidak memengaruhi perhitungan, tapi memicu salah baca di audit berikutnya. Hapus bila disetujui.

### R3 — Verifikasi Ringan Tanpa Perubahan Kode (LOW)

1. Snapshot Tabel 1 Musyarakah/IMBT memakai outstanding pokok (sudah benar via `outstanding_balance`) — spot-check 1 grup Musyarakah di Tabel 1 vs data sumber.
2. Musyarakah/IMBT tidak menunggak muncul di Tabel 1 Bucket 1 dengan EAD=0 di modul CKPN — spot-check 1 rekening.

### R4 — Fix Bug Kritikal EAD Strategy Factory (DITEMUKAN saat R3) — CRITICAL, SELESAI

**Temuan:** `EadStrategyFactory::$strategies` di-key dengan `'MURA','MJA','MUSY','IMBT'`, sementara `ProductAkadMappingService::resolveAkadCode()` mengembalikan **kode numerik master** (`'06','13','03','10'`). Lookup selalu gagal → fallback `MurabahahStrategy` untuk **semua akad**. Akibat: aturan EAD Musyarakah & IMBT (EAD=0 bila belum jatuh tempo) **tidak pernah diterapkan**; 1328 rekening IMBT non-menunggak memiliki EAD penuh (melanggar K2).

**Perbaikan:** Tambah key numerik ke `EadStrategyFactory::$strategies` (`06`=Murabahah, `13`=Multijasa, `03`=Musyarakah, `10`=IMBT) di [EadStrategyFactory.php](file:///d:/laragon/www/calculator_ckpn/app/Services/Ead/EadStrategyFactory.php).

**Verifikasi (periode 40, re-run pipeline penuh):**
- Strategi terpilih benar: `06→Murabahah, 13→Multijasa, 03→Musyarakah, 10→IMBT` ✓
- IMBT non-menunggak & belum jatuh tempo: 1328/1328 EAD=0 ✓ (sebelumnya 1328 anomali)
- Musyarakah non-menunggak & belum jatuh tempo: 8/8 EAD=0 ✓
- Multijasa: EAD=outstanding (K1) ✓
- Total EAD: akad 06=98.25 M, 03=6.36 M, 13=0.0196 M, 10=0.184 M
- Pipeline: PD Net Flow (23 grup, window 48 bln) → LGD ER+CS (23 grup) → Finalize CKPN = **Rp 5.695.225.134,77** (individual 30, kolektif 23)

---

## 3. Urutan Eksekusi

```
1. R1 (migration default 36 + data fix)
2. Re-run PD Net Flow periode aktif (bila window sebelumnya < 36 → hasil PD berubah)
3. Re-run finalize CKPN, rekonsiliasi
4. R3 spot-check, R2 setelah konfirmasi
```

**Catatan:** Setelah R1, jika window efektif berubah dari 24 → 36, hasil PD & CKPN periode aktif **wajib dihitung ulang** — angka lama tidak valid sebagai pembanding.

---

## 4. Checklist Verifikasi

- [x] Default `stage_configs.historical_months` = 36 (migration `2026_10_10_000001_raise_default_historical_months_to_36` ter-apply, verifikasi COLUMN_DEFAULT = 36)
- [x] Semua baris config: `historical_months >= 36` (periode 40 = 48 bulan; 0 baris perlu difix)
- [x] R2: 5 method legacy + 2 unused import dihapus dari `PdNetFlowController.php` (±286 baris), `php -l` bersih, route aman
- [x] R4: Bug `EadStrategyFactory` key mismatch diperbaiki; EAD Musyarakah/IMBT sesuai K2 (1328/1328 + 8/8 EAD=0)
- [x] Re-run pipeline penuh periode 40: PD (23 grup) → LGD ER+CS (23 grup) → CKPN final Rp 5.695.225.134,77
- [ ] Spot-check R3 selesai
