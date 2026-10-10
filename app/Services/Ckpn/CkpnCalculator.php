<?php

namespace App\Services\Ckpn;

class CkpnCalculator
{
    /**
     * CKPN = Outstanding/Tunggakan Pokok - Net Recovery Value
     * Net Recovery Value = Nilai Likuidasi - (Nilai Likuidasi × % Biaya Penjualan)
     *
     * @param float $ead Outstanding/Tunggakan Pokok
     * @param float $totalLiquidityValue Sum collateral liquidity_value
     * @param float $saleCostPercentage % biaya penjualan (0-1)
     * @return float CKPN Individual value
     */
    public static function calculateIndividual(
        float $ead,
        float $totalLiquidityValue,
        float $saleCostPercentage = 0.0
    ): float {
        $saleCost = $totalLiquidityValue * $saleCostPercentage;
        $netRecoveryValue = $totalLiquidityValue - $saleCost;
        $ckpnIndividual = $ead - $netRecoveryValue;

        return max(0.0, round($ckpnIndividual, 2, PHP_ROUND_HALF_UP));
    }

    /**
     * CKPN Collective = PD × LGD × EAD
     *
     * @param float $pd Probability of Default (0-1)
     * @param float $lgd Loss Given Default (0-1)
     * @param float $ead Exposure at Default
     * @return float CKPN Collective value
     */
    public static function calculateCollective(
        float $pd,
        float $lgd,
        float $ead
    ): float {
        $ckpn = $pd * $lgd * $ead;

        return round($ckpn, 2, PHP_ROUND_HALF_UP);
    }
}
