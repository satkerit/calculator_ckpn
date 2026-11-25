# Requirements Document

## Introduction

Berdasarkan audit Laravel best practices pada CKPN Calculator BPRS, ditemukan 10 isu yang perlu diperbaiki. Dokumen ini mendokumentasikan setiap temuan sebagai requirement dengan kriteria penerimaan yang terukur.

### Scope

- Perbaikan bug integritas data pada RoleController
- Penambahan type declarations pada model relationships
- Aktivasi preventLazyLoading di AppServiceProvider
- Refactor dependency injection (ganti app() helper)
- Ekstraksi Form Request classes dari inline validation
- Penambahan sort tie-breakers
- Deduplikasi query Dashboard
- Pembuatan model Financing
- Penambahan caching Dashboard

### Out of Scope

- Refactor arsitektur besar (service layer rewrite)
- Perubahan business logic CKPN/PD/LGD
- Migrasi database schema (hanya tambah model, bukan ubah tabel)
- Performance tuning query kalkulasi berat

## Requirements

### Requirement 1: RoleController destroy DB Transaction

Operasi multi-table (detach + delete) pada RoleController::destroy() WAJIB dibungkus `DB::transaction()` agar atomic. Saat ini `detach()` dan `delete()` dijalankan terpisah; bila `delete()` gagal setelah `detach()`, asosiasi role-permission hilang permanen.

**User Story:** Sebagai developer, saya ingin operasi detach + delete pada role dibungkus transaksi agar bila delete gagal, permissions tidak hilang.

#### Acceptance Criteria

- [ ] `RoleController::destroy()` membungkus detach + delete dalam `DB::transaction()`
- [ ] Bila delete gagal, permissions tetap terpasang (rollback)
- [ ] Test: simulasi delete gagal -> permissions tidak hilang

---

### Requirement 2: RoleController bulkDestroy detach pivot

`RoleController::bulkDestroy()` WAJIB melepas relasi `role_permission` untuk semua role yang akan dihapus sebelum eksekusi `delete()`, dalam satu `DB::transaction()`. Saat ini `Role::whereIn()->delete()` gagal pada foreign key constraint bila role masih punya permissions.

**User Story:** Sebagai admin, saya ingin bulk delete role tidak gagal karena FK constraint pada role_permission.

#### Acceptance Criteria

- [ ] bulkDestroy memanggil `detach()` untuk semua role dalam list sebelum delete
- [ ] Operasi dibungkus `DB::transaction()`
- [ ] Role yang masih punya users tetap diblokir (existing check tetap ada)
- [ ] Test: bulk delete role dengan permissions -> tidak error FK constraint

---

### Requirement 3: Eloquent relationship return types

Semua relationship method WAJIB mendeklarasikan concrete return type yang sesuai (`BelongsTo`, `HasMany`). Saat ini 5 model memiliki relationship tanpa return type declaration.

**User Story:** Sebagai developer, saya ingin semua relationship method memiliki return type agar static analysis dan IDE autocompletion bekerja.

#### Acceptance Criteria

- [ ] `User::role()` memiliki `: BelongsTo`
- [ ] `User::office()` memiliki `: BelongsTo`
- [ ] `Role::users()` memiliki `: HasMany`
- [ ] `AkadType::productMappings()` memiliki `: HasMany`
- [ ] `ProductAkadMapping::akadType()` memiliki `: BelongsTo`
- [ ] Import statement untuk type class ditambahkan di setiap file
- [ ] Tidak ada regression pada existing relationship usage

---

### Requirement 4: Activate Model preventLazyLoading

`Model::preventLazyLoading(! app()->isProduction())` WAJIB ditambahkan ke `AppServiceProvider::boot()` untuk mendeteksi N+1 query di environment non-production. Saat ini `boot()` kosong.

**User Story:** Sebagai developer, saya ingin N+1 query terdeteksi otomatis saat development agar tidak lolos ke production.

#### Acceptance Criteria

- [ ] `AppServiceProvider::boot()` memanggil `Model::preventLazyLoading(! app()->isProduction())`
- [ ] Environment production tetap mengizinkan lazy loading
- [ ] Environment development/testing melempar exception bila ada N+1
- [ ] Existing tests tidak break

---

### Requirement 5: Replace app() helper with dependency injection

Semua penggunaan `app()` untuk resolve service WAJIB diganti dengan constructor injection atau method injection. 6 lokasi menggunakan `app()` helper yang menyembunyikan dependency dan menyulitkan testing.

**User Story:** Sebagai developer, saya ingin dependency di-inject secara eksplisit agar mudah di-mock saat testing.

#### Acceptance Criteria

- [ ] `ClassificationController` menggunakan constructor injection untuk `BucketAssignmentService`
- [ ] `PdNetFlowController` menggunakan constructor injection untuk `PdNetflowCalculator` dan `PdNetflowService`
- [ ] `CalculationExportController` menggunakan method injection untuk service classes
- [ ] `FullCkpnExportController` menggunakan method injection untuk service classes
- [ ] `PdNetflowCalculator` menggunakan constructor injection untuk `BucketAssignmentService`
- [ ] `app()->runningUnitTests()` di BaseCalculationController tetap (bukan dependency resolution)
- [ ] Semua test existing tetap pass
- [ ] Tidak ada perubahan behavior

---

### Requirement 6: Form Request classes untuk CRUD controllers

Ekstraksi validation rules ke Form Request classes untuk operasi store/update pada controller CRUD utama. Saat ini hanya `ProfileUpdateRequest` yang ada; 8+ controller menggunakan inline `$request->validate()`. Bulk destroy validation tetap inline.

**User Story:** Sebagai developer, saya ingin validation rules dipisah ke Form Request classes agar reusable dan testable.

#### Acceptance Criteria

- [ ] `StoreUserRequest` / `UpdateUserRequest` untuk UserController
- [ ] `StoreRoleRequest` / `UpdateRoleRequest` untuk RoleController
- [ ] `StoreOfficeRequest` / `UpdateOfficeRequest` untuk OfficeController
- [ ] `StoreCollateralRequest` / `UpdateCollateralRequest` untuk CollateralController
- [ ] `StoreCollateralSetupRequest` / `UpdateCollateralSetupRequest` untuk CollateralSetupController
- [ ] `StoreAssessmentPeriodRequest` / `UpdateAssessmentPeriodRequest` untuk AssessmentPeriodController
- [ ] `StoreAkadTypeRequest` / `UpdateAkadTypeRequest` untuk AkadTypeController
- [ ] `StoreBucketDefinitionRequest` untuk BucketConfigurationController
- [ ] Controller method menerima Form Request sebagai parameter (type-hint)
- [ ] Controller tidak lagi memanggil `$request->validate()` untuk store/update
- [ ] Bulk destroy tetap menggunakan inline validation
- [ ] Semua test existing tetap pass

---

### Requirement 7: Sort order tie-breakers

Tambahkan `->orderByDesc('id')` sebagai tie-breaker pada query yang menggunakan `latest()` atau `orderByDesc()` tanpa unique secondary sort. Saat ini beberapa query menghasilkan urutan non-deterministik.

**User Story:** Sebagai developer, saya ingin urutan query konsisten antar eksekusi agar pagination dan limit tidak menghasilkan hasil yang berbeda.

#### Acceptance Criteria

- [ ] `DashboardController`: `AssessmentPeriod::latest()->limit(5)->get()` memiliki `->orderByDesc('id')`
- [ ] `BaseCalculationController`: `AssessmentPeriod::orderByDesc('assessment_date')->first()` memiliki `->orderByDesc('id')`
- [ ] `DashboardController`: `Snapshot::...->orderByDesc('periode_snapshot')->limit(8)->get()` memiliki `->orderByDesc('id')`
- [ ] Urutan hasil konsisten antar eksekusi
- [ ] Tidak ada regression pada test yang bergantung pada urutan

---

### Requirement 8: Dashboard query deduplication

`Snapshot::distinct('loan_number')->count('loan_number')` dieksekusi dua kali di `DashboardController::index()`. Query count yang sama tidak boleh dieksekusi dua kali; simpan hasil dalam variabel dan gunakan kembali.

**User Story:** Sebagai developer, saya ingin query yang sama tidak dieksekusi berulang agar Dashboard lebih cepat.

#### Acceptance Criteria

- [ ] `Snapshot::distinct('loan_number')->count('loan_number')` hanya dipanggil sekali
- [ ] `$classificationStats['total']` menggunakan variabel `$snapshotCount` yang sama
- [ ] Hasil Dashboard tetap identik

---

### Requirement 9: Financing model creation

Tabel `snapshot` memiliki foreign key ke `financing.loan_number`, tetapi tidak ada model `Financing` di `app/Models/`. Buat model `Financing` dengan `$fillable` sesuai kolom tabel dan relationships yang sesuai.

**User Story:** Sebagai developer, saya ingin model Financing tersedia agar bisa eager-load relasi dari Snapshot dan Collateral.

#### Acceptance Criteria

- [ ] `app/Models/Financing.php` dibuat dengan `$fillable` lengkap sesuai kolom tabel `financing`
- [ ] `Financing::snapshots()` return `HasMany` ke `Snapshot` via `loan_number`
- [ ] `Financing::collaterals()` return `HasMany` ke `Collateral` via `loan_number`
- [ ] `Financing::office()` return `BelongsTo` ke `Office` via `office_code`
- [ ] `Snapshot::financing()` return `BelongsTo` ke `Financing` via `loan_number`

---

### Requirement 10: Dashboard caching

Dashboard menjalankan 5+ query count setiap page load tanpa caching. Cache hasil query Dashboard dengan TTL 30 detik menggunakan `Cache::remember()` dengan cache key `dashboard:stats`.

**User Story:** Sebagai user, saya ingin Dashboard load cepat dengan hasil query di-cache selama 30 detik.

#### Acceptance Criteria

- [ ] Query count Dashboard dibungkus `Cache::remember()` dengan TTL 30 detik
- [ ] Cache key: `dashboard:stats`
- [ ] Data yang sering berubah (recentAssessments, recentSnapshots) tetap fresh (tidak di-cache atau TTL pendek)
- [ ] Cache otomatis invalidated bila ada perubahan data

---

## Priority Order

| Priority | Requirement | Effort |
|----------|------------|--------|
| P0 | Requirement 1: RoleController destroy transaction | 5 min |
| P0 | Requirement 2: RoleController bulkDestroy detach | 10 min |
| P1 | Requirement 3: Relationship return types | 10 min |
| P1 | Requirement 4: preventLazyLoading | 5 min |
| P1 | Requirement 5: Replace app() with DI | 30 min |
| P2 | Requirement 6: Form Request classes | 2 hours |
| P3 | Requirement 7: Sort tie-breakers | 15 min |
| P3 | Requirement 8: Dashboard query dedup | 5 min |
| P3 | Requirement 9: Financing model | 30 min |
| P4 | Requirement 10: Dashboard caching | 15 min |

## Glossary

- **N+1 Query**: Pola dimana satu query awal diikuti N query tambahan untuk setiap record, diselesaikan dengan eager loading `with()`
- **Mass Assignment**: Kerentanan keamanan dimana input user bisa mengisi kolom yang tidak diizinkan; dicegah dengan `$fillable`
- **Form Request**: Class terpisah yang mengenkapsulasi validation rules dan authorization logic
- **Lazy Loading**: Pattern loading relationship hanya saat diakses; menyebabkan N+1 bila di-loop
- **DI (Dependency Injection)**: Pattern dimana dependency di-inject ke class via constructor/method, bukan di-resolve manual dengan `app()`
- **Tie-breaker**: Secondary sort column yang menjamin urutan deterministik bila primary sort column memiliki nilai duplikat
