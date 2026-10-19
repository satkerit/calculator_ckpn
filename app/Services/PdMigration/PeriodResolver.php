<?php

namespace App\Services\PdMigration;

use Carbon\Carbon;

/**
 * Penentu matriks triwulan PD Migration berdasarkan tanggal asesmen dan window historis.
 *
 * Aturan (PSAK 414 / kertas kerja B2.PD-Migration):
 * - Q_latest = triwulan penuh terakhir sebelum tanggal asesmen.
 * - Jumlah matriks = floor(historical_months / 3), minimum 1, default 4.
 * - T_end_M(N)   = endOfMonth(Q_latest)
 * - T_end_M(i)   = T_end_M(N) - (N - i) * 3 bulan, untuk i = 1..N
 * - T_start_M(i) = T_end_M(i) - 12 bulan (horizon transisi tetap 12 bulan per matriks)
 *
 * Contoh historical_months=36 (3 tahun, 12 matriks): asesmen 2027-01-15
 *   M1: 2023-12-01 → 2024-12-31  …  M12: 2025-12-01 → 2026-12-31
 */
class PeriodResolver
{
    /**
     * @param  int  $numMatrices  Jumlah matriks triwulan; 0 = default 4.
     * @return list<array{quarter:string, t_start:string, t_end:string}>
     *         Daftar matriks terurut M1..MN (kronologis naik).
     */
    public static function getMigrationPeriods(Carbon $assessmentDate, int $numMatrices = 4): array
    {
        $n = max(1, $numMatrices);
        $latest = self::resolveLatestQuarterEnd($assessmentDate);
        $periods = [];

        for ($i = 0; $i < $n; $i++) {
            $endDate = $latest->copy()->subMonths($i * 3);
            $startDate = $endDate->copy()->subYear()->startOfMonth();

            $periods[] = [
                'quarter' => sprintf('Q%d %d', self::quarterOf($endDate), $endDate->year),
                't_start' => $startDate->toDateString(),
                't_end'   => $endDate->copy()->endOfMonth()->toDateString(),
            ];
        }

        // Loop di atas menghasilkan MN..M1 → balik ke kronologis M1..MN.
        return array_reverse($periods);
    }

    /**
     * Q_latest: triwulan penuh terakhir sebelum bulan asesmen.
     */
    private static function resolveLatestQuarterEnd(Carbon $assessmentDate): Carbon
    {
        $year = $assessmentDate->year;
        $month = $assessmentDate->month;

        if ($month >= 1 && $month <= 3) {
            return Carbon::create($year - 1, 12, 1)->startOfMonth();
        }
        if ($month >= 4 && $month <= 6) {
            return Carbon::create($year, 3, 1)->startOfMonth();
        }
        if ($month >= 7 && $month <= 9) {
            return Carbon::create($year, 6, 1)->startOfMonth();
        }

        return Carbon::create($year, 9, 1)->startOfMonth();
    }

    private static function quarterOf(Carbon $date): int
    {
        return (int) ceil($date->month / 3);
    }
}
