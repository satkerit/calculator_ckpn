# Product Akad Mapping Module

## Overview
Modul untuk mapping `product_code` (numerik, misal 03/10/55/72) ke `akad_code` (MURA/MUSY/IMBT/MJA) dan `ead_rule_type` untuk strategi perhitungan EAD.

## Database
- **Tabel:** `product_akad_mappings`
- **Foreign key:** `akad_code` → `akad_types.kode_akad`
- **Index:** `product_code` (unique), `akad_code`

## Service Layer
`App\Services\ProductAkadMappingService`
- `resolveAkadCode(string $productCode): string` — bulk-cached lookup (fallback: 'MURA')
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
# Response: {"product_code":"03","akad_code":"MURA","ead_rule_type":"MURABAHAH"}
```

### 3. Programmatic
```php
use App\Services\ProductAkadMappingService;

$akadCode = ProductAkadMappingService::resolveAkadCode('03'); // "MURA"
$eadRule = ProductAkadMappingService::resolveEadRuleType('10'); // "MUSYARAKAH"
```

## Integration dengan EAD Calculation
`EadStrategyFactory::create()` sekarang baca mapping:
```php
// Sebelum: hardcode string akad
$strategy = EadStrategyFactory::create('MURA');

// Sesudah: resolve dari product_code numerik
$strategy = EadStrategyFactory::create($snapshot['product_code']); // "03" → MURA → MurabahahStrategy
```

## Seed Data
Default mapping (6 rows):
- 03 → MURA (Murabahah)
- 06 → MJA (Multijasa)
- 10 → MUSY (Musyarakah)
- 13 → IMBT
- 55 → MURA (default)
- 72 → MURA (default)

Run: `php artisan db:seed --class=ProductAkadMappingSeeder`

## Maintenance
- **Cache invalidation:** otomatis via controller setelah CRUD
- **Manual flush:** `ProductAkadMappingService::invalidateCache()`
- **Audit:** tambahkan mapping baru via UI saat produk baru diperkenalkan

## QA Checklist
- [x] Migration + seed berhasil
- [x] Resolve product_code 03/10/99 (fallback) correct
- [x] EAD calculation pakai mapping (6869 records processed)
- [x] Cache invalidation setelah update
- [ ] UI CRUD test manual (tambah/edit/hapus)
- [ ] Mapping produk real dari data master bank (sesuaikan seed)
