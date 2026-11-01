# CHANGELOG — CKPN Calculator BPRS

Format mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Setiap perubahan kode/konfigurasi signifikan WAJIB dicatat di sini.

---

## [Unreleased] — 2026-11-01

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
