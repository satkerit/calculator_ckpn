# Design Document

## Overview

Dokumen ini menjelaskan solusi teknis untuk 10 requirement yang didefinisikan pada requirements.md. Perubahan difokuskan pada controller layer (transaction wrapping, DI refactor, Form Request extraction), model layer (return type declarations, new Financing model), provider layer (preventLazyLoading activation), dan view/query layer (sort tie-breakers, query dedup, caching). Tidak ada perubahan arsitektur besar; semua perubahan bersifat additive atau corrective terhadap pola yang sudah ada.

## Architecture

Perubahan diorganisir per layer dengan dependency graph berikut:

```
REQ-01 (RoleController destroy)     ── standalone
REQ-02 (RoleController bulkDestroy) ── standalone
REQ-03 (Relationship types)         ── standalone
REQ-04 (preventLazyLoading)         ── standalone, run tests after
REQ-05 (DI refactor)                ── standalone
REQ-06 (Form Requests)              ── standalone
REQ-07 (Sort tie-breakers)          ── REQ-08 (same file: DashboardController)
REQ-08 (Query dedup)                ── REQ-07 (same file)
REQ-09 (Financing model)            ── REQ-03 (Snapshot relationship)
REQ-10 (Dashboard caching)          ── REQ-07, REQ-08 (same file, build on top)
```

Eksekusi sesuai priority order dari requirements. Setiap requirement mandiri kecuali REQ-07/08/10 yang bekerja pada file yang sama (DashboardController) dan REQ-09 yang bergantung pada REQ-03 untuk Snapshot relationship.

## Components and Interfaces

### Requirement 1: RoleController destroy DB Transaction

**File:** `app/Http/Controllers/RoleController.php`

Bungkus operasi `detach()` dan `delete()` dalam `DB::transaction()`. Tidak ada perubahan signature method atau logic bisnis.

```php
public function destroy(Role $role): RedirectResponse
{
    if ($role->users()->count() > 0) {
        return redirect()->route('role.index')
            ->with('error', "Role '{$role->nama_role}' masih digunakan.");
    }

    $roleName = $role->nama_role;

    DB::transaction(function () use ($role) {
        $role->permissions()->detach();
        $role->delete();
    });

    return redirect()->route('role.index')
        ->with('success', "Role '{$roleName}' berhasil dihapus.");
}
```

Tambahkan `use Illuminate\Support\Facades\DB;` di imports bila belum ada.

### Requirement 2: RoleController bulkDestroy detach pivot

**File:** `app/Http/Controllers/RoleController.php`

Sebelum `delete()`, ambil semua role dalam list dan detach permissions dalam satu transaksi.

```php
public function bulkDestroy(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'ids' => 'required|array|min:1',
        'ids.*' => 'required|exists:roles,id'
    ]);

    $rolesWithUsers = Role::whereIn('id', $validated['ids'])->has('users')->get();

    if ($rolesWithUsers->isNotEmpty()) {
        $names = $rolesWithUsers->pluck('nama_role')->implode(', ');
        return redirect()->route('role.index')
            ->with('error', "Role berikut masih digunakan: {$names}");
    }

    DB::transaction(function () use ($validated) {
        $roles = Role::whereIn('id', $validated['ids'])->get();
        foreach ($roles as $role) {
            $role->permissions()->detach();
        }
        Role::whereIn('id', $validated['ids'])->delete();
    });

    $count = count($validated['ids']);
    return redirect()->route('role.index')
        ->with('success', "{$count} role berhasil dihapus.");
}
```

### Requirement 3: Eloquent relationship return types

**Files affected (5):** `User.php`, `Role.php`, `AkadType.php`, `ProductAkadMapping.php`

Perubahan minimal: tambah import statement dan return type declaration. Body method tidak berubah.

```php
// User.php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

public function role(): BelongsTo
{
    return $this->belongsTo(Role::class, 'role_id');
}

public function office(): BelongsTo
{
    return $this->belongsTo(Office::class, 'office_id');
}
```

```php
// Role.php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function users(): HasMany
{
    return $this->hasMany(User::class);
}
```

```php
// AkadType.php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function productMappings(): HasMany
{
    return $this->hasMany(ProductAkadMapping::class, 'akad_code', 'kode_akad');
}
```

```php
// ProductAkadMapping.php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

public function akadType(): BelongsTo
{
    return $this->belongsTo(AkadType::class, 'akad_code', 'kode_akad');
}
```

### Requirement 4: Activate Model preventLazyLoading

**File:** `app/Providers/AppServiceProvider.php`

```php
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    Model::preventLazyLoading(! app()->isProduction());
}
```

### Requirement 5: Replace app() helper with dependency injection

**Files affected (5):**

**`ClassificationController.php`** — constructor injection:
```php
use App\Services\Bucket\BucketAssignmentService;

class ClassificationController extends BaseCalculationController
{
    public function __construct(
        private BucketAssignmentService $bucketService
    ) {}
    // Di run(): ganti app(BucketAssignmentService::class) dengan $this->bucketService
}
```

**`PdNetFlowController.php`** — constructor injection:
```php
class PdNetFlowController extends BaseCalculationController
{
    public function __construct(
        private PdNetflowCalculator $calculator,
        private PdNetflowService $service
    ) {}
    // Di run(): hapus app() calls, gunakan $this->calculator dan $this->service
}
```

**`CalculationExportController.php`** — method injection:
```php
public function pdNetflowBacktest(
    AssessmentPeriod $assessmentPeriod,
    PdNetflowService $service,
    PdNetflowCalculator $calculator
): StreamedResponse {
    // Body tetap sama, service dari parameter
}
```

**`FullCkpnExportController.php`** — pola sama dengan method injection.

**`PdNetflowCalculator.php`** — constructor injection:
```php
class PdNetflowCalculator
{
    public function __construct(
        private BucketAssignmentService $bucketService
    ) {}
    // Ganti app(BucketAssignmentService::class) dengan $this->bucketService
}
```

Catatan: `app()->runningUnitTests()` di BaseCalculationController BUKAN dependency resolution dan tetap dipertahankan.

### Requirement 6: Form Request classes untuk CRUD controllers

**Directory:** `app/Http/Requests/`

Buat 15 Form Request classes. Setiap class berisi validation rules yang dipindahkan dari controller `store()`/`update()`. Controller method menerima Form Request sebagai type-hint.

**Contoh pola:**
```php
// app/Http/Requests/StoreUserRequest.php
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'office_id' => 'nullable|exists:offices,id',
            'active' => 'boolean',
        ];
    }
}
```

**Controller change pattern:**
```php
// Before:
public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([...]);
}

// After:
public function store(StoreUserRequest $request): RedirectResponse
{
    $validated = $request->validated();
}
```

Rules diambil persis dari existing `$request->validate()` di setiap controller. Tidak ada penambahan atau pengurangan rule. Update requests menggunakan unique rule dengan ignore ID dari route parameter.

### Requirement 7: Sort order tie-breakers

**Files affected (2):** `DashboardController.php`, `BaseCalculationController.php`

```php
// DashboardController.php
// Before:
$recentAssessments = AssessmentPeriod::latest()->limit(5)->get();
// After:
$recentAssessments = AssessmentPeriod::latest()->orderByDesc('id')->limit(5)->get();

// Before:
$recentSnapshots = Snapshot::with('office:office_code,office_name')
    ->orderByDesc('periode_snapshot')->limit(8)->get();
// After:
$recentSnapshots = Snapshot::with('office:office_code,office_name')
    ->orderByDesc('periode_snapshot')->orderByDesc('id')->limit(8)->get();

// BaseCalculationController.php
// Before:
$period = AssessmentPeriod::orderByDesc('assessment_date')->first();
// After:
$period = AssessmentPeriod::orderByDesc('assessment_date')->orderByDesc('id')->first();
```

### Requirement 8: Dashboard query deduplication

**File:** `app/Http/Controllers/DashboardController.php`

Verifikasi bahwa `Snapshot::distinct('loan_number')->count('loan_number')` hanya dipanggil sekali dan `$classificationStats['total']` menggunakan variabel `$snapshotCount` yang sama. Bila sudah terpenuhi, tandai sebagai done.

### Requirement 10: Dashboard caching

**File:** `app/Http/Controllers/DashboardController.php`

Bungkus query count dan aggregate dalam `Cache::remember()`:

```php
use Illuminate\Support\Facades\Cache;

$stats = Cache::remember('dashboard:stats', 30, function () {
    return [
        'assessmentCount' => AssessmentPeriod::count(),
        'snapshotCount' => Snapshot::distinct('loan_number')->count('loan_number'),
        'userCount' => User::count(),
        'assessmentByStatus' => AssessmentPeriod::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->get()->keyBy('status'),
        'classificationStats' => [
            'with_usage_type' => Snapshot::whereNotNull('usage_type')
                ->distinct('loan_number')->count('loan_number'),
            'total' => Snapshot::distinct('loan_number')->count('loan_number'),
        ],
    ];
});
```

Data yang sering berubah (recentAssessments, recentSnapshots, latestAssessment, assessmentTrend, EAD/CKPN) tetap fresh tanpa caching.

Cache invalidation via model observer:
```php
// app/Observers/AssessmentPeriodObserver.php
class AssessmentPeriodObserver
{
    public function created(AssessmentPeriod $model): void
    {
        Cache::forget('dashboard:stats');
    }
    public function deleted(AssessmentPeriod $model): void
    {
        Cache::forget('dashboard:stats');
    }
}
```

Registrasi di `AppServiceProvider::boot()`:
```php
AssessmentPeriod::observe(AssessmentPeriodObserver::class);
```

## Data Models

### Requirement 9: Financing model creation

**New file:** `app/Models/Financing.php`

Kolom tabel `financing` dari migration `2026_09_30_111022_create_financing_table.php`:
- `loan_number` (string, primary key)
- `cif_number` (string)
- `name` (string, nullable)
- `product_code` (string)
- `office_code` (string, FK ke offices)
- `effective_date` (date)
- `expired_date` (date)
- `active_flag` (boolean)
- `write_off_flag` (boolean)
- `classification` (enum, nullable — added by migration `2026_09_30_150000`)

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Financing extends Model
{
    protected $table = 'financing';

    protected $fillable = [
        'loan_number',
        'cif_number',
        'name',
        'product_code',
        'office_code',
        'effective_date',
        'expired_date',
        'active_flag',
        'write_off_flag',
        'classification',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'expired_date' => 'date',
        'active_flag' => 'boolean',
        'write_off_flag' => 'boolean',
    ];

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class, 'loan_number', 'loan_number');
    }

    public function collaterals(): HasMany
    {
        return $this->hasMany(Collateral::class, 'loan_number', 'loan_number');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_code', 'office_code');
    }
}
```

**File:** `app/Models/Snapshot.php` — tambahkan:
```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

public function financing(): BelongsTo
{
    return $this->belongsTo(Financing::class, 'loan_number', 'loan_number');
}
```

## Correctness Properties

### Property 1: Transaction Atomicity

Transaksi database menjamin atomicity pada REQ-01/02. Bila salah satu operasi gagal, seluruh transaksi rollback. Tidak ada kondisi race antara detach dan delete.

**Validates: Requirements 1.0, 2.0**

### Property 2: Type Safety

Return type declaration (REQ-03) adalah contract compile-time. PHP memvalidasi bahwa method mengembalikan instance yang sesuai dengan declared type.

**Validates: Requirements 3.0**

### Property 3: Non-Production Guard

preventLazyLoading (REQ-04) hanya aktif di non-production. Production tetap berjalan normal tanpa exception.

**Validates: Requirements 4.0**

### Property 4: DI Transparency

Dependency injection via container (REQ-05) tidak mengubah runtime behavior. Laravel service container resolve dependency yang sama, hanya secara eksplisit.

**Validates: Requirements 5.0**

### Property 5: Rule Equivalence

Form Request validation rules (REQ-06) identik dengan inline validation yang dipindahkan. Tidak ada perubahan rule.

**Validates: Requirements 6.0**

### Property 6: Deterministic Ordering

Tie-breaker id (REQ-07) menjamin urutan deterministik karena id adalah unique primary key.

**Validates: Requirements 7.0**

### Property 7: Schema Compatibility

Model Financing (REQ-09) menggunakan custom foreign key (loan_number) yang sudah ada di schema. Tidak ada migrasi baru.

**Validates: Requirements 9.0**

### Property 8: Cache Freshness Bound

Cache TTL 30 detik (REQ-10) menjamin data tidak stale lebih dari 30 detik. Observer men-trigger invalidation immediate pada write.

**Validates: Requirements 10.0**

## Error Handling

- **REQ-01**: Bila `delete()` gagal dalam transaksi, `detach()` di-rollback. Role tetap memiliki permissions.
- **REQ-02**: Bila bulk delete gagal, seluruh operasi rollback. Tidak ada role yang kehilangan permissions.
- **REQ-04**: Bila preventLazyLoading trigger N+1 di test code, perbaiki dengan `with()` sebelum lanjut. Tidak suppress exception.
- **REQ-05**: Bila service tidak terdaftar di container, Laravel throw `BindingResolutionException`. Semua service sudah ada di container (auto-resolvable via constructor).
- **REQ-06**: Form Request authorization via `return true` karena authorization sudah ditangani oleh middleware `permission:*`.
- **REQ-10**: Bila cache store unavailable, `Cache::remember()` fall back ke callback execution (query tetap jalan, hanya tanpa cache).

## Testing Strategy

Setelah setiap requirement diimplementasi:

1. Jalankan `php artisan test` untuk memastikan tidak ada regression
2. REQ-04 khusus: bila N+1 muncul, perbaiki dengan `with()` sebelum lanjut ke requirement berikutnya
3. REQ-06: pastikan Form Request class menghasilkan validation error response yang sama dengan inline validation
4. REQ-09: test relationship `Financing::snapshots()` dan `Snapshot::financing()` dengan factory atau existing data
5. REQ-10: test bahwa cache key `dashboard:stats` ter-create dan ter-invalidate setelah model change

Command: `php artisan test` atau `composer run test` (config:clear + test).

