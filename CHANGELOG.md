# CHANGELOG — CKPN Calculator BPRS

Format mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Setiap perubahan kode/konfigurasi signifikan WAJIB dicatat di sini.

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
