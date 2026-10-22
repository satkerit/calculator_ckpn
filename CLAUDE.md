# CLAUDE.md – AI Agent Execution Guidelines

**Sistem:** CKPN Calculator BPRS  
**Versi:** 3.0  
**Tanggal:** 22 October 2026  
**Status:** ACTIVE – Mandatory for all AI agents operating in this repository

---

## 0. Sebelum Membaca Prompt — Jalankan Ini Dulu

Setiap agent AI yang bekerja di repository ini WAJIB membaca dan mengikuti protokol ini sebelum mengeksekusi prompt pertama. Ini bukan opsional — ini adalah perjanjian kerja.

---

## 1. Prompt Execution Workflow (4 Tahap Wajib)

### Tahap 1 — UNDERSTAND: Pahami Konteks, Periksa Kode, Temukan Gap

Sebelum menulis satu baris kode:

1. **Pahami intent bisnis** — Apakah ini berkaitan dengan: CKPN, EAD, PD, LGD, dashboard, master data, export, import, UI/UX, atau administrasi?
2. **Baca file yang relevan** — Gunakan tool `Read`, `Grep`, `Glob` untuk memeriksa kode yang ada. Dilarang keras memodifikasi kode sebelum membaca dan memahaminya.
3. **Temukan gap & permasalahan:**
   - Bug atau logika yang salah
   - Missing validation / boundary check
   - Query N+1 atau loop O(n²)
   - Risiko data corruption (transaksi DB tidak dibungkus)
   - Ketidakkonsistenan format/tipe data
   - Visual "AI slop" (gradient berlebih, efek hover tidak fungsional, UI tanpa hierarki)

### Tahap 2 — AUTO-SKILL SELECTION: Pilih Skill Secara Otomatis

Langsung setelah memahami konteks, deteksi skill yang paling relevan dan panggil `Skill` tool **sebagai aksi pertama** sebelum menulis kode:

| Konteks Prompt | Skill yang Dipanggil |
|---|---|
| Laravel controller, model, Eloquent, migration, route | `laravel-best-practices` atau `spatie-laravel-php` |
| Query lambat, N+1, caching Redis, indexing DB | `laravel-database-optimization` |
| Keamanan: auth, CSRF, file upload, SQL injection | `laravel-owasp-security` atau `spatie-security` |
| Testing: unit, feature, Pest, assertion | `laravel-testing` |
| Tailwind CSS, responsive, dark mode, migration v3→v4 | `tailwind-best-practices` atau `tailwindcss-development` |
| UI/UX Design — komponen, layout, sistem desain | `ui-ux-pro-max` atau `ui-styling` |
| Redesign UI — halaman, navigation, dashboard | `ui-ux-pro-max` + `tailwind-best-practices` |
| Meminimalkan kode / refactor lean / YAGNI review | `ponytail` |
| Optimasi token AI / biaya / model audit | `token-saver` |

**Jika ragu antara dua skill:** panggil keduanya, terapkan yang paling relevan.  
**Jika tidak ada skill yang cocok:** lanjutkan tanpa skill, tapi tetap ikuti prinsip koding di bawah.

### Tahap 3 — PLAN & EXECUTE: Rencanakan, Baru Kerjakan

1. Buat `TodoWrite` jika ada ≥ 3 langkah diskrit.
2. Kerjakan satu tugas selesai sebelum lanjut ke berikutnya. Mark `completed` segera.
3. Tulis kode mengikuti standar koding di bawah.
4. Verifikasi perubahan: jalankan diagnostics atau test jika tersedia.

### Tahap 4 — LOG CHANGE: Dokumentasi Wajib ke CHANGELOG.md

Setiap prompt/tugas selesai, tambahkan entri di `CHANGELOG.md`:

```markdown
## [Unreleased] — YYYY-MM-DD
### [Tipe]: Judul Perubahan
- Detail singkat apa yang diubah
- File yang terdampak
- Alasan perubahan (bug fix / improvement / refactor / feature)
```

Tipe valid: `Added`, `Changed`, `Fixed`, `Removed`, `Security`, `Refactored`.

---

## 2. Code Quality Standards

### PHP & Laravel

```php
// ✅ BENAR — Select spesifik, eager-load, transaksi
DB::transaction(function () use ($runId) {
    $groups = CollectiveGroup::select(['id', 'group_key', 'total_ead'])
        ->where('calculation_run_id', $runId)
        ->with(['pd:id,group_id,pd_calculated', 'lgd:id,group_id,lgd_calculated'])
        ->get();
    // ...
});

// ❌ SALAH — SELECT *, N+1, tanpa transaksi
$groups = CollectiveGroup::all();
foreach ($groups as $g) {
    $g->pd; // N+1 query
    $g->lgd;
}
```

**Naming Convention:**
- Variable & kolom DB: `snake_case` (`outstanding_balance`, `product_code`)
- Method & JSON output key: `camelCase` (`calculateEad()`, `validatedCount`)
- Konstanta: `UPPER_SNAKE_CASE` (`DEFAULT_PD_RATE`, `TOLERANCE_PERCENT`)
- Class & Controller: `PascalCase` (`EadCalculationAgent`, `AssessmentController`)

**Decimal Precision — Wajib untuk data keuangan:**
```php
// Gunakan bcmath untuk nominal CKPN, EAD, LGD, PD
$ckpn = bcmul(bcmul((string)$ead, (string)$pd, 8), (string)$lgd, 4);
// Atau cast Eloquent: $casts = ['outstanding_balance' => 'decimal:2']
```

**Safety Checklist:**
- [ ] Input sanitized: `trim()`, `strtoupper()`, date parsed dengan `Carbon::parse()` / `createFromFormat()`
- [ ] Boundary validated sebelum proses: `assert($pd >= 0 && $pd <= 1)`
- [ ] Operasi multi-table dibungkus `DB::transaction()`
- [ ] Nilai default eksplisit saat data missing (`$recovery = $recovery ?? 0`)
- [ ] Audit log ditulis setelah setiap operasi write

### Laravel-Specific Best Practices

```php
// ✅ Eager-load
Financing::select(['id', 'loan_number', 'office_code', 'product_code', 'outstanding_balance'])
    ->with(['office:id,code,name', 'snapshot:id,financing_id,periode_snapshot'])
    ->where('assessment_period_id', $periodId)
    ->chunkById(100, function ($batch) {
        // Proses batch, jangan load semua ke memory
    });

// ✅ Transaction atomic
DB::transaction(function () {
    Ead::upsert($records, ['loan_number', 'run_id'], ['ead_calculated']);
    AuditLog::create([...]);
});
```

**Database Rules:**
- Selalu tambahkan `index` untuk kolom yang sering di-filter: `loan_number`, `calculation_run_id`, `assessment_period_id`, `office_code`, `periode_snapshot`.
- Hindari raw SQL kecuali tidak ada cara dengan Query Builder/Eloquent.
- Gunakan Eloquent casting, jangan manual casting di view.

---

## 3. UI/UX Anti-Slop Rules

Aturan ini berlaku untuk semua template Blade, Tailwind CSS:

```
❌ DILARANG:
- Gradient saturasi tinggi (bg-gradient-to-r from-blue-400 via-purple-500 to-pink-500)
- hover:scale-105 / hover:scale-110 pada card data — tidak fungsional, mengganggu
- Shadow bertingkat (shadow-xl + shadow-2xl + ring pada elemen sama)
- Rounded corners ≥ rounded-2xl untuk card data keuangan — tidak enterprise
- Warna status yang tidak konsisten (beda page beda makna warna)

✅ STANDAR:
- Border tipis: border border-gray-200/80 atau border-gray-100
- Rounded konsisten: rounded-lg untuk card, rounded-md untuk badge/button
- Shadow minimal: shadow-sm untuk card, tanpa shadow untuk tabel/row
- Status badge: border + bg-[color]-50 text-[color]-700 border-[color]-200
- Nominal keuangan: font-mono text-right
- Heading konsisten: text-sm font-semibold text-gray-900 (card heading)
                     text-xs uppercase tracking-wide text-gray-500 (label KPI)
```

**Design System Wajib Project Ini:**
- Primary: `blue-600` (action, active state, CTA)
- Success: `emerald-600` / `green-600`
- Warning: `amber-600`
- Danger/Error: `red-600`
- Neutral: `gray-900` (text utama), `gray-500` (subtext), `gray-200` (border)
- Background page: `slate-50`
- Card background: `white`

---

## 4. Agent Pipeline (Business Logic Layer)

### Urutan Wajib Pipeline CKPN

```
DataValidationAgent
    → EadCalculationAgent
    → ClassificationAgent
    → GroupingAgent
    → (paralel) PdNetFlowAgent || PdMigrationAgent
    → (paralel) LgdExpectedRecoveryAgent + LgdCollateralShortfallAgent
    → LgdWeightedAgent
    → (paralel) CkpnCollectiveAgent + CkpnIndividualAgent
    → CkpnConsolidationAgent
    → ReconciliationAgent
    (continuous) AuditLogAgent
```

### Output JSON Standard Semua Agent

```json
{
  "status": "SUCCESS | FAIL | WARNING",
  "agent": "NamaAgent",
  "count": 0,
  "data": {},
  "details": [],
  "execution_time_ms": 0,
  "audit_log_id": null
}
```

### Guard Constants

```php
// Gunakan constants, bukan magic numbers
const DEFAULT_PD_RATE         = 0.05;   // 5% jika data historis < 24 bulan
const DEFAULT_LGD_RATE        = 0.50;   // 50% jika data historis < 5 tahun
const EAD_TOLERANCE_PERCENT   = 0.0001; // 0.01% toleransi reconciliation
const HISTORICAL_WINDOW_PD    = 36;     // bulan untuk PD Net Flow
const HISTORICAL_WINDOW_LGD   = 60;     // bulan untuk LGD Expected Recovery
const DATA_QUALITY_APPROVE    = 85;     // % minimum untuk status APPROVED
const DATA_QUALITY_WARNING    = 70;     // % minimum untuk status WARNING
```

---

## 5. Documentation & Change Tracking Protocol

### CHANGELOG.md Format

Wajib tambahkan entri setiap kali ada perubahan signifikan pada project, termasuk:
- Setiap prompt user yang mengakibatkan perubahan file
- Setiap bugfix, improvement, refactor
- Setiap perubahan skema DB (migration)

Format:
```
## [Unreleased] — YYYY-MM-DD
### Added — Fitur baru
### Changed — Perubahan perilaku yang ada
### Fixed — Perbaikan bug
### Removed — Fitur atau kode yang dihapus
### Security — Perbaikan keamanan
### Refactored — Perubahan internal tanpa perubahan fungsionalitas
```

### Code Comment Requirement

Komentar diperlukan hanya saat logika tidak self-evident:
```php
// ponytail: hanya support EAD fixed rule, tambahkan dynamic rule engine jika product types > 10
$ead = match($productCode) {
    'MURABAHAH', 'MULTIJASA' => $outstandingBalance,
    'MUSYARAKAH', 'IMBT'     => $expiredDate <= $assessmentDate ? $principalArrears : 0,
    default                   => $outstandingBalance, // fallback: log warning via AuditLogAgent
};
```

---

## 6. Environment Setup

Verifikasi environment sebelum memulai development atau menjalankan pipeline:

```sh
php -v          # Minimum PHP 8.2
composer -V     # Minimum Composer 2.x
php artisan about  # Cek konfigurasi app, DB, cache

# Pastikan .env lengkap:
# APP_ENV=local|production
# DB_CONNECTION=mysql
# CACHE_DRIVER=redis  (wajib untuk production — caching agent rules)
# QUEUE_CONNECTION=redis
```

---

## 7. Troubleshooting Quick Reference

| Masalah | Langkah Pertama |
|---|---|
| N+1 query terdeteksi | Tambahkan `with()` pada relasi, atau refactor ke join |
| Nilai CKPN negative | Cek guard `max(0, nilai_tercatat - estimasi_recovery)` |
| PD di luar [0,1] | Validasi data historis window, cek division by zero |
| Audit log tidak tercatat | Cek apakah agent berada dalam `DB::transaction()` yang gagal rollback |
| Agent salah urutan | Periksa `agent_configurations.execution_order` dan dependency chain |
| UI terlihat "slop" / noisy | Terapkan anti-slop rules di atas, panggil skill `ui-ux-pro-max` |
| Token boros | Panggil skill `token-saver`, audit query `SELECT *`, cek eager-load |

---

**Prepared by:** AI Assistant / TraeCode Agent  
**Date:** 2026-10-22  
**Status:** ACTIVE — berlaku untuk semua agent yang bekerja di repository ini.
