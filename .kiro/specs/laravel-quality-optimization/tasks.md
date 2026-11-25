# Implementation Plan: Laravel Quality Optimization

## Overview

Tasks ini mengimplementasikan 10 requirements dari audit Laravel best practices CKPN Calculator BPRS. Eksekusi sesuai priority order: P0 dulu (bug integritas data), lalu P1 (best practices), P2-P4 (improvements).

## Tasks

- [ ] 1. Wrap RoleController::destroy() in DB::transaction
  - [ ] 1.1. Add `use Illuminate\Support\Facades\DB;` import to RoleController bila belum ada
  - [ ] 1.2. Bungkus `$role->permissions()->detach()` dan `$role->delete()` dalam `DB::transaction(function () use ($role) { ... })` di method destroy()
  - [ ] 1.3. Run `php artisan test` to verify no regression

- [ ] 2. Fix RoleController::bulkDestroy() to detach pivot before delete
  - [ ] 2.1. Replace `Role::whereIn('id', $validated['ids'])->delete()` dengan transactional block: ambil semua role, detach permissions per role, lalu delete dalam `DB::transaction()`
  - [ ] 2.2. Pastikan check users tetap ada sebelum transaksi (tidak dihapus)
  - [ ] 2.3. Run `php artisan test` to verify no regression

- [ ] 3. Add Eloquent relationship return types to 5 models
  - [ ] 3.1. User.php: add `use ...BelongsTo;`, change `role()` and `office()` to `: BelongsTo`
  - [ ] 3.2. Role.php: add `use ...HasMany;`, change `users()` to `: HasMany`
  - [ ] 3.3. AkadType.php: add `use ...HasMany;`, change `productMappings()` to `: HasMany`
  - [ ] 3.4. ProductAkadMapping.php: add `use ...BelongsTo;`, change `akadType()` to `: BelongsTo`
  - [ ] 3.5. Run `php artisan test` to verify no regression

- [ ] 4. Activate Model::preventLazyLoading in AppServiceProvider
  - [ ] 4.1. Add `use Illuminate\Database\Eloquent\Model;` to AppServiceProvider
  - [ ] 4.2. Add `Model::preventLazyLoading(! app()->isProduction());` to boot() method
  - [ ] 4.3. Run `php artisan test`; bila LazyLoadingViolationException muncul, fix dengan `with()`

- [ ] 5. Replace app() helper with dependency injection
  - [ ] 5.1. ClassificationController: add constructor with `BucketAssignmentService $bucketService`, replace `app()` call with `$this->bucketService`
  - [ ] 5.2. PdNetFlowController: add constructor with `PdNetflowCalculator $calculator` dan `PdNetflowService $service`, replace `app()` calls
  - [ ] 5.3. CalculationExportController: add method injection pada methods yang membutuhkan, replace `app()` calls
  - [ ] 5.4. FullCkpnExportController: same method injection pattern as 5.3
  - [ ] 5.5. PdNetflowCalculator: add constructor with `BucketAssignmentService $bucketService`, replace `app()` call
  - [ ] 5.6. Verify `app()->runningUnitTests()` di BaseCalculationController tetap (bukan DI)
  - [ ] 5.7. Run `php artisan test` to verify no regression

- [ ] 6. Create Form Request classes for CRUD controllers
  - [ ] 6.1. Create StoreUserRequest dan UpdateUserRequest, pindahkan rules dari UserController, update signatures
  - [ ] 6.2. Create StoreRoleRequest dan UpdateRoleRequest, pindahkan rules dari RoleController, update signatures
  - [ ] 6.3. Create StoreOfficeRequest dan UpdateOfficeRequest, pindahkan rules dari OfficeController, update signatures
  - [ ] 6.4. Create StoreCollateralRequest dan UpdateCollateralRequest, pindahkan rules dari CollateralController, update signatures
  - [ ] 6.5. Create StoreCollateralSetupRequest dan UpdateCollateralSetupRequest, pindahkan rules dari CollateralSetupController, update signatures
  - [ ] 6.6. Create StoreAssessmentPeriodRequest dan UpdateAssessmentPeriodRequest, pindahkan rules dari AssessmentPeriodController, update signatures
  - [ ] 6.7. Create StoreAkadTypeRequest dan UpdateAkadTypeRequest, pindahkan rules dari AkadTypeController, update signatures
  - [ ] 6.8. Create StoreBucketDefinitionRequest, pindahkan rules dari BucketConfigurationController, update signatures
  - [ ] 6.9. Verify: setiap Form Request punya `authorize(): bool { return true; }`
  - [ ] 6.10. Run `php artisan test` to verify no regression

- [ ] 7. Add sort order tie-breakers
  - [ ] 7.1. DashboardController: add `->orderByDesc('id')` after `AssessmentPeriod::latest()->limit(5)->get()`
  - [ ] 7.2. DashboardController: add `->orderByDesc('id')` after `->orderByDesc('periode_snapshot')` on recentSnapshots query
  - [ ] 7.3. BaseCalculationController: add `->orderByDesc('id')` after `->orderByDesc('assessment_date')` in resolvePeriod()
  - [ ] 7.4. Run `php artisan test` to verify no regression

- [ ] 8. Deduplicate Dashboard query
  - [ ] 8.1. Audit DashboardController::index() untuk duplikasi `Snapshot::distinct('loan_number')->count('loan_number')`
  - [ ] 8.2. Bila ditemukan duplikasi, ganti referensi kedua dengan variabel `$snapshotCount`
  - [ ] 8.3. Run `php artisan test` to verify no regression

- [ ] 9. Create Financing model with relationships
  - [ ] 9.1. Baca migration `2026_09_30_111022_create_financing_table.php` dan `2026_09_30_150000_add_classification_to_financing_table.php` untuk daftar kolom lengkap
  - [ ] 9.2. Create `app/Models/Financing.php` dengan `$table`, `$fillable`, `$casts`, dan 3 relationships: `snapshots(): HasMany`, `collaterals(): HasMany`, `office(): BelongsTo`
  - [ ] 9.3. Add `financing(): BelongsTo` relationship to `app/Models/Snapshot.php`
  - [ ] 9.4. Run `php artisan test` to verify no regression

- [ ] 10. Add Dashboard caching with Cache::remember
  - [ ] 10.1. Wrap 5 Dashboard query counts dalam `Cache::remember('dashboard:stats', 30, ...)`
  - [ ] 10.2. Keep data yang sering berubah tetap fresh tanpa caching
  - [ ] 10.3. Create `app/Observers/AssessmentPeriodObserver.php` dengan `created()` dan `deleted()` yang memanggil `Cache::forget('dashboard:stats')`
  - [ ] 10.4. Register observer di AppServiceProvider::boot()
  - [ ] 10.5. Run `php artisan test` to verify no regression

## Task Dependency Graph

```json
{
  "waves": [
    {
      "id": 0,
      "tasks": ["1.1", "1.2", "1.3", "2.1", "2.2", "2.3"],
      "parallel": true,
      "description": "P0: Fix RoleController integrity bugs"
    },
    {
      "id": 1,
      "tasks": ["3.1", "3.2", "3.3", "3.4", "3.5", "4.1", "4.2", "4.3", "5.1", "5.2", "5.3", "5.4", "5.5", "5.6", "5.7"],
      "parallel": true,
      "description": "P1: Best practices (relationship types, preventLazyLoading, DI)"
    },
    {
      "id": 2,
      "tasks": ["6.1", "6.2", "6.3", "6.4", "6.5", "6.6", "6.7", "6.8", "6.9", "6.10"],
      "parallel": false,
      "description": "P2: Form Request classes (sequential, large task)"
    },
    {
      "id": 3,
      "tasks": ["7.1", "7.2", "7.3", "7.4", "8.1", "8.2", "8.3"],
      "parallel": true,
      "description": "P3: Dashboard fixes (sort tie-breakers + query dedup, same file)"
    },
    {
      "id": 4,
      "tasks": ["9.1", "9.2", "9.3", "9.4"],
      "parallel": false,
      "description": "P3: Create Financing model (depends on Task 3 for Snapshot relationship)"
    },
    {
      "id": 5,
      "tasks": ["10.1", "10.2", "10.3", "10.4", "10.5"],
      "parallel": false,
      "description": "P4: Dashboard caching (depends on Task 7+8, same file)"
    }
  ],
  "dependencies": {
    "9.2": ["3.1"],
    "9.3": ["3.1"],
    "7.1": ["8.1"],
    "7.2": ["8.1"],
    "10.1": ["7.1", "7.2", "8.1", "8.2"]
  }
}
```
## Notes

- Eksekusi sesuai priority: Task 1-2 (P0) dulu, lalu Task 3-5 (P1), Task 6 (P2), Task 7-9 (P3), Task 10 (P4)
- Task 4 bisa trigger N+1 yang sebelumnya tersembunyi — siapkan `with()` fix bila muncul
- Task 6 adalah yang paling besar (15 Form Request classes) — pertimbangkan untuk batch create
- Setiap task diakhiri dengan `php artisan test` untuk memastikan tidak ada regression
- Command: `php artisan test` atau `composer run test` (config:clear + test)
