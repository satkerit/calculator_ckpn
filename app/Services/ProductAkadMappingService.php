<?php

namespace App\Services;

use App\Models\ProductAkadMapping;
use Illuminate\Support\Facades\Cache;

class ProductAkadMappingService
{
    private const CACHE_KEY = 'product_akad_mappings';
    private const CACHE_TTL = 86400; // 24 jam

    /**
     * Memo per proses/request: Cache::remember tetap melakukan 1 query `cache`
     * setiap pemanggilan (store database). Pemetaan ini dipanggil per baris
     * (klasifikasi, LGD, export), sehingga tanpa memo satu run bisa memicu
     * ribuan SELECT ke tabel cache.
     */
    private static ?array $akadMemo = null;
    private static ?array $eadRuleMemo = null;

    /**
     * Resolve product_code ke akad_code (cached bulk-load).
     * Fallback: numerik product_code → default strategy (MURA).
     */
    public static function resolveAkadCode(string $productCode): string
    {
        $mappings = self::getMappings();
        if (!array_key_exists($productCode, $mappings)) {
            // Fallback ke Murabahah ('06') HARUS tercatat agar EAD tidak salah tanpa jejak.
            \Illuminate\Support\Facades\Log::warning('ProductAkadMapping: fallback ke default Murabahah (06)', [
                'product_code' => $productCode,
            ]);
        }

        return $mappings[$productCode] ?? '06'; // default: Murabahah
    }

    /**
     * Resolve product_code ke EAD rule type.
     */
    public static function resolveEadRuleType(string $productCode): string
    {
        $ruleTypes = self::getEadRuleTypes();
        if (!array_key_exists($productCode, $ruleTypes)) {
            // Fallback ke rule MURABAHAH HARUS tercatat agar EAD tidak salah tanpa jejak.
            \Illuminate\Support\Facades\Log::warning('ProductAkadMapping: fallback EAD rule ke default MURABAHAH', [
                'product_code' => $productCode,
            ]);
        }

        return $ruleTypes[$productCode] ?? 'MURABAHAH';
    }

    /**
     * Bulk-load cache: product_code => akad_code.
     */
    private static function getMappings(): array
    {
        return self::$akadMemo ??= Cache::remember(self::CACHE_KEY . ':akad', self::CACHE_TTL, function () {
            return ProductAkadMapping::where('active', true)
                ->pluck('akad_code', 'product_code')
                ->toArray();
        });
    }

    /**
     * Bulk-load cache: product_code => ead_rule_type.
     */
    private static function getEadRuleTypes(): array
    {
        return self::$eadRuleMemo ??= Cache::remember(self::CACHE_KEY . ':ead_rules', self::CACHE_TTL, function () {
            return ProductAkadMapping::where('active', true)
                ->pluck('ead_rule_type', 'product_code')
                ->toArray();
        });
    }

    /**
     * Invalidate cache (call setelah insert/update mapping).
     */
    public static function invalidateCache(): void
    {
        self::$akadMemo = null;
        self::$eadRuleMemo = null;
        Cache::forget(self::CACHE_KEY . ':akad');
        Cache::forget(self::CACHE_KEY . ':ead_rules');
    }

    /**
     * Get all mappings untuk display table.
     */
    public static function all()
    {
        return ProductAkadMapping::where('active', true)->orderBy('product_code')->get();
    }
}
