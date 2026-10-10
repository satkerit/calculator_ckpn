# Dokumen Perancangan Sistem Calculator CKPN BPRS
## PSAK 414 – Penurunan Nilai Aset Keuangan Syariah

**Dokumen:** System Design / Technical & Functional Design  
**Nama Sistem:** CKPN Calculator BPRS  
**Versi:** 3.0
**Tanggal:** 30 September 2026
**Target Platform:** Web Application  
**Backend:** Laravel 13  
**Admin/UI:** Filament 4+  
**Database:** MySQL 8+  
**Frontend Utility:** Tailwind CSS  
**Arsitektur:** Modular Service-Oriented Application

---

# 1. Ringkasan Eksekutif

Sistem ini dirancang sebagai **calculator CKPN BPRS** yang mengubah proses perhitungan berbasis kertas kerja Excel menjadi proses terstruktur, terdokumentasi, dapat diaudit, dan dapat diulang untuk setiap periode penilaian.

Kertas kerja yang dipelajari memisahkan proses menjadi:

1. Penurunan nilai secara **Individual**.
2. Penurunan nilai secara **Kolektif**.
3. Perhitungan **PD Net Flow**.
4. Perhitungan **PD Migration**.
5. Perhitungan **LGD Expected Recoveries**.
6. Perhitungan **LGD Collateral Shortfall**.
7. Perhitungan CKPN dengan formula `PD × LGD × EAD`.
8. Rekonsiliasi hasil CKPN Individual + CKPN Kolektif.

PSAK 414 yang disahkan DSAS IAI pada 29 April 2025 mengatur penurunan nilai aset keuangan syariah bagi entitas yang menerapkan SAK Indonesia untuk Entitas Privat. IAI menyatakan PSAK 414 menggunakan konsep **incurred loss**, berbasis data historis kerugian, tanpa unsur time value of money, dan efektif 1 Januari 2027 dengan opsi penerapan dini.

> **Catatan desain penting:** sistem harus memisahkan antara *standard rule*, *business rule BPRS*, dan *parameter kertas kerja*. Dengan demikian perubahan kebijakan atau interpretasi akuntansi tidak mengharuskan perubahan kode program.

---

# 2. Referensi Perancangan

## 2.1 Referensi utama

- PSAK 414: Penurunan Nilai Aset Keuangan Syariah bagi Entitas yang Menerapkan SAK Indonesia untuk Entitas Privat.
- Ketentuan OJK mengenai kualitas aset BPRS.
- Pedoman/ketentuan akuntansi BPRS yang berlaku.
- Kertas kerja `Kertas kerja.xlsx` yang menjadi dasar rancangan calculator.
- Data historis pembiayaan BPRS.

## 2.2 Catatan regulasi

PSAK 414 perlu dibedakan dari PSAK 413. PSAK 413 menggunakan pendekatan expected loss, sedangkan PSAK 414 menggunakan konsep incurred loss untuk entitas yang menerapkan SAK EP.

Oleh karena itu, sistem **tidak boleh meng-hardcode asumsi expected credit loss ala PSAK 413** sebagai satu-satunya pendekatan. Engine perhitungan harus mengikuti metodologi PSAK 414 yang diterapkan BPRS dan parameter yang telah ditetapkan.

Untuk aspek operasional BPRS, sistem juga perlu menyediakan konfigurasi agar formula EAD, klasifikasi kualitas, grouping, dan perlakuan akad dapat disesuaikan dengan ketentuan OJK dan kebijakan internal.

---

# 3. Tujuan Sistem

Sistem bertujuan untuk:

1. Menghitung CKPN Individual.
2. Menghitung CKPN Kolektif.
3. Mendukung metode PD Net Flow.
4. Mendukung metode PD Migration.
5. Menghitung LGD Expected Recoveries.
6. Menghitung LGD Collateral Shortfall.
7. Menghasilkan Weighted LGD.
8. Menghasilkan EAD berdasarkan akad dan business rule.
9. Menentukan debitur yang masuk Individual atau Kolektif.
10. Membentuk grouping pembiayaan berdasarkan tiga dimensi tetap:
    - kode kantor;
    - kode akad;
    - kode jenis penggunaan.
11. Mengklasifikasikan exposure ke bucket setelah grouping terbentuk untuk kebutuhan engine PD.
12. Menyediakan audit trail.
13. Menyediakan rekonsiliasi sumber data dengan hasil kalkulasi.
14. Menyediakan laporan per debitur, kontrak, group, akad, kantor, dan periode.
15. Menyimpan snapshot hasil perhitungan agar hasil historis tidak berubah ketika master parameter berubah.

---

# 4. Scope Akad

Versi awal wajib mendukung:

| Akad | Nama | Perlakuan EAD default |
|---|---|---|
| MURABAHAH | Murabahah | Outstanding pokok |
| MULTIJASA | Ijarah Multijasa | Outstanding pokok |
| MUSYARAKAH | Musyarakah | Tunggakan pokok, hanya jika telah jatuh tempo |
| IMBT | Ijarah Muntahiyah Bittamlik | Tunggakan pokok, hanya jika telah jatuh tempo |

## 4.1 Rule Murabahah

EAD default:

```text
EAD = Outstanding Pokok
```

Outstanding margin/ujrah yang belum menjadi pokok tidak otomatis dimasukkan ke EAD.

## 4.2 Rule Ijarah Multijasa

EAD default:

```text
EAD = Outstanding Pokok
```

Rule harus dapat dikonfigurasi melalui master akad.

## 4.3 Rule Musyarakah

Untuk kebutuhan bisnis yang ditetapkan dalam rancangan ini:

```text
IF tanggal_jatuh_tempo <= tanggal_penilaian
THEN EAD = Tunggakan Pokok
ELSE EAD = 0
```

Dengan demikian pembiayaan Musyarakah yang belum jatuh tempo tidak masuk ke basis EAD menggunakan rule ini.

> Rule ini harus dibuat sebagai parameterizable rule, bukan hardcode, karena perlakuan akuntansi untuk instrumen/eksposur Musyarakah perlu divalidasi terhadap ruang lingkup PSAK 414, kebijakan akuntansi BPRS, dan ketentuan regulator yang berlaku pada saat implementasi.

## 4.4 Rule IMBT

Untuk kebutuhan bisnis yang ditetapkan:

```text
IF tanggal_jatuh_tempo <= tanggal_penilaian
THEN EAD = Tunggakan Pokok
ELSE EAD = 0
```

Rule juga harus disimpan dalam konfigurasi akad.

---

# 5. Konsep Utama Perhitungan

## 5.1 CKPN Total

Secara konseptual:

```text
CKPN Total =
    CKPN Individual
    + CKPN Kolektif
```

## 5.2 CKPN Kolektif

```text
CKPN Kolektif =
    PD × LGD × EAD
```

Untuk setiap record/group:

```text
CKPN Group =
    PD Group
    × LGD Group
    × EAD Group
```

Kemudian:

```text
Total CKPN Kolektif =
    SUM(CKPN Group)
```

---

# 6. Arsitektur Perhitungan

Sistem menggunakan pipeline:

```text
SOURCE DATA
    |
    v
Data Validation
    |
    v
Normalization
    |
    v
Akad Rule Engine
    |
    v
EAD Engine
    |
    v
Individual / Collective Classification
    |
    +-----------------------+
    |                       |
    v                       v
Individual Engine      Collective Engine
                            |
                            +--> Grouping Engine
                            |
                            +--> PD Net Flow
                            |
                            +--> PD Migration
                            |
                            +--> LGD Engine
                            |
                            +--> CKPN = PD × LGD × EAD
    |                       |
    +-----------+-----------+
                |
                v
        CKPN Consolidation
                |
                v
          Reconciliation
                |
                v
             Report
```

---

# 7. Modul Sistem

## 7.1 Dashboard

Menampilkan:

- Periode aktif.
- Total outstanding.
- Total EAD.
- Total CKPN Individual.
- Total CKPN Kolektif.
- Total CKPN.
- Jumlah kontrak.
- Jumlah kontrak Individual.
- Jumlah kontrak Kolektif.
- Distribusi EAD per akad.
- Distribusi CKPN per kantor.
- Distribusi CKPN per group.
- PD rata-rata.
- LGD.
- Exception data.

---

# 8. Master Data

## 8.1 Master Kantor

Field:

```text
id
kode_kantor
nama_kantor
status
created_at
updated_at
```

## 8.2 Master Akad

Field:

```text
id
kode_akad
nama_akad
kategori
ead_rule_type
requires_due_date
active
```

Contoh:

```text
MURABAHAH
MULTIJASA
MUSYARAKAH
IMBT
```

## 8.3 Master Jenis Penggunaan

Contoh:

```text
MODAL_KERJA
INVESTASI
KONSUMTIF
```

## 8.4 Master Sektor Ekonomi

Dapat mengikuti mapping internal BPRS.

## 8.5 Master Skala Usaha

Contoh:

```text
MIKRO
KECIL
MENENGAH
NON_UMKM
```

## 8.6 Master Kualitas Pembiayaan

Minimal mendukung:

```text
1 - Lancar
2 - Dalam Perhatian Khusus
3 - Kurang Lancar
4 - Diragukan
5 - Macet
WO - Write Off
```

Label dan kode harus configurable.

---

# 9. Grouping Engine

Grouping pada versi desain ini **ditetapkan secara sederhana dan konsisten**. Sistem tidak lagi menyediakan arbitrary/dynamic grouping berdasarkan banyak dimensi.

Grouping utama hanya menggunakan tiga dimensi:

```text
KODE KANTOR
    ↓
KODE AKAD
    ↓
KODE JENIS PENGGUNAAN
```

Setelah kombinasi tersebut terbentuk, pembiayaan baru diklasifikasikan ke dalam **bucket tunggakan** untuk kebutuhan perhitungan PD Net Flow/PD Migration.

Dengan demikian:

```text
Portfolio
   |
   +-- Kode Kantor
          |
          +-- Kode Akad
                 |
                 +-- Kode Jenis Penggunaan
                        |
                        +-- Bucket 1
                        +-- Bucket 2
                        +-- ...
                        +-- Bucket 14
```

> **Pemisahan penting:** `Kode Kantor + Kode Akad + Kode Jenis Penggunaan` adalah **grouping portfolio**, sedangkan `Bucket` adalah **klasifikasi risiko/tunggakan di dalam group**. Bucket tidak digunakan sebagai dimensi pembentuk group utama.

## 9.1 Group Key

Setiap collective exposure memiliki group key:

```text
group_key =
    kode_kantor
    + kode_akad
    + kode_jenis_penggunaan
```

Contoh:

```text
001 / MURABAHAH / MODAL_KERJA
001 / MURABAHAH / KONSUMTIF
001 / MUSYARAKAH / MODAL_KERJA
002 / MURABAHAH / MODAL_KERJA
```

Setiap kombinasi yang memiliki data menjadi calculation group tersendiri.

## 9.2 Bucket Setelah Grouping

Setelah group terbentuk, data historical dan current exposure di dalam group dipetakan ke bucket:

```text
001 / MURABAHAH / MODAL_KERJA
    |
    +-- B1
    +-- B2
    +-- B3
    +-- ...
    +-- B14
```

Bucket digunakan oleh engine PD dan tidak mengubah identitas group.

## 9.3 Data Model Grouping

Struktur minimal:

```text
collective_groups
    |
    +-- office_code
    +-- akad_code
    +-- usage_type_code
    +-- group_key
```

Jika diperlukan versioning:

```text
grouping_profiles
grouping_profile_versions
collective_groups
collective_group_members
```

Namun grouping profile tidak lagi menyimpan daftar dimensi arbitrary. Profile hanya menyimpan bahwa metodologi menggunakan:

```text
OFFICE
AKAD
USAGE_TYPE
```

## 9.4 Contoh Grouping

Misalnya terdapat:

```text
2 Kantor
2 Akad
3 Jenis Penggunaan
```

maka secara teoritis terdapat maksimal:

```text
2 × 2 × 3 = 12 group
```

Tetapi hanya kombinasi yang benar-benar memiliki exposure yang dibuat sebagai group calculation.

Contoh:

```text
Kantor 001
├── MURABAHAH
│   ├── MODAL_KERJA
│   ├── INVESTASI
│   └── KONSUMTIF
└── MUSYARAKAH
    └── MODAL_KERJA

Kantor 002
├── MURABAHAH
│   ├── MODAL_KERJA
│   └── KONSUMTIF
└── IMBT
    └── INVESTASI
```

Masing-masing leaf grouping tersebut kemudian memiliki bucket 1–14 sesuai mapping bucket.

## 9.5 Grouping dan PD

PD harus dihitung secara terisolasi untuk setiap:

```text
Kode Kantor
+
Kode Akad
+
Kode Jenis Penggunaan
```

Contoh:

```text
001 / MURABAHAH / MODAL_KERJA
```

tidak boleh dicampur dengan:

```text
001 / MURABAHAH / KONSUMTIF
```

atau:

```text
002 / MURABAHAH / MODAL_KERJA
```

Historical data juga harus menggunakan group key yang sama agar transition dan compound flow comparable.

## 9.6 Grouping dan LGD

Prinsip grouping yang sama diterapkan pada:

```text
LGD Expected Recovery
LGD Collateral Shortfall
```

LGD dihitung pada context:

```text
Kode Kantor
+
Kode Akad
+
Kode Jenis Penggunaan
```

Bucket tidak menjadi dimensi group LGD kecuali formula/metodologi khusus yang telah disahkan BPRS mensyaratkannya.

# 10. Rule Engine

Semua formula penting harus dibuat sebagai rule.

Contoh:

```text
EAD_RULE_MURABAHAH
EAD_RULE_MULTIJASA
EAD_RULE_MUSYARAKAH
EAD_RULE_IMBT
```

Rule dapat memiliki:

```text
condition
formula
priority
effective_date
end_date
active
```

Contoh:

```text
Rule: EAD_MUSYARAKAH

IF maturity_date <= assessment_date
THEN outstanding_principal_overdue
ELSE 0
```

Dengan pendekatan ini, perubahan kebijakan tidak harus mengubah source code.

---

# 11. Data Input Pembiayaan

Minimal field:

```text
id
assessment_period_id
cif
no_kontrak
kode_kantor
kode_akad
kode_jenis_penggunaan
kode_sektor_ekonomi
kode_skala_usaha

tanggal_akad
tanggal_jatuh_tempo

plafond
harga_perolehan
outstanding_pokok
outstanding_margin
outstanding_ujrah

tunggakan_pokok
tunggakan_margin
tunggakan_ujrah

hari_tunggakan
bucket_tunggakan
kolektibilitas

status_restrukturisasi
status_write_off

jaminan
nilai_agunan
nilai_likuidasi
nilai_pasar

individual_flag
individual_reason

created_at
updated_at
```

---

# 12. Assessment Period

Setiap kalkulasi harus mempunyai periode.

Contoh:

```text
31 Januari 2026
28 Februari 2026
31 Maret 2026
30 Juni 2026
30 September 2026
31 Desember 2026
```

Entity:

```text
assessment_periods
```

Field:

```text
id
period_code
assessment_date
status
source_version
created_by
approved_by
approved_at
```

Status:

```text
DRAFT
VALIDATING
CALCULATING
REVIEW
APPROVED
LOCKED
CANCELLED
```

---

# 13. Data Import

Sistem harus mendukung:

- Excel.
- CSV.
- API/core banking.
- Database staging.

Pipeline:

```text
Upload
  |
  v
Staging Table
  |
  v
Validation
  |
  +--> Error
  |
  +--> Warning
  |
  v
Normalization
  |
  v
Production Snapshot
```

## 13.1 Validation

Contoh:

- no_kontrak kosong.
- kode akad tidak dikenal.
- kode kantor tidak dikenal.
- outstanding negatif.
- tanggal jatuh tempo invalid.
- EAD tidak sesuai rule.
- data CIF duplikat.
- data historis tidak lengkap.
- bucket tidak sesuai hari tunggakan.

---

# 14. Penentuan Individual vs Kolektif

Sistem menyediakan rule untuk menentukan kontrak masuk:

```text
INDIVIDUAL
atau
COLLECTIVE
```

## 14.1 Individual

Contoh parameter:

```text
individual_flag = true
```

atau berdasarkan kriteria:

```text
kolektibilitas tertentu
restrukturisasi tertentu
default condition
material exposure
manual selection
```

## 14.2 Manual Override

User berwenang dapat menetapkan:

```text
no_kontrak X
=> INDIVIDUAL
```

dengan alasan wajib.

Override harus masuk audit trail.

---

# 15. CKPN Individual Engine

Perhitungan Individual pada kertas kerja menggunakan pendekatan estimasi arus kas yang dapat diterima.

Secara konseptual:

```text
CKPN Individual =
Nilai Tercatat
-
Estimasi Arus Kas Masa Depan yang Dapat Dipulihkan
```

Jika menggunakan agunan:

```text
Recoverable Cash Flow =
Nilai Likuidasi / Realisasi Agunan
-
Biaya Penjualan / Biaya Eksekusi
+
Cash Recovery lain
```

Contoh dari kertas kerja:

```text
Nilai tercatat setelah komponen yang relevan
= Rp3.000.000.000

Estimasi arus kas diterima
= Rp2.300.000.000

CKPN
= Rp700.000.000
```

## 15.1 Individual Cash Flow Schedule

Sistem harus menyediakan:

```text
individual_assessments
individual_cash_flows
individual_collaterals
individual_recoveries
```

Cash flow dapat menyimpan:

```text
tanggal
estimasi_penerimaan
jenis_penerimaan
nilai_agunan
biaya_realisasi
nilai_recoverable
```

---

# 16. Collective Engine

Input:

```text
EAD
PD
LGD
```

Formula:

```text
CKPN = EAD × PD × LGD
```

Contoh:

```text
EAD = 1.000.000.000
PD  = 10%
LGD = 60%

CKPN =
1.000.000.000 × 10% × 60%
= 60.000.000
```

---

# 17. PD Net Flow Engine

Kertas kerja menggunakan bucket tunggakan dan histori perpindahan bucket.

Contoh bucket:

```text
1 = 0 hari
2 = 1–30 hari
3 = 31–60 hari
4 = 61–90 hari
5 = 91–120 hari
6 = 121–150 hari
7 = 151–180 hari
8 = 181–210 hari
9 = 211–240 hari
10 = 241–270 hari
11 = 271–300 hari
12 = 301–330 hari
13 = 331–360 hari
14 = >360 hari
```

Bucket harus configurable.

## 17.1 Transition Rate

Untuk setiap periode:

```text
Transition Rate =
Exposure yang berpindah ke bucket berikutnya
/
Exposure awal bucket
```

Contoh:

```text
Bucket 1 -> Bucket 2
```

## 17.2 Compound Flow to Loss

PD Net Flow tidak cukup hanya mengambil satu transition rate.

Engine harus dapat menghitung compound flow:

```text
PD Bucket =
Flow Bucket saat ini
+
Flow menuju bucket berikutnya
+
Flow lanjutan sampai Loss/Default
```

Implementasi harus mengikuti formula yang telah ditetapkan dalam policy/metodologi BPRS.

## 17.3 Historical Window

Sistem harus mendukung:

```text
12 bulan
24 bulan
36 bulan
48 bulan
60 bulan
custom
```

Jangan hardcode 12 bulan.

## 17.4 Weighted PD

Jika menggunakan weighted average:

```text
Weighted PD =
SUM(Exposure_i × PD_i)
/
SUM(Exposure_i)
```

---

# 18. PD Migration Engine

PD Migration menggunakan matriks perpindahan kualitas:

```text
             Ending Quality
Start     1      2      3      4      5      WO
------------------------------------------------
1         x      x      x      x      x      x
2         x      x      x      x      x      x
3         x      x      x      x      x      x
4         x      x      x      x      x      x
5         x      x      x      x      x      x
```

## 18.1 Migration Rate

```text
Migration Rate(i -> j) =
Exposure i yang berpindah ke j
/
Saldo awal exposure i
```

## 18.2 Default Probability

PD dapat diturunkan dari probabilitas berpindah menuju:

```text
Macet
Hapus Buku
Loss
```

sesuai metodologi yang ditetapkan BPRS.

## 18.3 Matrix Engine

Entity:

```text
pd_migration_periods
pd_migration_matrices
pd_migration_matrix_cells
pd_migration_results
```

---

# 19. LGD Engine

LGD mendukung dua metode:

```text
LGD Expected Recoveries
LGD Collateral Shortfall
```

Kertas kerja juga menunjukkan penggunaan weighted LGD.

## 19.1 Weighted LGD

Secara umum:

```text
Weighted LGD =
(Total Loss Expected Recoveries
 + Total Loss Collateral Shortfall)
/
(Total Exposure Expected Recoveries
 + Total Exposure Collateral Shortfall)
```

Namun formula final harus mengikuti policy akuntansi BPRS yang disetujui.

---

# 20. LGD Expected Recoveries

Data yang diperlukan:

```text
no_kontrak
tahun_write_off
saldo_write_off
tanggal_recovery
nilai_recovery
```

Recovery dapat dikelompokkan berdasarkan:

```text
Year 1
Year 2
Year 3
Year 4
Year 5
Year 6
...
```

## 20.1 Recovery Rate

```text
Recovery Rate =
Total Recovery
/
Total Write Off
```

## 20.2 LGD

```text
LGD =
1 - Recovery Rate
```

Sistem harus mendukung historical window configurable.

Default rancangan:

```text
5 tahun
```

tetapi tidak hardcode.

---

# 21. LGD Collateral Shortfall

Data:

```text
no_kontrak
outstanding_at_writeoff
nilai_agunan
tanggal_agunan
tanggal_eksekusi
hasil_eksekusi
biaya_eksekusi
```

## 21.1 Shortfall

```text
Shortfall =
MAX(
    Outstanding,
    Recovery Basis
)
-
Net Recovery
```

Implementasi yang lebih tepat:

```text
Shortfall =
MAX(0, Exposure - Net Recovery)
```

## 21.2 Net Recovery

```text
Net Recovery =
Gross Execution Value
-
Execution Cost
-
Selling Cost
-
Other Recoverable Costs
```

## 21.3 LGD

```text
LGD =
Total Shortfall
/
Total Exposure
```

---

# 22. EAD Engine

EAD harus menggunakan **Akad Rule Engine**.

## 22.1 Mapping

| Akad | EAD |
|---|---|
| Murabahah | outstanding pokok |
| Multijasa | outstanding pokok |
| Musyarakah | tunggakan pokok jika jatuh tempo |
| IMBT | tunggakan pokok jika jatuh tempo |

## 22.2 Pseudocode

```php
switch ($akad->ead_rule_type) {

    case 'OUTSTANDING_PRINCIPAL':
        return $contract->outstanding_principal;

    case 'OVERDUE_PRINCIPAL_ONLY':
        return $contract->due_date <= $assessmentDate
            ? $contract->overdue_principal
            : 0;

    default:
        throw new InvalidEadRuleException();
}
```

Di production, implementasi sebaiknya menggunakan Strategy Pattern, bukan switch langsung di service.

---

# 23. EAD Reconciliation

Sistem harus menghitung:

```text
EAD Source
-
EAD Calculated
=
Difference
```

Jika:

```text
Difference != 0
```

maka status:

```text
EAD_MISMATCH
```

Kertas kerja juga menggunakan konsep pemeriksaan bahwa EAD posisi penilaian harus konsisten dengan data posisi yang digunakan untuk PD.

---

# 24. Calculation Run

Setiap perhitungan dibuat sebagai:

```text
calculation_runs
```

Contoh:

```text
RUN-2026-09-30-001
```

Metadata:

```text
assessment_period
method
grouping_profile
pd_method
lgd_method
ead_rule_version
data_snapshot_version
created_by
created_at
```

Hasil calculation run tidak boleh dihitung ulang secara destruktif.

---

# 25. Snapshot

Setiap calculation run harus menyimpan:

```text
input_snapshot
parameter_snapshot
grouping_snapshot
pd_snapshot
lgd_snapshot
ead_snapshot
result_snapshot
```

Tujuannya:

> Jika parameter PD berubah enam bulan kemudian, hasil CKPN September 2026 tetap dapat direproduksi persis seperti saat approval.

---

# 26. Database Design

## 26.1 Core Tables

```text
users
roles
permissions

offices
akad_types
usage_types
economic_sectors
business_scales
quality_ratings

assessment_periods
calculation_runs

financing_contracts
financing_snapshots
financing_histories

collaterals
collateral_realizations
recoveries
write_offs
```

## 26.2 Grouping Tables

```text
grouping_profiles
grouping_levels
grouping_values
grouping_assignments
```

## 26.3 PD Tables

```text
pd_methods
pd_historical_periods
pd_netflow_buckets
pd_netflow_transitions
pd_netflow_results

pd_migration_periods
pd_migration_matrices
pd_migration_results
```

## 26.4 LGD Tables

```text
lgd_methods
lgd_expected_recovery_periods
lgd_expected_recovery_details
lgd_expected_recovery_results

lgd_collateral_periods
lgd_collateral_details
lgd_collateral_results
```

## 26.5 CKPN Tables

```text
individual_assessments
individual_cash_flows

collective_groups
collective_calculations
collective_calculation_details

ckpn_results
ckpn_reconciliations
```

## 26.6 Audit Tables

```text
audit_logs
calculation_logs
approval_logs
data_import_logs
validation_errors
```

---

# 27. Relational Model

```text
offices
   |
   +---- financing_contracts
              |
              +---- akad_types
              +---- usage_types
              +---- economic_sectors
              +---- business_scales
              |
              +---- financing_snapshots
              |
              +---- collaterals
              +---- recoveries
              |
              +---- individual_assessments
              |
              +---- collective_calculation_details
```

---

# 28. Service Layer Laravel

Direkomendasikan struktur:

```text
app/
├── Domain/
│   └── Ckpn/
│       ├── Entities/
│       ├── Enums/
│       ├── DTOs/
│       ├── Rules/
│       ├── Services/
│       ├── Strategies/
│       └── ValueObjects/
│
├── Services/
│   ├── Ckpn/
│   ├── Ead/
│   ├── Pd/
│   ├── Lgd/
│   ├── Grouping/
│   ├── Import/
│   └── Reconciliation/
│
├── Filament/
│   ├── Resources/
│   ├── Pages/
│   └── Widgets/
│
└── Models/
```

---

# 29. Design Pattern

Gunakan:

### Strategy Pattern

Untuk:

```text
PD Net Flow
PD Migration

LGD Expected Recoveries
LGD Collateral Shortfall

EAD Murabahah
EAD Multijasa
EAD Musyarakah
EAD IMBT
```

Contoh:

```php
interface PdCalculatorInterface
{
    public function calculate(
        AssessmentContext $context
    ): PdCalculationResult;
}
```

Implementasi:

```text
NetFlowPdCalculator
MigrationPdCalculator
```

LGD:

```php
interface LgdCalculatorInterface
{
    public function calculate(
        AssessmentContext $context
    ): LgdCalculationResult;
}
```

---

# 30. Calculation Context

Semua engine menerima context yang sama:

```php
AssessmentContext
```

Isi:

```text
assessment_date
assessment_period
contracts
grouping_profile
pd_method
lgd_method
ead_rules
historical_window
quality_mapping
bucket_mapping
```

Keuntungannya adalah calculation engine menjadi deterministic dan mudah diuji.

---

# 31. Workflow Operasional

```text
1. Create Assessment Period
        |
2. Import Financing Data
        |
3. Validate Data
        |
4. Normalize Data
        |
5. Configure Grouping
        |
6. Configure Individual Criteria
        |
7. Calculate EAD
        |
8. Calculate PD
        |
9. Calculate LGD
        |
10. Calculate Individual CKPN
        |
11. Calculate Collective CKPN
        |
12. Reconcile
        |
13. Review
        |
14. Approval
        |
15. Lock Period
        |
16. Generate Report
```

---

# 32. Approval Workflow

Role:

```text
MAKER
CHECKER
APPROVER
ADMIN
AUDITOR
```

Workflow:

```text
DRAFT
  |
  v
CALCULATED
  |
  v
CHECKED
  |
  v
APPROVED
  |
  v
LOCKED
```

Tidak boleh mengubah calculation run yang telah `LOCKED`.

---

# 33. Audit Trail

Setiap perubahan menyimpan:

```text
user_id
action
module
record_type
record_id
old_value
new_value
reason
ip_address
user_agent
created_at
```

Contoh:

```text
User A
mengubah:

No Kontrak:
123456

Classification:
COLLECTIVE -> INDIVIDUAL

Reason:
Debitur ditetapkan sebagai individual assessment.
```

---

# 34. Exception Management

Sistem harus menyediakan daftar exception.

Contoh:

```text
MISSING_AKAD
MISSING_OFFICE
INVALID_DUE_DATE
NEGATIVE_EAD
EAD_MISMATCH
NO_PD_DATA
NO_LGD_DATA
NO_HISTORICAL_DATA
INVALID_GROUP
DUPLICATE_CONTRACT
INDIVIDUAL_OVERRIDE
MISSING_COLLATERAL_VALUE
```

Setiap exception memiliki severity:

```text
ERROR
WARNING
INFO
```

---

# 35. Reporting

## 35.1 Executive Report

```text
Periode
Total Pembiayaan
Total EAD
CKPN Individual
CKPN Kolektif
Total CKPN
```

## 35.2 Report per Akad

```text
Murabahah
Multijasa
Musyarakah
IMBT
```

## 35.3 Report per Kantor

```text
Kantor
Outstanding
EAD
PD
LGD
CKPN
```

## 35.4 Report per Group

```text
Group
Jumlah Kontrak
EAD
PD
LGD
CKPN
```

## 35.5 Contract Detail

```text
CIF
No Kontrak
Akad
Kantor
Jenis Penggunaan
Outstanding
Tunggakan
Hari Tunggakan
EAD
PD
LGD
CKPN
Classification
```

---

# 36. Reconciliation Report

Wajib tersedia:

```text
Source Outstanding
Source Tunggakan
Calculated EAD
Difference
```

dan:

```text
Total Individual
+
Total Collective
=
Total CKPN
```

Contoh:

```text
CKPN Individual        700.000.000
CKPN Kolektif        1.258.730.798
----------------------------------
Total CKPN           1.958.730.798
```

Angka di atas hanya contoh dari kertas kerja dan bukan angka produksi.

---

# 37. Filament Resource

Resource yang direkomendasikan:

```text
OfficeResource
AkadTypeResource
UsageTypeResource
EconomicSectorResource
BusinessScaleResource
QualityRatingResource

AssessmentPeriodResource
FinancingContractResource

GroupingProfileResource

PdNetflowResource
PdMigrationResource

LgdExpectedRecoveryResource
LgdCollateralResource

IndividualAssessmentResource

CalculationRunResource
CkpnResultResource

ApprovalResource
AuditLogResource
```

---

# 38. Dashboard Filament

Widget:

```text
CkpnSummaryWidget
EadByAkadWidget
CkpnByOfficeWidget
CkpnByGroupingWidget
IndividualVsCollectiveWidget
PdSummaryWidget
LgdSummaryWidget
ExceptionWidget
CalculationStatusWidget
```

---

# 39. Security

Wajib:

- RBAC.
- Policy Laravel.
- Form validation.
- SQL injection protection melalui Eloquent/Query Builder.
- XSS protection.
- CSRF.
- Mass assignment protection.
- Encryption untuk data sensitif bila diperlukan.
- Audit trail.
- Period locking.
- Approval segregation.
- Tidak boleh menghapus calculation run yang sudah approved.
- Semua import memiliki source reference.

---

# 40. Performance

Karena data BPRS dapat mencapai puluhan/ratusan ribu kontrak:

Gunakan:

```text
Queue
Batch Processing
Chunk Query
Database Indexing
Caching
Materialized Calculation Snapshot
```

Index minimal:

```text
assessment_period_id
no_kontrak
cif
kode_kantor
kode_akad
kode_jenis_penggunaan
tanggal_jatuh_tempo
hari_tunggakan
bucket_tunggakan
kolektibilitas
```

---

# 41. Idempotency

Calculation harus idempotent.

Jika:

```text
Calculation Run A
```

dijalankan dua kali dengan:

```text
Input Snapshot sama
Parameter sama
Grouping sama
```

maka hasil harus sama.

Gunakan:

```text
calculation_hash
```

Contoh:

```text
SHA256(
    assessment_period
    + data_snapshot
    + parameter_version
    + grouping_version
    + methodology_version
)
```

---

# 42. Versioning

Wajib versioning:

```text
PSAK methodology version
OJK rule version
EAD rule version
PD methodology version
LGD methodology version
Grouping version
```

Contoh:

```text
CKPN-METHOD-2026.01
EAD-RULE-2026.01
PD-NETFLOW-2026.01
LGD-ER-2026.01
LGD-CS-2026.01
```

---

# 43. Configuration

Admin dapat mengatur:

```text
Bucket tunggakan
Historical period
PD method
LGD method
Grouping basis (Kantor + Akad + Jenis Penggunaan)
EAD rule
Individual criteria
Quality mapping
Default calculation parameters
```

Tidak boleh menyimpan formula kritis hanya sebagai text bebas tanpa validation.

---

# 44. Minimum Historical Data

Berdasarkan kertas kerja:

```text
PD:
minimal 2–3 tahun sebagai starting point

LGD:
sekitar 5 tahun terakhir
```

Namun sistem harus mendukung konfigurasi:

```text
historical_window_years
```

dan memberikan warning jika data historis kurang dari minimum policy.

---

# 45. Data Quality Score

Tambahkan skor kualitas data:

```text
Completeness
Consistency
Validity
Historical Coverage
Reconciliation
```

Contoh:

```text
Data Quality = 96.5%
```

Jika di bawah threshold:

```text
CALCULATION_REVIEW_REQUIRED
```

---

# 46. Alur Detail Collective Calculation

```text
Assessment Date
      |
      v
Load Financing Snapshot
      |
      v
Apply Akad EAD Rule
      |
      v
Determine Individual / Collective
      |
      v
Build Dynamic Group
      |
      v
Load Historical Data
      |
      +------------------+
      |                  |
      v                  v
 PD Net Flow       PD Migration
      |                  |
      +--------+---------+
               |
               v
          PD Result
               |
               v
          LGD Engine
               |
        +------+------+
        |             |
        v             v
 Expected        Collateral
 Recoveries      Shortfall
        |             |
        +------+------+
               |
               v
          Weighted LGD
               |
               v
           PD × LGD
               |
               v
              EAD
               |
               v
        Collective CKPN
```

---

# 47. Contoh Dynamic Group

Misalnya BPRS memilih:

```text
LEVEL 1 = KODE_KANTOR
LEVEL 2 = KODE_AKAD
LEVEL 3 = KODE_JENIS_PENGGUNAAN
LEVEL 4 = BUCKET_TUNGGAKAN
```

Maka:

```text
01
└── MURABAHAH
    └── MODAL_KERJA
        ├── 0
        ├── 1-30
        ├── 31-60
        ├── 61-90
        └── >360
```

Setiap leaf group memiliki:

```text
EAD
PD
LGD
CKPN
```

---

# 48. Prinsip Penting: PD dan EAD Harus Comparable

Jika PD dihitung berdasarkan:

```text
Posisi Desember 2026
```

maka EAD yang digunakan untuk calculation run harus memiliki:

```text
Assessment Date = Desember 2026
```

Jika tidak:

```text
PD-EAD-MISMATCH
```

Sistem harus memberikan error/warning.

---

# 49. Prinsip Penting: Grouping Harus Konsisten

PD historis harus dihitung dengan grouping yang comparable dengan exposure yang akan dinilai.

Contoh:

Jika current portfolio dikelompokkan:

```text
Kantor
+
Akad
+
Jenis Penggunaan
```

maka historical data idealnya juga dapat direkonstruksi menggunakan dimensi yang sama.

Sistem harus mencatat:

```text
grouping_profile_version
```

---

# 50. Prinsip Penting: No Silent Calculation

Sistem tidak boleh:

- Mengisi PD kosong dengan 0 tanpa warning.
- Mengisi LGD kosong dengan 0 tanpa warning.
- Mengubah EAD tanpa log.
- Mengabaikan kontrak yang tidak memiliki mapping akad.
- Menghilangkan kontrak dari calculation tanpa alasan.

Setiap excluded record harus memiliki:

```text
excluded_reason
```

---

# 51. API Internal

Service API:

```text
POST /api/ckpn/assessment
POST /api/ckpn/import
POST /api/ckpn/validate
POST /api/ckpn/evaluate-ead
POST /api/ckpn/calculate-pd
POST /api/ckpn/calculate-lgd
POST /api/ckpn/calculate-individual
POST /api/ckpn/calculate-collective
POST /api/ckpn/reconcile
POST /api/ckpn/approve
POST /api/ckpn/lock
```

---

# 52. Testing Strategy

## Unit Test

Wajib menguji:

```text
EAD Murabahah
EAD Multijasa
EAD Musyarakah
EAD IMBT

PD Net Flow
PD Migration

LGD Expected Recovery
LGD Collateral Shortfall

Individual CKPN
Collective CKPN
Weighted LGD
Grouping
```

## Feature Test

Test:

```text
Import
Validation
Calculation Run
Approval
Lock
Reporting
```

## Regression Test

Kertas kerja Excel dijadikan:

```text
Golden Dataset
```

Sistem harus menghasilkan nilai yang sama dengan kertas kerja untuk dataset yang sama, dengan perbedaan hanya pada pembulatan yang telah didefinisikan.

---

# 53. Golden Dataset

Sebelum production:

```text
Excel Kertas Kerja
       |
       v
Extract Test Dataset
       |
       v
Run Laravel Calculator
       |
       v
Compare Result
       |
       +--> PASS
       |
       +--> FAIL
```

Tolerance harus didefinisikan:

```text
absolute tolerance
relative tolerance
rounding rule
```

---

# 54. Rounding Policy

Jangan melakukan pembulatan pada intermediate calculation kecuali diwajibkan policy.

Rekomendasi:

```text
Database:
DECIMAL(24,8)

Display:
Rp 0
atau
Rp 2 decimal
```

PD/LGD:

```text
DECIMAL(12,10)
```

Perhitungan menggunakan precision penuh kemudian dibulatkan pada output.

---

# 55. Output Calculation

Setiap detail:

```text
Group ID
Jumlah Kontrak
EAD
PD
LGD
CKPN
```

Contoh:

```text
GROUP-00001
Contracts : 125
EAD       : 5.000.000.000
PD        : 12,50%
LGD       : 59,29%
CKPN      : 370.562.500
```

---

# 56. Struktur Result

```text
ckpn_results
```

Field:

```text
id
calculation_run_id
contract_id nullable
group_id nullable
classification
akad
office
usage_type
bucket
ead
pd
lgd
ckpn
calculation_method
formula_version
created_at
```

---

# 57. Calculation Trace

Setiap hasil harus dapat ditelusuri:

```text
CKPN
 |
 +--> EAD
 |     |
 |     +--> Akad Rule
 |
 +--> PD
 |     |
 |     +--> Method
 |     +--> Historical Data
 |     +--> Group
 |
 +--> LGD
       |
       +--> Expected Recovery
       +--> Collateral Shortfall
```

User auditor dapat membuka satu hasil dan melihat asal seluruh komponennya.

---

# 58. Auditability Requirement

Untuk satu no kontrak, auditor harus dapat menjawab:

1. Mengapa masuk Individual/Kolektif?
2. Berapa EAD?
3. Rule EAD apa yang digunakan?
4. Berapa PD?
5. Dari periode histori mana?
6. Group apa?
7. Berapa LGD?
8. LGD berasal dari metode apa?
9. Berapa CKPN?
10. Siapa yang menjalankan?
11. Siapa yang memeriksa?
12. Siapa yang menyetujui?
13. Parameter versi berapa?

---

# 59. Roadmap Implementasi

## Phase 1 – Foundation

- Laravel 13.
- Filament 4+.
- Authentication.
- RBAC.
- Master data.
- Assessment period.
- Import.

## Phase 2 – EAD

- Akad rules.
- EAD engine.
- Validation.
- Reconciliation.

## Phase 3 – PD

- PD Net Flow.
- PD Migration.
- Historical data.
- Transition matrix.
- Weighted PD.

## Phase 4 – LGD

- Expected Recoveries.
- Collateral Shortfall.
- Weighted LGD.

## Phase 5 – CKPN

- Individual.
- Collective.
- Consolidation.
- Calculation run.
- Snapshot.

## Phase 6 – Governance

- Review.
- Approval.
- Lock.
- Audit trail.
- Reporting.

## Phase 7 – Optimization

- Queue.
- Batch calculation.
- API.
- Core banking integration.
- Automated scheduled calculation.

---

# 60. Prioritas Pengembangan

Prioritas:

```text
P0
- Data model
- Assessment period
- Financing import
- EAD Engine
- Grouping Engine

P1
- PD Net Flow
- PD Migration
- LGD Expected Recovery
- LGD Collateral Shortfall

P2
- Individual CKPN
- Collective CKPN
- Reconciliation

P3
- Approval
- Audit
- Reporting

P4
- API
- Core banking integration
- Automation
```

---

# 61. Keputusan Arsitektur Utama

Sistem harus mengikuti prinsip:

### 1. Configuration over Hardcoding

Rule bisnis disimpan sebagai configuration/master.

### 2. Calculation is Immutable

Calculation run yang telah approved tidak diubah.

### 3. Snapshot Based

Hasil harus dapat direproduksi.

### 4. Strategy Based

Metode PD, LGD, dan EAD menggunakan Strategy Pattern.

### 5. Standardized Grouping

Grouping collective menggunakan tiga dimensi tetap:

```text
Kode Kantor
→ Kode Akad
→ Kode Jenis Penggunaan
```

Bucket merupakan tahap klasifikasi setelah group terbentuk, bukan dimensi grouping utama.

### 6. Full Traceability

Setiap CKPN harus dapat ditelusuri sampai sumber datanya.

---

# 62. Rancangan Menu

```text
Dashboard

Master Data
├── Kantor
├── Akad
├── Jenis Penggunaan
├── Sektor Ekonomi
├── Skala Usaha
├── Kualitas Pembiayaan
├── Bucket Tunggakan
└── Parameter

Data Pembiayaan
├── Import
├── Validation
├── Financing
└── Historical Data

Assessment
├── Periode
├── Individual Criteria
├── Grouping Profile
└── Calculation Run

PD
├── PD Net Flow
├── PD Migration
└── Historical PD

LGD
├── Expected Recoveries
├── Collateral Shortfall
└── Weighted LGD

CKPN
├── Individual
├── Collective
├── Result
└── Reconciliation

Approval
├── Review
├── Approval
└── Locked Period

Reports
├── Executive
├── Per Kantor
├── Per Akad
├── Per Group
├── Per Kontrak
└── Audit Report

Administration
├── Users
├── Roles
├── Permissions
└── Audit Logs
```

---

# 63. Kesimpulan Perancangan

Sistem CKPN Calculator BPRS sebaiknya dibangun bukan sebagai "kalkulator Excel versi web", tetapi sebagai **calculation platform** yang memiliki:

```text
Data Layer
+
Rule Engine
+
Grouping Engine
+
PD Engine
+
LGD Engine
+
EAD Engine
+
Individual Assessment
+
Collective Assessment
+
Reconciliation
+
Approval
+
Audit Trail
+
Reporting
```

Empat akad utama yang ditargetkan:

```text
Murabahah
Multijasa
Musyarakah
IMBT
```

dapat menggunakan EAD rule yang berbeda tanpa membuat engine perhitungan terpisah.

Rancangan EAD awal:

```text
Murabahah
    -> Outstanding Pokok

Multijasa
    -> Outstanding Pokok

Musyarakah
    -> Tunggakan Pokok jika telah jatuh tempo

IMBT
    -> Tunggakan Pokok jika telah jatuh tempo
```

Sementara metode collective:

```text
PD Net Flow
atau
PD Migration

dikombinasikan dengan

LGD Expected Recoveries
atau
LGD Collateral Shortfall

kemudian:

CKPN = PD × LGD × EAD
```

Dengan dynamic grouping, BPRS dapat membentuk kelompok seperti:

```text
Kantor
 -> Akad
    -> Jenis Penggunaan
       -> Bucket Tunggakan
```

atau konfigurasi lain tanpa perubahan source code.

---

# 64. Catatan Validasi Sebelum Production

Sebelum sistem digunakan sebagai dasar pelaporan resmi, beberapa hal wajib memperoleh sign-off dari fungsi Accounting/Finance, Risk, Compliance, dan manajemen:

1. Interpretasi final ruang lingkup PSAK 414 untuk setiap akad.
2. Perlakuan Musyarakah dan IMBT.
3. Definisi EAD masing-masing akad.
4. Kriteria Individual vs Collective.
5. Definisi default/loss.
6. Formula PD Net Flow.
7. Formula PD Migration.
8. Formula LGD Expected Recoveries.
9. Formula LGD Collateral Shortfall.
10. Weighted LGD.
11. Historical observation window.
12. Minimum data historis.
13. Bucket tunggakan.
14. Grouping yang dianggap memiliki risiko kredit serupa.
15. Treatment restrukturisasi.
16. Treatment write-off.
17. Treatment recovery setelah write-off.
18. Treatment agunan.
19. Rounding.
20. Approval dan governance.

Dokumen ini menjadi **blueprint awal**. Formula final yang menjadi *accounting policy* harus dikunci melalui parameter versi dan approval, bukan ditentukan hanya oleh programmer.

---

# 65. Referensi Eksternal

- Ikatan Akuntan Indonesia – Pengesahan PSAK 414.
- Ikatan Akuntan Indonesia – Draf Eksposur PSAK 414.
- Otoritas Jasa Keuangan – POJK 24 Tahun 2024 tentang Kualitas Aset BPRS.
- Otoritas Jasa Keuangan – materi/pedoman akuntansi BPRS yang berlaku.



---

# 66. Workflow Perhitungan Revisi Berdasarkan Kertas Kerja

Catatan tambahan ini menjadi bagian integral dari desain sistem dan memperjelas workflow calculation run.

Urutan utama:

```text
1. Penentuan Periode
        |
2. Penetapan Kriteria CKPN
        |
3. Seleksi Individual / Collective / Excluded
        |
4. Grouping Portfolio
        |
5. Pemilihan Metode PD
        |
6. Pemilihan Historical Window
        |
7. Historical Grouping
        |
8. PD Calculation
        |
9. LGD Calculation
        |
10. EAD Calculation
        |
11. CKPN Calculation
        |
12. Reconciliation
        |
13. Review & Approval
```

# 67. Tahap 1 – Penentuan Periode Perhitungan

User membuat Assessment Period dengan format `YYYYMM`.

Contoh:

```text
202608
```

berarti Agustus 2026 dan sistem membentuk:

```text
period_code    = 202608
assessment_date = 2026-08-31
```

Periode harus unik, bulan valid, dan periode yang sudah `LOCKED` tidak boleh diubah.

# 68. Tahap 2 – Penetapan Kriteria CKPN

Setelah periode dibuat, user memilih Calculation Profile yang menentukan:

```text
CKPN Individual Criteria
CKPN Collective Criteria
Excluded Criteria
Grouping Profile
PD Method
PD Historical Window
LGD Method
EAD Rules
```

Contoh:

```text
Individual:
Kolektibilitas > 3
+
TOP N berdasarkan outstanding

Collective:
Remaining Eligible Financing

Excluded:
Financing yang memenuhi exclusion rule
```

# 69. Tahap 3 – Seleksi CKPN Individual

Sistem mendukung kriteria NPL dengan:

```text
kolektibilitas > 3
```

dan TOP N berdasarkan:

```text
Outstanding Pokok DESC
```

Contoh:

```text
NPL = 500 kontrak
TOP N = 100

100 kontrak -> Individual
Sisa eligible -> Collective
```

TOP N harus configurable dan selection result harus menyimpan ranking serta alasan seleksi.

# 70. Tahap 4 – Excluded Financing

Portfolio dibagi menjadi:

```text
INDIVIDUAL
COLLECTIVE
EXCLUDED
```

Setiap excluded record wajib memiliki:

```text
excluded_reason
excluded_by
excluded_at
```

Kontrol rekonsiliasi:

```text
Total Source Records
=
Individual + Collective + Excluded
```

# 71. Tahap 5 – Portfolio Grouping

Grouping dilakukan setelah Individual, Collective, dan Excluded ditentukan.

Struktur grouping ditetapkan:

```text
LEVEL 1 = KODE_KANTOR
LEVEL 2 = KODE_AKAD
LEVEL 3 = KODE_JENIS_PENGGUNAAN
```

Contoh:

```text
001
└── MURABAHAH
    ├── MODAL_KERJA
    ├── INVESTASI
    └── KONSUMTIF
```

Masing-masing kombinasi menjadi calculation group tersendiri jika memiliki exposure.

Setelah group terbentuk, sistem melakukan bucket classification:

```text
001 / MURABAHAH / MODAL_KERJA
    ├── B1
    ├── B2
    ├── ...
    └── B14
```

**Bucket tidak membentuk group baru pada tahap grouping.** Bucket hanya memecah exposure di dalam group untuk perhitungan PD.

# 72. Tahap 6 – Pemilihan Metode PD

User memilih:

```text
PD NETFLOW
```

atau:

```text
PD MIGRATION
```

Pilihan disimpan pada Calculation Profile/Run sehingga hasil historis tetap dapat direproduksi.

# 73. Tahap 7 – Historical Window

User dapat memilih:

```text
12 bulan
24 bulan
36 bulan
48 bulan
60 bulan
CUSTOM
```

Contoh:

```text
Assessment = 202201
Historical Window = 12 bulan
```

Data utama:

```text
Januari 2021 s/d Januari 2022
```

Namun PD Net Flow membutuhkan opening bucket dan extended transition period sehingga calendar perhitungan tidak boleh hanya menggunakan dua tanggal.

# 74. Tahap 8 – Historical Grouping

Historical data harus dikelompokkan menggunakan tiga dimensi yang sama:

```text
Kode Kantor
+
Kode Akad
+
Kode Jenis Penggunaan
```

Contoh:

```text
001 + MURABAHAH + MODAL_KERJA
```

Kemudian, di dalam group tersebut historical exposure dipetakan ke bucket per periode:

```text
Periode
  |
  +-- B1
  +-- B2
  +-- ...
  +-- B14
```

Dengan demikian PD tidak tercampur antar group dan bucket tetap menjadi klasifikasi internal group.

# 75. Struktur Waktu PD Net Flow

Untuk:

```text
Assessment = Januari 2022
Historical Window = 12 bulan
```

data utama:

```text
Januari 2021 s/d Januari 2022
```

opening bucket:

```text
Desember 2020
```

sehingga bucket observation:

```text
Desember 2020 s/d Desember 2021
```

actual transition:

```text
Januari 2021 s/d Desember 2021
```

extended/rolling transition:

```text
Januari 2022 s/d Desember 2022
```

Sistem harus membedakan:

```text
Bucket Observation Period
Actual Transition Period
Rolling Forecast / Extended Transition Period
```

# 76. Notasi Periode dan Bucket

Gunakan:

```text
Pn = Periode ke-n
Bn = Bucket ke-n
```

Contoh:

```text
P1 = periode pertama
B1 = bucket pertama
```

Transition:

```text
T(B1 -> B2, P1)
```

berarti perpindahan dari B1 periode sebelumnya menuju B2 pada P1.

# 77. PD Net Flow – Table Bucket

Table pertama:

```text
pd_netflow_buckets
```

Minimal:

```text
id
calculation_run_id
group_id
period
bucket_no
bucket_code
outstanding
contract_count
```

Tujuannya menyimpan outstanding/exposure setiap group pada setiap periode dan bucket.

# 78. PD Net Flow – 14 Bucket

Default methodology menyediakan 14 bucket.

Master bucket:

```text
bucket_no
bucket_code
bucket_name
min_dpd
max_dpd
is_loss_bucket
is_writeoff_bucket
active
```

Nomor bucket merupakan identifier metodologi dan mapping harus berasal dari parameter kertas kerja, bukan asumsi program.

# 79. Special Rule Bucket 14 – >360 Hari dan Write-Off

Bucket 14 mengakomodasi:

```text
hari_tunggakan > 360
```

dan pembiayaan write-off yang terjadi pada periode yang sama.

Contoh:

```text
STSACC = 'W'
TGLWO   = 20250829
Period  = 202508
```

maka record masuk:

```text
Bucket 14
```

Pseudocode:

```php
if ($statusAcc === 'W'
    && period($tglWo) === $period) {
    return 14;
}

if ($statusAcc !== 'W'
    && $hariTunggakan > 360) {
    return 14;
}
```

Rule harus diimplementasikan melalui `BucketClassificationStrategy`, bukan hard-code pada controller.

# 80. PD Net Flow – Table Perpindahan Bucket

Table kedua:

```text
pd_netflow_transitions
```

Menyimpan perpindahan bucket per group dan periode.

Field:

```text
id
calculation_run_id
group_id
from_period
to_period
from_bucket
to_bucket
opening_outstanding
destination_outstanding
transition_percentage
calculation_type
```

# 81. Rumus Perpindahan Bucket

Untuk perpindahan B1 -> B2 pada Januari 2021:

```text
Transition Rate =
Outstanding Januari 2021 Bucket 2
/
Outstanding Desember 2020 Bucket 1
× 100
```

Secara umum:

```text
T(Bn -> Bn+1, Pn)
=
Outstanding(Bn+1, Pn)
/
Outstanding(Bn, Pn-1)
× 100
```

# 82. Zero Denominator Rule

Jika denominator:

```text
Outstanding(Bn, Pn-1) = 0
```

maka rule kertas kerja menetapkan:

```text
Transition Rate = 100%
```

atau:

```text
1.000000
```

Jika disimpan sebagai decimal.

Rule harus memiliki parameter:

```text
zero_denominator_transition_rule = ONE
```

agar dapat diaudit.

# 83. Actual Transition Period

Untuk assessment 202201 dan historical window 12 bulan:

```text
Jan 2021
Feb 2021
...
Dec 2021
```

menggunakan actual historical transition.

Contoh:

```text
Jan 2021:
Dec 2020 B1 -> Jan 2021 B2

Feb 2021:
Jan 2021 B1 -> Feb 2021 B2

...

Dec 2021:
Nov 2021 B1 -> Dec 2021 B2
```

# 84. Rolling Transition Period

Mulai Januari 2022, transition menggunakan rolling average 6 bulan sebelumnya.

Contoh:

```text
Transition Jan 2022
=
AVG(
Jul 2021,
Aug 2021,
Sep 2021,
Oct 2021,
Nov 2021,
Dec 2021
)
```

Untuk Februari 2022:

```text
AVG(
Aug 2021,
Sep 2021,
Oct 2021,
Nov 2021,
Dec 2021,
Jan 2022
)
```

dan seterusnya.

Formula:

```text
Forecast Transition(Pn)
=
AVG(Pn-6 ... Pn-1)
```

Rolling window harus configurable, dengan default:

```text
6 bulan
```

# 85. PD Net Flow – Table Compound Flow to Loss

Table ketiga:

```text
pd_netflow_compound_flows
```

Minimal:

```text
id
calculation_run_id
group_id
source_bucket
target_loss_bucket
period
direct_flow
compound_flow
pd
calculation_version
```

Tujuan table ini adalah menghitung compound flow dari bucket sumber menuju bucket loss.

Secara konseptual:

```text
B1
 -> B2
 -> B3
 -> ...
 -> B14
```

Compound flow memperhitungkan kemungkinan perpindahan bertahap sampai Loss/Bucket 14.

Formula final harus mengikuti formula pada kertas kerja sebagai golden calculation specification.

# 86. Group Isolation pada PD Net Flow

Setiap group memiliki tiga result set utama:

```text
GROUP = Kantor + Akad + Jenis Penggunaan
 |
 +--> Bucket
 |
 +--> Transition
 |
 +--> Compound Flow to Loss
```

Contoh:

```text
001 + MURABAHAH + MODAL_KERJA
001 + MURABAHAH + KONSUMTIF
001 + MULTIJASA + KONSUMTIF
002 + MURABAHAH + MODAL_KERJA
```

Tidak boleh mencampur outstanding antar-group.

Bucket 1–14 berada di dalam masing-masing group dan digunakan untuk perhitungan transition serta compound flow.

# 87. Grouping untuk LGD

Prinsip grouping yang sama diterapkan pada:

```text
LGD Expected Recovery
LGD Collateral Shortfall
```

LGD dihitung berdasarkan:

```text
Kantor
+
Akad
+
Jenis Penggunaan
```

Contoh:

```text
Kantor 001 + Murabahah + Modal Kerja
Kantor 001 + Murabahah + Konsumtif
Kantor 002 + Musyarakah + Modal Kerja
```

Setiap group menyimpan exposure, recovery/shortfall, LGD, dan calculation trace.

# 88. Group-Level PD, LGD, EAD

Setiap leaf group memiliki:

```text
PD
LGD
EAD
CKPN
```

Formula:

```text
CKPN Group =
PD Group × LGD Group × EAD Group
```

Contoh ilustrasi:

```text
Group = Kantor 01 + Murabahah
PD    = 12%
LGD   = 60%
EAD   = 10.000.000.000

CKPN = 720.000.000
```

# 89. Calculation Profile

Tambahkan entity:

```text
calculation_profiles
```

Minimal:

```text
id
profile_code
profile_name
individual_enabled
individual_criteria_json
collective_enabled
collective_criteria_json
excluded_criteria_json
grouping_profile_id
pd_method
pd_historical_months
pd_rolling_months
lgd_expected_recovery_enabled
lgd_collateral_shortfall_enabled
ead_rule_version
status
effective_from
effective_to
created_by
approved_by
```

# 90. Individual Selection Result

Tambahkan:

```text
individual_selection_rules
individual_selection_results
```

Result menyimpan:

```text
assessment_period_id
calculation_run_id
contract_id
criteria_id
ranking_value
ranking_position
selected
selection_reason
```

Dengan demikian auditor dapat mengetahui mengapa kontrak masuk TOP N.

# 91. Exclusion Result

Tambahkan:

```text
exclusion_rules
exclusion_results
```

Minimal:

```text
contract_id
exclusion_code
exclusion_reason
source
created_by
```

# 92. Revised PD Net Flow Data Model

```text
pd_netflow_runs
    |
    +-- pd_netflow_groups
            |
            +-- pd_netflow_buckets
            |
            +-- pd_netflow_transitions
            |
            +-- pd_netflow_compound_flows
```

Semua child record terikat pada:

```text
calculation_run_id
group_id
period
methodology_version
```

# 93. Revised LGD Data Model

Expected Recovery:

```text
lgd_expected_recovery_runs
    |
    +-- lgd_expected_recovery_groups
            |
            +-- lgd_expected_recovery_details
            |
            +-- lgd_expected_recovery_results
```

Collateral Shortfall:

```text
lgd_collateral_runs
    |
    +-- lgd_collateral_groups
            |
            +-- lgd_collateral_details
            |
            +-- lgd_collateral_results
```

# 94. Filament Wizard Workflow

UI calculation sebaiknya berbentuk wizard:

```text
Step 1  Periode
Step 2  Kriteria CKPN
Step 3  Individual Selection
Step 4  Excluded Financing
Step 5  Grouping
Step 6  PD Method
Step 7  Historical Window
Step 8  Historical Grouping
Step 9  PD Calculation
Step 10 LGD Calculation
Step 11 EAD Calculation
Step 12 CKPN Calculation
Step 13 Reconciliation
Step 14 Review
Step 15 Approval
```

# 95. Calculation Preview

Sebelum calculation dijalankan, tampilkan:

```text
Assessment       : 202608
Total Contract   : 25.450
Individual       : 100
Collective       : 24.900
Excluded         : 450
Grouping         : Kantor + Akad + Jenis Penggunaan
PD Method        : PD Net Flow
Historical       : 60 bulan
Rolling Forecast : 6 bulan
```

User harus melakukan confirmation.

# 96. Calculation Calendar

Karena PD Net Flow memiliki beberapa horizon waktu, sistem membutuhkan `CalculationCalendar`.

Calendar menghasilkan:

```text
bucket_periods
transition_periods
forecast_periods
```

Contoh untuk assessment 202201:

```text
202012 -> Opening Bucket

202101 -> Bucket + Actual Transition
202102 -> Bucket + Actual Transition
...
202112 -> Bucket + Actual Transition

202201 -> Assessment / Extended Transition
202202 -> Rolling Forecast
...
202212 -> Rolling Forecast
```

Jangan mengimplementasikan kebutuhan ini hanya sebagai satu `start_date` dan `end_date`.

# 97. Full Collective Calculation

```text
Collective Portfolio
       |
       v
Dynamic Grouping
       |
       +------------------+
       |        |         |
       v        v         v
     Group A  Group B   Group C
       |        |         |
       v        v         v
      PD       PD        PD
       |        |         |
      LGD      LGD       LGD
       |        |         |
      EAD      EAD       EAD
       |        |         |
       v        v         v
     CKPN A   CKPN B    CKPN C
       |        |         |
       +--------+---------+
                |
                v
       Total CKPN Collective
```

# 98. Revised Acceptance Criteria

Sistem harus memenuhi minimal:

### Periode

`202608` dapat dibuat sebagai assessment period valid.

### Individual

Jika criteria:

```text
kolektibilitas > 3
TOP N = 100
```

maka eligible exposure diranking berdasarkan outstanding dan 100 exposure teratas dipilih sebagai Individual, sesuai exclusion rule.

### Collective

Exposure eligible yang bukan Individual masuk Collective.

### Grouping

Jika terdapat kombinasi Kantor + Akad + Jenis Penggunaan, setiap kombinasi yang memiliki data menghasilkan group calculation context tersendiri. Bucket 1–14 dihitung di dalam masing-masing group.

### PD Net Flow

Setiap group memiliki:

```text
Bucket
Transition
Compound Flow
```

### Historical Window

Assessment Januari 2022 dengan window 12 bulan membentuk:

```text
Main data       : Jan 2021 – Jan 2022
Opening bucket  : Dec 2020
Bucket history  : Dec 2020 – Dec 2021
Actual transition: Jan 2021 – Dec 2021
Extended period : Jan 2022 – Dec 2022
```

### Zero Denominator

Jika denominator transition = 0:

```text
rate = 100%
```

sesuai methodology profile.

### Rolling Average

Untuk extended transition:

```text
rate = average 6 bulan sebelumnya
```

### LGD

LGD menggunakan grouping context yang telah ditentukan oleh Calculation Profile.

# 99. Prinsip Final

Urutan dependency:

```text
Assessment Period
       |
       v
Calculation Profile
       |
       v
Eligibility / Classification
       |
       +--> Individual
       +--> Collective
       +--> Excluded
                |
                v
         Dynamic Grouping
                |
        +-------+-------+
        |               |
        v               v
    Current Data    Historical Data
        |               |
        |        +------+------+
        |        |             |
        |        v             v
        |     Net Flow      Migration
        |        |             |
        |        +------+------+
        |               |
        |               v
        |              PD
        |
        v
       EAD
        |
        +----------------+
                         |
Historical Loss/Recovery |
        |                |
        v                |
       LGD --------------+
                         |
                         v
                  PD × LGD × EAD
                         |
                         v
                  Collective CKPN
```

# 100. Target Architecture

Dengan requirement tambahan, sistem harus diposisikan sebagai **multi-stage CKPN calculation engine**, bukan sekadar calculator.

Komponen inti:

```text
Assessment Period Engine
Eligibility / Classification Engine
Standardized Grouping Engine
Bucket Classification Engine
Calculation Calendar Engine
PD Net Flow Bucket Engine
PD Net Flow Transition Engine
Compound Flow to Loss Engine
PD Migration Engine
LGD Group Engine
EAD Rule Engine
Individual CKPN Engine
Collective CKPN Engine
Reconciliation Engine
Calculation Snapshot Engine
Audit & Approval Engine
```

Khusus PD Net Flow, tiga table utama wajib dipertahankan:

```text
1. Bucket
2. Perpindahan Bucket
3. Compound Flow to Loss
```

Ketiganya harus terikat pada:

```text
Calculation Run
+
Grouping Profile
+
Group
+
Period
+
Methodology Version
```

Dengan desain ini, grouping:

```text
Kantor
 -> Akad
    -> Jenis Penggunaan
       -> Bucket
```

dapat berkembang secara dinamis tanpa perubahan source code. Prinsip yang sama dapat diterapkan pada LGD Expected Recovery dan LGD Collateral Shortfall.


---

# 101. Revision Note v3 – Standardized Grouping

Perubahan utama dari desain sebelumnya:

### Sebelumnya

Grouping dirancang sebagai dynamic grouping yang dapat memilih banyak dimensi seperti:

```text
Kantor
Akad
Jenis Penggunaan
Sektor Ekonomi
Skala Usaha
Produk
Kualitas
Bucket
```

### Revisi v3

Grouping collective ditetapkan hanya:

```text
KODE KANTOR
    ↓
KODE AKAD
    ↓
KODE JENIS PENGGUNAAN
```

Setelah group terbentuk:

```text
GROUP
  ↓
BUCKET CLASSIFICATION
  ↓
PD / LGD / EAD
  ↓
CKPN
```

Dengan desain ini:

1. Grouping menjadi konsisten antar-periode.
2. Historical grouping menggunakan key yang sama dengan current portfolio.
3. PD Net Flow dihitung terpisah untuk setiap group.
4. PD Migration dihitung terpisah untuk setiap group.
5. LGD dihitung berdasarkan group yang sama.
6. Bucket tidak menciptakan group portfolio baru.
7. Database dan service layer menjadi lebih sederhana.
8. Risiko tercampurnya exposure antar kantor, akad, atau jenis penggunaan dapat dikendalikan melalui group key.
9. Penambahan bucket tidak mengubah struktur grouping.
10. Auditor dapat menelusuri setiap hasil CKPN sampai kombinasi `kode kantor + kode akad + kode jenis penggunaan`.

**Status dokumen:** Revised v3 – Grouping Specification Updated
