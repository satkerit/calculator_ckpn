# PD Net Flow Calculation Pattern Analysis

## Data Flow:
1. **Snapshot Filtering** → filter by (office_code, akad_code, usage_code)
2. **Bucket Assignment** → group per bucket per periode
3. **Transition Rate** → calculate movement dari bucket i ke bucket i+1
4. **Compound Flow to Loss** → calculate cumulative PD ke bucket 14 (loss)

## Tabel 1: Min. Bucket (Outstanding per Bucket per Periode)
- **Input:** `snapshots` table filtered by group segmentasi
- **Group by:** `bucket_no`, `periode_snapshot`
- **Aggregate:** SUM(outstanding_balance)

**Pattern dari gambar:**
- Bucket 1 (0 hari): 77,620,800,000 (Des-20) → 80,034,000,000 (Jan-21) → 82,811,600,000 (Feb-21) → ... → 90,811,200,000 (Des-21)
- Bucket 2 (1-30 hari): 576,400,000 (Des-20) → 847,100,000 (Jan-21) → 1,601,100,000 (Feb-21) → ... → 1,058,400,000 (Des-21)
- Pattern: Historical data dari Des-20 s/d Des-21 (13 bulan), lalu kolom kuning (projections/future)

## Tabel 2: Perpindahan Bucket (Transition Rate)
**Formula:**
```
Transition Rate (Bucket i → Bucket i+1) pada periode P = 
  (Outstanding Bucket i+1 pada periode P) / (Outstanding Bucket i pada periode P-1)
```

**Contoh dari gambar:**
- Row "1 to 2" Jan-21: 1.09%
  - Bucket 2 Jan-21 = 847,100,000
  - Bucket 1 Des-20 = 77,620,800,000
  - Rate = 847,100,000 / 77,620,800,000 = 0.0109 = 1.09%

- Row "2 to 3" Jan-21: 66.93%
  - Bucket 3 Jan-21 = 385,800,000
  - Bucket 2 Des-20 = 576,400,000
  - Rate = 385,800,000 / 576,400,000 = 0.6693 = 66.93%

**Pattern:**
- Transition BUKAN dari bucket i ke i+1, tapi **BERAPA BANYAK yang ada di bucket i (periode lalu) PINDAH ke bucket i+1 (periode ini)**
- Formula lebih tepat:
  ```
  Transition Rate(i→i+1, periode P) = 
    COUNT(loan di bucket i+1 periode P yang periode P-1 ada di bucket i) / 
    COUNT(loan di bucket i periode P-1)
  ```

## Tabel 3: Compound Flow to Loss (PD per Source Bucket)
**Formula:** Compound probability dari bucket source menuju bucket 14 (loss/WO)

**Contoh dari gambar:**
- Row "1 to 2" Jan-21: 0.10%
- Row "2 to 3" Jan-21: 8.34%
- Row "3 to 4" Jan-21: 28.67%

**Pattern:**
Compound PD dari bucket i ke loss bucket (14) menggunakan chain transition:
```
PD(1→loss) = Σ [P(1→2) × P(2→3) × ... × P(13→14)]
PD(2→loss) = Σ [P(2→3) × P(3→4) × ... × P(13→14)]
...
```

**Formula Compound Flow:**
```
CF(bucket_i, periode) = 
  Σ [transition_rate(i, i+1, periode) × 
     transition_rate(i+1, i+2, periode) × 
     ... × 
     transition_rate(13, 14, periode)]
```

Atau lebih tepat dengan rolling average multi-path (tidak hanya direct path, tapi semua kemungkinan path ke loss).

## Key Insight:
1. **Tabel 1** = Raw data snapshot per bucket per periode
2. **Tabel 2** = Transition matrix (movement rate bucket i → i+1)
3. **Tabel 3** = PD final (compound transition dari bucket source ke loss bucket 14)

## Implementation Strategy:
1. Build transition matrix dari historical snapshots (loan-level tracking)
2. Calculate transition rate per period
3. Calculate compound PD menggunakan matrix multiplication atau recursive sum
