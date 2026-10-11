# Product Akad Mapping Module

## Overview
Modul untuk mapping `product_code` (numerik, misal 03/10/13/72) ke `akad_code` (kode master numerik: 06=Murabahah, 13=Multijasa, 03=Musyarakah, 10=IMBT) dan `ead_rule_type` untuk strategi perhitungan EAD.

## Database
- **Tabel:** `product_akad_mappings`
- **Foreign key:** `akad_code` → `akad_types.kode_akad`
- **Index:** `product_code` (unique), `akad_code`

## Service Layer
`App\Services\ProductAkadMappingService`
- `resolveAkadCode(string $productCode): string` — bulk-cached lookup (fallback: '06' = Murabahah)
- `resolveEadRuleType(string $productCode): string` — bulk-cached lookup (fallback: 'MURABAHAH')
- `invalidateCache()` — call setelah insert/update mapping
- Cache TTL: 24 jam

## Usage

### 1. Tambah mapping via UI
```
/product-akad → Index (list)
/product-akad/create → Form tambah
/product-akad/{id}/edit → Form edit
```

### 2. Resolve via API
```bash
GET /api/product-akad/resolve?product_code=03
# Response: {"product_code":"03","akad_code":"03","ead_rule_type":"MUSYARAKAH"}
```

### 3. Programmatic
```php
use App\Services\ProductAkadMappingService;

$akadCode = ProductAkadMappingService::resolveAkadCode('03'); // "03"
$eadRule = ProductAkadMappingService::resolveEadRuleType('10'); // "IMBT"
```

## Integration dengan EAD Calculation
`EadStrategyFactory` di-key dengan **kode master numerik** akad:
```php
// Kode master: 06=Murabahah, 13=Multijasa, 03=Musyarakah, 10=IMBT
$strategy = EadStrategyFactory::createByAkad('03'); // → MusyarakahStrategy
```

Alur resolve: `product_code` → `resolveAkadCode()` → kode master numerik → `EadStrategyFactory::createByAkad()`.

> **Catatan penting:** Sebelumnya `EadStrategyFactory` di-key dengan string `'MURA','MJA','MUSY','IMBT'` sementara `resolveAkadCode()` mengembalikan kode numerik, sehingga lookup gagal dan semua akad jatuh ke MurabahahStrategy (bug EAD Musyarakah/IMBT). Key string lama sudah **dihapus** — kini hanya memakai kode numerik `06/13/03/10`.

## Seed Data
Default mapping (sesuaikan dengan data master bank):
- 03 → 03 (Musyarakah)
- 06 → 06 (Murabahah)
- 10 → 10 (IMBT)
- 13 → 13 (Multijasa)

Run: `php artisan db:seed --class=ProductAkadMappingSeeder`

## Maintenance
- **Cache invalidation:** otomatis via controller setelah CRUD
- **Manual flush:** `ProductAkadMappingService::invalidateCache()`
- **Audit:** tambahkan mapping baru via UI saat produk baru diperkenalkan

## QA Checklist
- [x] Migration + seed berhasil
- [x] Resolve product_code 03/10/99 (fallback) correct
- [x] EAD calculation pakai mapping
- [x] Cache invalidation setelah update
- [ ] UI CRUD test manual (tambah/edit/hapus)
- [ ] Mapping produk real dari data master bank (sesuaikan seed)
