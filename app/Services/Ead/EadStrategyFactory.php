<?php

namespace App\Services\Ead;

use App\Models\AkadType;
use App\Services\ProductAkadMappingService;

class EadStrategyFactory
{
    private static array $strategies = [
        // Kode strategi master akad_types: 06=Murabahah, 13=Multijasa, 03=Musyarakah, 10=IMBT
        '06' => MurabahahStrategy::class,
        '13' => MultijasaStrategy::class,
        '03' => MusyarakahStrategy::class,
        '10' => ImbtStrategy::class,
    ];

    private static array $configCache = [];

    /**
     * Create strategy dari product_code (numerik) via mapping → akad_code.
     */
    public static function create(string $productCode): EadStrategyInterface
    {
        $akadCode = ProductAkadMappingService::resolveAkadCode($productCode);
        return self::createByAkad($akadCode);
    }

    /**
     * Create strategy langsung dari akad_code, baca config dari manajemen akad.
     */
    public static function createByAkad(string $akadCode): EadStrategyInterface
    {
        // Load config dari database jika belum di-cache
        if (!isset(self::$configCache[$akadCode])) {
            self::$configCache[$akadCode] = AkadType::where('kode_akad', $akadCode)
                ->where('active', true)
                ->first();
        }

        $config = self::$configCache[$akadCode];
        $strategyClass = self::$strategies[$akadCode] ?? MurabahahStrategy::class;
        $strategy = new $strategyClass();

        // Inject config jika strategy support setConfig
        if ($config && method_exists($strategy, 'setConfig')) {
            $strategy->setConfig([
                'ead_rule_type' => $config->ead_rule_type,
                'requires_due_date' => $config->requires_due_date,
            ]);
        }

        return $strategy;
    }
}
