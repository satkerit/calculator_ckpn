<?php

namespace App\Services;

use App\Models\ProductAkadMapping;
use Illuminate\Support\Facades\Cache;

class ProductAkadMappingService
{
    private const CACHE_KEY = 'product_akad_mappings';
    private const CACHE_TTL = 86400; // 24 jam

    /**
     * Resolve product_code ke akad_code (cached bulk-load).
     * Fallback: numerik product_code → default strategy (MURA).
     */
    public static function resolveAkadCode(string $productCode): string
    {
        $mappings = self::getMappings();
        return $mappings[$productCode] ?? 'MURA';
    }

    /**
     * Resolve product_code ke EAD rule type.
     */
    public static function resolveEadRuleType(string $productCode): string
    {
        $ruleTypes = self::getEadRuleTypes();
        return $ruleTypes[$productCode] ?? 'MURABAHAH';
    }

    /**
     * Bulk-load cache: product_code => akad_code.
     */
    private static function getMappings(): array
    {
        return Cache::remember(self::CACHE_KEY . ':akad', self::CACHE_TTL, function () {
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
        return Cache::remember(self::CACHE_KEY . ':ead_rules', self::CACHE_TTL, function () {
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
