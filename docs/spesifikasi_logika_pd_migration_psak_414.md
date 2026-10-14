# Spesifikasi Teknis Logika PD Migration (PSAK 414 / OJK)
**Dokumen Panduan Implementasi Engine Kalkulasi CKPN Kolektif Dinamis**

---

## 1. Pendahuluan

Sesuai kerangka kerja PSAK 414 (PSAK 109) dan acuan Kertas Kerja CKPN OJK, perhitungan *Probability of Default* (PD) berbasis **PD Migration Matrix** diukur melalui observasi pergerakan/migrasi kolektibilitas rekening selama kurun waktu 12 bulan (*1-year lag window*) yang dievaluasi secara triwulanan (*multi-quarter observation*).

Dokumen ini mendefinisikan aturan bisnis, logika matematis, dan algoritma penentuan matriks dinamis untuk diterapkan pada aplikasi/sistem engine CKPN.

---

## 2. Aturan Bisnis & Konsep Dasar

1. **Jarak Pengamatan Matriks ($T-12$ ke $T$):** Setiap matriks tunggal mengukur migrasi kualitas kredit tepat **12 bulan (1 tahun)**.
2. **Multi-Period Observation:** Untuk menjaga stabilitas data historis dari fluktuasi musiman, digunakan **4 matriks triwulanan berurut (1 tahun penuh data historis triwulanan)**.
3. **Latest Completed Quarter Rule:** Matriks terbaru yang digunakan ($M_4$) selalu merujuk pada posisi **Triwulan Penuh Terakhir (*Latest Completed Quarter*)** sebelum tanggal asesmen.
4. **Posisi Baki Debet (EAD):** PD *Weighted Average* hasil kalkulasi dikalikan langsung dengan Baki Debet (*Outstanding*) posisi **Tanggal Asesmen ($T_{\text{assesment}}$)**.

---

## 3. Matriks Referensi Periode Dinamis

### A. Algoritma Penentuan $Q_{\text{latest}}$

Berdasarkan bulan tanggal asesmen ($T_{\text{assesment}}$):

| Bulan Asesmen | $Q_{\text{latest}}$ (Triwulan Acuan Utama) | Tanggal Akhir $Q_{\text{latest}}$ ($T_{\text{end\_4}}$) |
| :--- | :--- | :--- |
| **Januari, Februari, Maret** | Triwulan IV (Tahun $Y-1$) | 31 Desember ($Y-1$) |
| **April, Mei, Juni** | Triwulan I (Tahun $Y$) | 31 Maret ($Y$) |
| **Juli, Agustus, September** | Triwulan II (Tahun $Y$) | 30 Juni ($Y$) |
| **Oktober, November, Desember** | Triwulan III (Tahun $Y$) | 30 September ($Y$) |

---

### B. Rumus Penentuan 4 Rentang Matriks Triwulanan

Setelah $T_{\text{end\_4}}$ ditentukan:

* **Matriks 4 ($M_4$):** $[T_{\text{end\_4}} - 12 \text{ bulan}] \longrightarrow [T_{\text{end\_4}}]$
* **Matriks 3 ($M_3$):** $[T_{\text{end\_3}} - 12 \text{ bulan}] \longrightarrow [T_{\text{end\_3}}]$ di mana $T_{\text{end\_3}} = T_{\text{end\_4}} - 3 \text{ bulan}$
* **Matriks 2 ($M_2$):** $[T_{\text{end\_2}} - 12 \text{ bulan}] \longrightarrow [T_{\text{end\_2}}]$ di mana $T_{\text{end\_2}} = T_{\text{end\_3}} - 3 \text{ bulan}$
* **Matriks 1 ($M_1$):** $[T_{\text{end\_1}} - 12 \text{ bulan}] \longrightarrow [T_{\text{end\_1}}]$ di mana $T_{\text{end\_1}} = T_{\text{end\_2}} - 3 \text{ bulan}$

---

## 4. Matriks Pergerakan Triwulan Menurut Periode Asesmen

### Kasus 1: Asesmen Februari 2026 ($T_{\text{assesment}} = \text{Februari } 2026$)
* **$Q_{\text{latest}}$:** Triwulan IV 2025 (31 Desember 2025)

| Matriks | Saldo Awal ($T-12$) | Saldo Akhir ($T$) | Rentang Pengamatan |
| :--- | :--- | :--- | :--- |
| **$M_1$ (Q1 2025)** | 31 Maret 2024 | 31 Maret 2025 | 12 Bulan |
| **$M_2$ (Q2 2025)** | 30 Juni 2024 | 30 Juni 2025 | 12 Bulan |
| **$M_3$ (Q3 2025)** | 30 September 2024 | 30 September 2025 | 12 Bulan |
| **$M_4$ (Q4 2025)** | 31 Desember 2024 | 31 Desember 2025 | 12 Bulan |

---

### Kasus 2: Asesmen Maret 2026 ($T_{\text{assesment}} = 31 \text{ Maret } 2026$)
* **$Q_{\text{latest}}$:** Triwulan I 2026 (31 Maret 2026)

| Matriks | Saldo Awal ($T-12$) | Saldo Akhir ($T$) | Rentang Pengamatan |
| :--- | :--- | :--- | :--- |
| **$M_1$ (Q2 2025)** | 30 Juni 2024 | 30 Juni 2025 | 12 Bulan |
| **$M_2$ (Q3 2025)** | 30 September 2024 | 30 September 2025 | 12 Bulan |
| **$M_3$ (Q4 2025)** | 31 Desember 2024 | 31 Desember 2025 | 12 Bulan |
| **$M_4$ (Q1 2026)** | 31 Maret 2025 | 31 Maret 2026 | 12 Bulan |

---

### Kasus 3: Asesmen April 2026 ($T_{\text{assesment}} = 30 \text{ April } 2026$)
* **$Q_{\text{latest}}$:** Triwulan I 2026 (31 Maret 2026)

| Matriks | Saldo Awal ($T-12$) | Saldo Akhir ($T$) | Rentang Pengamatan |
| :--- | :--- | :--- | :--- |
| **$M_1$ (Q2 2025)** | 30 Juni 2024 | 30 Juni 2025 | 12 Bulan |
| **$M_2$ (Q3 2025)** | 30 September 2024 | 30 September 2025 | 12 Bulan |
| **$M_3$ (Q4 2025)** | 31 Desember 2024 | 31 Desember 2025 | 12 Bulan |
| **$M_4$ (Q1 2026)** | 31 Maret 2025 | 31 Maret 2026 | 12 Bulan |

> *Catatan:* Hasil PD Migration untuk Maret 2026 dan April 2026 **identik**, namun nilai EAD yang dikalikan mengikuti saldo baki debet masing-masing bulan.

---

## 5. Implementasi Kode / Pseudocode (PHP / Laravel)

```php
<?php

namespace App\Services\CKPN;

use Carbon\Carbon;

class PDMigrationPeriodResolver
{
    /**
     * Mengembalikan 4 rentang matriks triwulan dinamis berbasis tanggal asesmen.
     *
     * @param Carbon $assessmentDate
     * @return array
     */
    public static function getMigrationPeriods(Carbon $assessmentDate): array
    {
        $year  = $assessmentDate->year;
        $month = $assessmentDate->month;

        // 1. Tentukan Tahun dan Bulan untuk Q_latest
        if ($month >= 1 && $month <= 3) {
            $latestYear  = $year - 1;
            $latestMonth = 12;
        } elseif ($month >= 4 && $month <= 6) {
            $latestYear  = $year;
            $latestMonth = 3;
        } elseif ($month >= 7 && $month <= 9) {
            $latestYear  = $year;
            $latestMonth = 6;
        } else {
            $latestYear  = $year;
            $latestMonth = 9;
        }

        $periods = [];

        // 2. Iterasi Mundur untuk 4 Matriks Triwulan
        for ($i = 0; $i < 4; $i++) {
            $endDate = Carbon::createFromDate($latestYear, $latestMonth, 1)
                ->subMonths($i * 3)
                ->endOfMonth();

            $startDate = $endDate->copy()->subYear();

            $quarterNumber = ceil($endDate->month / 3);

            $periods[] = [
                'matrix_index' => 4 - $i,
                'quarter'      => "Q{$quarterNumber} {$endDate->year}",
                't_start'      => $startDate->format('Y-m-d'), // Saldo Awal (T-12)
                't_end'        => $endDate->format('Y-m-d'),   // Saldo Akhir (T)
            ];
        }

        // Urutkan secara kronologis dari matriks terlama (M1 ke M4)
        return array_reverse($periods);
    }
}
```

---

## 6. Formulasi Kalkulasi PD Migration Cumulative & Weighted Average

### A. Formula Roll-Forward PD Cumulative ($PD_k$)

Perhitungan PD untuk setiap kolektibilitas ($k \in \{1, 2, 3, 4, 5\}$) pada satu matriks $m$:

$$PD_{5,m} = P_{\text{WO}, 5} + P_{5,5}$$

$$PD_{4,m} = P_{\text{WO}, 4} + (P_{4,5} \times PD_{5,m})$$

$$PD_{3,m} = P_{\text{WO}, 3} + (P_{3,5} \times PD_{5,m}) + (P_{3,4} \times PD_{4,m})$$

$$PD_{2,m} = P_{\text{WO}, 2} + (P_{2,5} \times PD_{5,m}) + (P_{2,4} \times PD_{4,m}) + (P_{2,3} \times PD_{3,m})$$

$$PD_{1,m} = P_{\text{WO}, 1} + \sum_{j=2}^{5} (P_{1,j} \times PD_{j,m})$$

Di mana:
* $P_{i,j}$: Rasio perpindahan baki debet dari Kolektibilitas $i$ ke Kolektibilitas $j$.
* $P_{\text{WO}, i}$: Rasio hapus buku (*Write-Off*) dari Kolektibilitas $i$.

---

### B. Formula Weighted Average PD Final ($\overline{PD}_k$)

Menggabungkan hasil dari 4 matriks triwulan:

$$\overline{PD}_k = \frac{\sum_{m=1}^{4} \left( \text{Saldo Awal}_{k, m} \times PD_{k, m} \right)}{\sum_{m=1}^{4} \text{Saldo Awal}_{k, m}}$$

---

## 7. Penyesuaian PSAK 414 Wajib (Audit Readiness)

1. **Forward-Looking Overlay (FLI):**
   Nilai $\overline{PD}_k$ historis wajib dikalikan dengan faktor *overlay* proyeksi makroekonomi (misal PDB, Inflasi, suku bunga):
   $$PD_{\text{FLI}} = \overline{PD}_k \times \text{FLI\_Multiplier}$$
2. **Penggunaan Lifetime PD (Stage 2):**
   Untuk aset pada Stage 2 dengan sisa tenor $> 1$ tahun, $PD_{\text{FLI}}$ harus dikonversi menjadi *Lifetime PD* sesuai dengan sisa umur kredit.