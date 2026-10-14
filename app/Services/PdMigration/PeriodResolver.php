<?php

namespace App\Services\PdMigration;

use Carbon\Carbon;

/**
 * Penentu 4 matriks triwulan PD Migration berdasarkan tanggal asesmen.
 *
 * Aturan (PSAK 414 / kertas kerja B2.PD-Migration):
 * - Q_latest = triwulan penuh terakhir sebelum tanggal asesmen.
 *   Jan-Mar  → TW-IV tahun sebelumnya, T_end = 31 Des (Y-1)
 *   Apr-Jun  → TW-I tahun Y,         T_end = 31 Mar (Y)
 *   Jul-Sep  → TW-II tahun Y,        T_end = 30 Jun (Y)
 *   Okt-Des  → TW-III tahun Y,       T_end = 30 Sep (Y)
 * - T_end_M4 = endOfMonth(Q_latest)
 * - T_end_M(i) = T_end_M4 - (4 - i) * 3 bulan, untuk i = 1..4
 * - T_start_M(i) = T_end_M(i) - 1 tahun (tepat 12 bulan)
 *
 * Contoh: asesmen 2027-01-15
 *   M1: 2025-03-01 → 2026-03-31
 *   M2: 2025-06-01 → 2026-06-30
 *   M3: 2025-09-01 → 2026-09-30
 *   M4: 2025-12-01 → 2026-12-31
 */
class PeriodResolver
{
    /**
     * @return list<array{quarter:string, t_start:string, t_end:string}>
     *         Daftar 4 matriks terurut M1..M4 (kronologis naik).
     */
    public static function getMigrationPeriods(Carbon $assessmentDate): array
    {
        $latest = self::resolveLatestQuarterEnd($assessmentDate);
        $periods = [];

        for ($i = 0; $i < 4; $i++) {
            $endDate = $latest->copy()->subMonths($i * 3);
            $startDate = $endDate->copy()->subYear()->startOfMonth();

            $periods[] = [
                'quarter' => sprintf('Q%d %d', self::quarterOf($endDate), $endDate->year),
                't_start' => $startDate->toDateString(),
                't_end'   => $endDate->copy()->endOfMonth()->toDateString(),
            ];
        }

        // Loop di atas menghasilkan M4..M1 → balik ke kronologis M1..M4.
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
