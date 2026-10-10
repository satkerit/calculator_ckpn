# Klasifikasi & Segmentasi — Kriteria EAD

**Dokumen:** Definisi kriteria pembiayaan untuk perhitungan EAD di modul Klasifikasi  
**Versi:** 1.0  
**Tanggal:** 2026-10-05  

---

## Overview

Modul **Klasifikasi & Segmentasi** (Module 2) menggunakan kriteria pembiayaan yang **BERBEDA** dari snapshot PD Net Flow. 

**Tujuan:** Menentukan daftar debitur yang masuk kategori INDIVIDUAL vs COLLECTIVE untuk perhitungan CKPN.

---

## Kriteria Pembiayaan untuk EAD Klasifikasi

### Syarat Pembiayaan:

1. **Active Flag = TRUE**
   - Hanya pembiayaan yang aktif (belum jatuh tempo atau masih dalam periode aktif)
   - Exclude: pembiayaan yang sudah ditutup/tidak aktif

2. **Write-off Flag = FALSE (atau NULL)**
   - Exclude: pembiayaan yang sudah write-off
   - Alasan: Write-off sudah keluar dari perhitungan normal, masuk ke kategori khusus

3. **Kriteria Akad Pembiayaan** (per kode akad):
   - Setiap akad memiliki aturan EAD masing-masing
   - **Tidak ada exception**: hanya menggunakan outstanding sesuai aturan akad

---

## Metode Perhitungan EAD per Akad

### 1. MURABAHAH (MURA)
```
EAD = outstanding_balance
Alasan: Pokok pembiayaan yang masih berjalan
```

### 2. MULTIJASA (MJA)
```
EAD = outstanding_balance
Alasan: Pokok pembiayaan yang masih berjalan
```

### 3. MUSYARAKAH (MUSY)
```
IF expired_date <= assessment_date THEN
  EAD = principal_arrears (tunggak pokok)
ELSE
  EAD = 0 (belum jatuh tempo, tidak ada exposure)
```

### 4. IJARAH MUNTAHIYA BITTAMLIK (IMBT)
```
IF expired_date <= assessment_date THEN
  EAD = principal_arrears (tunggak pokok)
ELSE
  EAD = 0 (belum jatuh tempo, tidak ada exposure)
```

---

## Perbedaan Klasifikasi EAD vs PD Snapshot EAD

| Aspek | Klasifikasi (Module 2) | PD Net Flow (Module 5) |
|-------|----------------------|----------------------|
| **Tujuan** | Tentukan INDIVIDUAL/COLLECTIVE debitur | Hitung PD berdasarkan historical flow |
| **Data Source** | Active snapshot current period | Historical snapshot 12-36 bulan |
| **Filter** | active_flag=true, write_off_flag=false | Semua periode historis (tracking) |
| **Periode** | Current assessment period saja | Multiple periods (transition tracking) |
| **EAD Usage** | Ranking outstanding (TOP N) | Bucket distribution & transition rate |
| **Debitur** | Daftar debitur untuk CKPN | Loan-level movement history |

---

## Implementasi (ClassificationController)

```php
// Filter pembiayaan aktif sesuai kriteria
$snapshots = Snapshot::where('periode_snapshot', $periodeYm)
    ->where('active_flag', true)  // Hanya aktif
    ->where(function ($q) {
        $q->whereNull('write_off_flag')->orWhere('write_off_flag', false);
    })
    ->orderBy('loan_number')
    ->get();

// Hitung EAD per akad (strategy pattern)
foreach ($snapshots as $s) {
    $akadCode = ProductAkadMappingService::resolveAkadCode($s->product_code);
    $ead = EadStrategyFactory::createByAkad($akadCode)
        ->calculate($s->toArray(), $period->assessment_date);
    // ead = outstanding_balance atau principal_arrears sesuai akad
}
```

---

## Acceptance Criteria

- [ ] Hanya pembiayaan aktif (active_flag=true) masuk perhitungan
- [ ] Write-off excluded
- [ ] EAD dihitung sesuai aturan akad masing-masing
- [ ] Tidak ada exception beyond akad rules (hanya outstanding atau tunggak, tidak ada campuran)
- [ ] TOP N outstanding diambil untuk INDIVIDUAL classification
- [ ] Sisa (non-NPL atau luar TOP N) masuk COLLECTIVE
- [ ] Total EAD = sum(outstanding per akad) per classification

---

## Sign-off

**Prepared by:** AI Assistant / Kiro  
**Date:** 2026-10-05

