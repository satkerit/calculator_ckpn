# CHANGELOG — CKPN Calculator BPRS

Format mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Setiap perubahan kode/konfigurasi signifikan WAJIB dicatat di sini.

---

## [Unreleased] — 2026-11-04

### Changed — Anti-Slop UI Remediation: Hierarki Stat Card (#27) & Format Tabel Monospace (#34)
- **Modul LGD ER & CS (`calculation/modules/lgd_er.blade.php`, `calculation/modules/lgd_cs.blade.php`)**:
  - Menerapkan hierarki visual kartu KPI (#27) dengan *Average LGD* sebagai kartu utama (hero stat card span 2 kolom) beraksen biru lembut dan border kontras, serta kartu sekunder untuk jumlah grup dan metode.
  - Memperbaiki kolom numerik dan nominal keuangan pada tabel hasil LGD ER dan LGD CS menggunakan `font-mono` rata kanan dengan prefix `Rp` (#34).
- **Modul CKPN Individual & Kolektif (`calculation/modules/detail_ckpn.blade.php`, `calculation/modules/ckpn_individual.blade.php`, `calculation/modules/ckpn_collective.blade.php`)**:
  - Menyelaraskan seluruh angka finansial (Nilai Tercatat, Agunan, Pengurangan, Recovery, CKPN, EAD) dengan `font-mono`, perataan kanan, serta penambahan prefix `Rp` untuk keterbacaan data audit perbankan (#34).
- **Laporan Ringkasan CKPN (`reporting/ckpn-summary.blade.php`)**:
  - Menambahkan prefix `Rp` pada kolom nominal monospaced (EAD, Individual CKPN, Collective CKPN, Total CKPN).

### Changed — Format Tabel Compound Flow to Loss pada PD Net Flow Detail
- Menyesuaikan header dan label baris pada tabel **Compound Flow to Loss** di `resources/views/calculation/pd_netflow_detail.blade.php`:
  - Judul tabel disederhanakan menjadi `Compound Flow to Loss`.
  - Menggabungkan kolom `Bucket` dan `Label` menjadi satu kolom header `Bucket` dengan format perpindahan antar-bucket (`1 to 2`, `2 to 3`, ..., `13 to 14`) sesuai spesifikasi visual PSAK 414 / matriks perpindahan compound loss.

### Audit — Verifikasi Fungsi & Logika (tanpa perubahan kode)
- **Metode:** boot aplikasi + refleksi seluruh route action, pemindaian `route()/view()/DB::table()` di 298 berkas, cek indeks/isi DB live, dan `php artisan test`.
- **Hasil statis:** 0 view hilang, 0 nama route tak terdefinisi, seluruh route action resolve kecuali `role.show`, `product-akad.show`, `bucket.show` (method belum ada pada controller).
- **Temuan HIGH (terverifikasi):**
  - `snapshot` tidak memiliki unique index `(loan_number, periode_snapshot)` → `upsert()` saat import berperilaku INSERT (duplikat periode menggelembungkan EAD/CKPN). Data saat ini 0 duplikat.
  - Tabel `audit_logs` belum pernah dibuat (tidak ada migrasi) sehingga `BaseCalculationController::audit()` selalu no-op → audit trail CALCULATE/FINALIZE tidak tercatat (AGENTS.md #14 & README menyatakan tabel ini ada).
  - `routes/api.php` memakai `permission:assessment.create|assessment.view` yang tidak ada di `permissions.name` (7 baris legacy ber-`name` kosong) → API v1 selalu 403, ditambah guard `auth` (session) pada rute API.
  - `ComprehensiveExportController` dead code: mereferensikan model `App\Models\SystemConfiguration` (tidak ada) dan 6 tabel `stage_*` yang tidak pernah dibuat; tidak terdaftar di route.
  - Ambiguitas identitas bucket: `snapshot.bucket_no`/label memakai `bucket_definitions.urutan`, sedangkan PD Netflow (`PdNetflowCalculator`, `PdNetflowService::getActiveBucketIds()`) memakai `bucket_definitions.id`. Nilainya sama (1–14) saat ini sehingga belum terlihat, tetapi menambah/mengurutkan ulang bucket memutus join PD/CKPN.
  - `snapshot.periode_snapshot` bercampur format di data live: 73 nilai `YYYYMM` (519.387 baris) + 3 nilai tanggal (`2020-12-01`, `2021-01-01`, `2021-02-01`, 150 baris). Pencarian periode memakai kesamaan persis `YYYYMM` di Classification/DetailCkpn/CkpnDetail/PdNetFlow/LgdCS.
- **Temuan MEDIUM:** `Role::permissionNames()` memakai static cache per role id (risiko stale pada proses panjang/Octane); filter `write_off_flag` di `ClassificationController::run()` membuat cabang NPL write-off tidak pernah tercapai; CKPN Individual memakai `ead_amount` sebagai nilai tercatat (tunggakan pokok tidak masuk basis); `eadGroups()` membaca `request()` saat komputasi; `usage_code` fallback ke `financing_contract_code`; route export `/{module}/{assessmentPeriod}` menutupi `/module/{module}` (route `calculation.export.module` tak terjangkau); command `pd:test` hardcode `periodId = 20` dan menulis ke tabel stage.
- **Test suite merah:** 19 failed / 1 skipped / 45 passed. 16 kegagalan berupa HTTP 403 pada harness tes (resolusi permission di DB live terbukti benar: pivot `role_permissions` → `calculation.run`, `hasPermission()=true`), 3 kegagalan `PdNetflowTableRulesTest` karena `bucket_definitions` kosong pada DB tes (kode kini config-driven), 2 kegagalan `RegistrationTest` karena registrasi publik memang ditutup.
- **Tidak ada perubahan kode** pada audit ini; perbaikan menunggu konfirmasi prioritas dari user.

### Paket A — Integritas Data Snapshot & Audit Trail (dieksekusi)
- **`database/migrations/2026_11_04_000001_create_audit_logs_table.php`**: membuat tabel `audit_logs` (user_id, agent_name, action, module, record_type, record_id, old_value, new_value, reason, ip_address, timestamps + index `(record_type, record_id, action, created_at)`). Tanpa FK ke `users` agar jejak audit tetap utuh bila user dihapus. Dengan ini `BaseCalculationController::audit()` berhenti menjadi no-op.
- **`database/migrations/2026_11_04_000002_normalize_snapshot_period_format.php`**: menormalkan `snapshot.periode_snapshot` dari `YYYY-MM-DD` / `YYYY-MM` / `YYYYMMDD` menjadi `YYYYMM` (per nilai distinct, tanpa memuat 500k baris ke memori).
- **`database/migrations/2026_11_04_000003_add_unique_index_to_snapshot_table.php`**: dedupe `(loan_number, periode_snapshot)` — chunk 200 key, simpan baris `updated_at` terbaru per transaksi — lalu menambah unique index `snapshot_loan_period_unique` sehingga `Snapshot::upsert()` benar-benar UPDATE, bukan INSERT.
- **`app/Http/Controllers/DataImportController.php`**: `normalizePeriod()` menormalkan `periode_snapshot` saat import (YYYYMM kanonik) agar format tanggal tidak masuk lagi lewat upload; pengecekan duplikat dalam batch ikut memakai nilai ternormalisasi.
- **Verifikasi:** `php artisan migrate --force` sukses; `audit_logs` ada dengan 13 kolom; unique index `snapshot_loan_period_unique(loan_number,periode_snapshot)` aktif; 0 baris `periode_snapshot` non-`YYYYMM`; 0 key duplikat; payload `audit()` berhasil di-insert (rollback); test suite tetap 19 failed / 1 skipped / 45 passed (baseline sama, migrasi tidak merusak tes sqlite).
- **Catatan data:** 6.870 baris periode `202610` masuk pada 2026-11-04 01:32 (di luar sesi ini — import aplikasi berjalan) dan sudah ikut ternormalisasi/terindeks tanpa duplikat.

---

## [Unreleased] — 2026-11-01

### Fixed — Import (HTTP 500)
- **Akar masalah 500:** `DataImportController::uploadSnapshot()` dan `DataImportController::streamImport()` memanggil `response()->stream($closure, $headers)`. Argumen kedua `ResponseFactory::stream()` adalah `$status` (int) dan header ada di argumen ketiga, sehingga array header dikirim sebagai status → `TypeError` dari `StreamedResponse::__construct()` → HTTP 500 (terbukti pada `storage/logs/laravel.log`: `ResponseFactory.php on line 231`).
  - Diperbaiki menjadi `response()->stream($closure, 200, $this->sseHeaders())`; memulihkan 4 endpoint import: `/import/snapshot`, `/import/collateral`, `/import/office`, `/import/collateral-setup`.
- `DataImportController::sse()`: `ob_flush()` kini dijaga `ob_get_level() > 0` (warning tanpa buffer dikonversi Laravel menjadi `ErrorException` dan memutus stream di tengah import); seluruh payload SSE (termasuk di `AkadTypeController` & `ProductAkadMappingController`) memakai `JSON_INVALID_UTF8_SUBSTITUTE` agar baris non-UTF8 tidak membuat `json_encode()` gagal dan `JSON.parse` di client error.
- `.xls` (Excel 97-2003) tidak didukung OpenSpout v4 (hanya CSV/ODS/XLSX), tetapi lolos validasi `mimes:…,xls` dan berakhir "0 imported, 0 failed" tanpa penjelasan. Kini ditolak dengan pesan actionable di `DataImportController::runImport()` (ditangkap handler SSE) serta di `AkadTypeController::uploadImport()` dan `ProductAkadMappingController::uploadImport()` (event `status: error`).
- **Temuan lanjutan (belum diubah, butuh keputusan skema):** tabel `snapshot` tidak memiliki unique index pada `(loan_number, periode_snapshot)`, sehingga `Snapshot::upsert()` berperilaku sebagai INSERT biasa - upload ulang periode yang sama akan menduplikasi baris dan menggelembungkan EAD/CKPN. Perbaikan memerlukan migrasi unique index + pembersihan duplikat yang sudah ada.

### Security — Remediasi Audit OWASP Top 10
- **A01 Broken Access Control:**
  - `app/Http/Requests/Auth/LoginRequest.php` — login menolak user non-aktif (`active = 1` disertakan pada `Auth::attempt`).
  - `app/Http/Middleware/CheckPermission.php` — tolak user tanpa role atau non-aktif (defense-in-depth untuk sesi yang sudah terbentuk).
  - `app/Models/User.php` — implementasi `FilamentUser::canAccessPanel()`; panel `/admin` hanya untuk user aktif dengan `kode_role = ADMIN`.
  - `routes/web.php` — tambah middleware permission pada rute yang sebelumnya hanya `auth`:
    - `assessment` resource → `permission:calculation.view`; `assessment.bulk-destroy` → `permission:calculation.run`
    - `snapshot-history` index/show → `permission:financing.view`; export → `permission:reporting.export`
    - `api/product-akad/resolve` → `permission:financing.view`
    - `template/download/{type}` → `permission:financing.import`
- **A05 Security Misconfiguration:**
  - `app/Http/Middleware/SetSecurityHeaders.php` — tambah HSTS (`Strict-Transport-Security`, saat koneksi secure) & CSP (aktif di non-local), hapus header `X-XSS-Protection` yang deprecated.
- **A06 Vulnerable & Outdated Components (supply chain):**
  - `resources/views/components/sweetalert-global.blade.php` — pin SweetAlert2 ke versi tetap `11.26.25` + SRI `integrity="sha384-…"` + `crossorigin="anonymous"` (hash diverifikasi terhadap file `dist/sweetalert2.all.min.js`).

### Catatan
- Permission `assessment.*` pada `PermissionSeeder` menulis kolom legacy (`kode_permission`/`nama_permission`) sehingga `permissions.name` bernilai NULL; middleware rute assessment sengaja memakai `calculation.*` yang sudah valid agar tidak mengunci seluruh role, termasuk ADMIN.

### Audit — UI/UX anti-slop (implementasi perbaikan, batch 1)
- **Fixed** #1 (R-02): em dash diganti `-` di 16 lokasi pada 9 berkas.
- **Fixed** #2 (R-24): route profile terdaftar (`profile.edit/update/destroy`) → ProfileController; tautan ikon profil tidak lagi mati.
- **Fixed** #3 (R-26): tag `<script>` bersarang di `user/index.blade.php` ditutup; fungsi bulk delete kembali terdefinisi.
- **Fixed** #5 (R-17): presedensi operator di `reporting/risk-matrix.blade.php:105` diperbaiki (Total EAD tidak lagi salah 10^9).
- **Fixed** #6 (R-39): `role/form.blade.php` title dievaluasi dengan benar.
- **Fixed** #7 (R-36): klaim kepercayaan tanpa bukti di `layouts/guest.blade.php` dihapus.
- Menjalankan audit `antislop` mode **AFTER** atas seluruh 91 view Blade dengan basis aturan `antislop` (R-01..R-40) + `antislop-ui`.
- Hasil: `anti-slop/audit-001-2026-11-01.md` (45 temuan bernomor: 19 HIGH / 12 MEDIUM / 14 LOW).
- Temuan HIGH utama yang terverifikasi: em dash di 7 berkas (R-02), route `profile.edit` tidak terdaftar padahal dipakai di topbar (R-24), `<script>` bersarang yang mematikan aksi hapus di `user/index` (R-26), filter mati di `reporting/ckpn-summary` (R-26), bug presedensi `Total EAD` di `reporting/risk-matrix` (R-17), `<title>` literal di `role/form` (R-39), klaim tanpa bukti di `layouts/guest` (R-36), dua `<h1>` per halaman (R-39), kontras gagal WCAG AA (R-25), tap target <44px & drop zone non-keyboard (R-03/R-32), dan Chart.js render-blocking (R-40).
- Tidak ada berkas view yang dimodifikasi; perbaikan menunggu persetujuan nomor temuan dari user.

### Changed — Lokalisasi label UI ke Bahasa Indonesia
- Menerjemahkan seluruh copy UI berbahasa Inggris yang tersisa (judul halaman, header kolom tabel, label form, tombol/tautan, pesan empty-state & status) tanpa mengubah logika PHP/JS, route, nama variabel, atau struktur HTML.
- **Halaman import:** `import/{snapshot,office,collateral,collateral_setup}`, `akad-type/import`, `product-akad/{import,index,create,edit}` — label (`Format Wajib`, `Maks. Baris`, `Tarik & Lepas File di Sini`, `Mulai Unggah`, `Unduh Template`, `Progres Unggah`, `Log Impor`, dsb.) beserta pesan JS (`Silakan pilih file`, `Selesai!`, `Impor berhasil! …`).
- **Halaman form/detail:** `user/{index,create,edit,show}`, `office/{index,create}`, `collateral/{index,show,create,edit}`, `collateral-setup/{index,show,edit}`, `snapshot-history/{index,show}`, `bucket/{index,create,edit}`, `akad-type/{index,edit}`, `assessment/{index,create,edit,show}`, `role/{index,form}`, `reporting/{risk-matrix,aging-analysis,ckpn-summary}`, `product-akad/index`.
- **Panel auth:** `auth/login.blade.php`, `layouts/guest.blade.php`.
- **Profil (Breeze):** `profile/partials/{update-profile-information-form,update-password-form,delete-user-form}`, `profile/edit`.
- **Modul kalkulasi:** `calculation/modules/*` dan `calculation/pd_netflow_detail` — header `Office`→`Kantor`, `Count`→`Jumlah`, `Export XLSX`→`Ekspor XLSX`, `Export Detail Matriks`→`Ekspor Detail Matriks`, dsb.
- Istilah domain dipertahankan (PD, LGD, EAD, CKPN, PSAK 414, XLSX, WO, Recovery, Bucket, Role, Segmentasi). Konvensi: `Baki Debet`, `Tunggakan Pokok`, `Nomor Pembiayaan`, `Aktif/Nonaktif`, `Ubah`, `Ekspor`.
- Verifikasi grep (`>Office<|>Count<|>Name<|>Export |No .* found|>Password<|>Confirm Password<|Edit (Collateral|Setup|Bucket|Assessment|Role)`) → **No matches found**.

### Fixed — Anti-slop UI (batch 2)
- `auth/login.blade.php` — hapus latar `bg-gradient-to-br` → `bg-slate-900`; hapus keyframes & kelas `animate-slide-left/right/fade` beserta `style="animation-delay"`; turunkan `shadow-2xl` → `shadow-lg`; tagline fitur diterjemahkan.
- Hapus stripe `border-l-4` pada kartu sukses/info → kartu netral (`bg-emerald-50 border border-emerald-200` untuk sukses, `bg-white border border-gray-200` untuk info) di `import/*`, `akad-type/import`, `product-akad/import`, `role/{index,form}`.
- Normalisasi palet: `green-*` → `emerald-*` pada halaman import; kelas di luar palet (indigo/purple/orange/cyan/teal/pink/fuchsia/violet/rose/sky/lime) sudah bersih (verifikasi grep → **No matches**).
- Angka statistik pada halaman import diberi `font-mono` (audit-friendly).
- `snapshot-history/show.blade.php` — hapus blok dump "Raw Data (JSON)" agar detail snapshot hanya menampilkan field manusiawi.
- `auth/login.blade.php` — kotak Kredensial Demo: `Password:`→`Kata Sandi:`, `Analyst:`→`Analis:`.
- Verifikasi `php artisan view:cache` → **Blade templates cached successfully** (semua template kompilasi tanpa error sintaks).

### Changed — Unifikasi palet & sisa label Inggris (resources/views)
- **Palet:** seluruh token `green-<shade>` → `emerald-<shade>` dan `yellow-<shade>` → `amber-<shade>` di 23 berkas Blade (assessment, akad-type, bucket, collateral, collateral-setup, office, user, snapshot-history, role, reporting/*, calculation/{pd_netflow_detail,lgd_detail}, layouts/app, auth/verify-email, components/auth-session-status). Hanya kelas CSS; emerald/amber yang sudah ada tidak disentuh.
- **Label Inggris → Indonesia:** `reporting/ckpn-summary` (`Assessment Period`→`Periode Assessment`); `assessment/index` (title + dialog konfirmasi hapus); `calculation/modules/pd_netflow` (`Periods`→`Periode`); `calculation/modules/detail_pd_ead` (`Method`→`Metode`, `Periods`→`Periode`); `layouts/app` (nav `Assessment Periods`→`Periode Assessment`). Header `Method`→`Metode` juga diterapkan di `calculation/modules/detail_ckpn` dan `calculation/modules/lgd_cs` agar pemetaan konsisten.
- Verifikasi grep (`-(green|yellow)-[0-9]` dan `Assessment Periods|>Periods<|>Method<`) → **No matches found**.

---

## [Unreleased] — 2026-10-22

### Changed — Redesign Layout & Navigation
- `resources/views/layouts/app.blade.php` — Desain sidebar dibangun ulang sepenuhnya:
  - Struktur menu direorganisasi menjadi 4 section logis: **Menu**, **CKPN**, **Data & Laporan**, **Master Data**, **Administrasi**
  - Sidebar lebar 240px, sticky, full-height dengan scroll-area
  - Brand logo + nama aplikasi di header sidebar
  - User avatar + nama + email + tombol logout di footer sidebar
  - Topbar: hamburger mobile, page-title, user display name
  - Accordion collapsible untuk sub-menu Perhitungan CKPN, Akad & Produk, Agunan
  - Style: border-gray-200, nav-item-active blue-50/blue-600, tanpa shadow berlebih
  - Alpine.js `sidebarOpen` state untuk mobile drawer
  - Fade-in CSS animation untuk konten halaman
  - Flash messages (success, error, validation errors) dengan layout konsisten

### Changed — Redesign Dashboard
- `resources/views/dashboard/index.blade.php` — Dashboard dibangun ulang:
  - 5 KPI cards (Assessment, Loan Snapshot, Total EAD, Total CKPN, Pengguna) dengan border halus, warna icon per kategori
  - KPI nilai keuangan diformat satuan Miliar Rupiah (Rp X.XX M)
  - Quick Action Bar: 4 tombol aksi langsung ke workflow utama
  - Tabel Recent Assessments dengan status badge berwarna konsisten (border + bg-*-50)
  - Kelengkapan Data: progress bar usage type vs total fasilitas
  - Tabel Snapshot Pembiayaan: font-mono untuk loan_number dan nominal, data real dari DB

### Changed — AGENTS.md v3.0
- Menambahkan Section 0: **Mandatory AI Prompt Execution Protocol** (4 tahap: Understand → Auto-Skill → Plan & Execute → Log Change)
- Menambahkan Section 1: **Code Standards & Usability Principles** (Anti-Slop, Safety, Token Optimization)
- Dilarang keras menulis kode tanpa membaca kode yang ada terlebih dahulu
- Auto-skill selection dipetakan ke 7 kategori skill tersedia

### Changed — CLAUDE.md v3.0
- Ditulis ulang sepenuhnya dengan 7 section:
  - §1: Prompt Execution Workflow 4 tahap (Understand → Skill → Plan → Log)
  - §2: Code Quality Standards (PHP, Eloquent, Naming Convention, Decimal Precision, Safety Checklist)
  - §3: UI/UX Anti-Slop Rules & Design System Token wajib
  - §4: Agent Pipeline reference & Output JSON standard
  - §5: Documentation & Change Tracking Protocol (format CHANGELOG.md)
  - §6: Environment Setup
  - §7: Troubleshooting Quick Reference

### Added — CHANGELOG.md
- File ini dibuat untuk mendokumentasikan semua perubahan proyek secara kronologis
- Format Keep a Changelog, wajib diperbarui setiap prompt yang menghasilkan perubahan

---

## Riwayat Versi Sebelumnya

### [2.0] — 2026-10-22 (pre-session)
- AGENTS.md v2.0: Penambahan Token Efficiency Notes per agent
- CLAUDE.md v2.0: Awal guidelines development & agent keyword mapping
- Sistem keyword mapping agen (DataValidationAgent, EadCalculationAgent, dll.)

### [1.0] — 2026-09-30
- Arsitektur awal 14 agent CKPN Calculator
- Pipeline: DataValidation → EAD → Classification → Grouping → PD → LGD → CKPN → Consolidation → Reconciliation → Audit
- AGENTS.md v1.0: Spesifikasi lengkap semua agent berdasarkan TASK.md v3.1 + CKPN.md v3.0
