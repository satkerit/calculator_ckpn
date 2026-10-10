<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$ap = DB::table('assessment_periods')->orderByDesc('id')->first();
$assessmentDate = $ap->assessment_date; // e.g. 2026-09-30
$assessYm = str_replace('-', '', substr($assessmentDate, 0, 7)); // e.g. 202609

// Rentang 5 tahun ke belakang (60 bulan) dari assessment_date
$startYm = \Carbon\Carbon::parse($assessmentDate)->subMonths(60)->format('Ym');
echo "Assessment date: $assessmentDate (Ym=$assessYm)\n";
echo "Rentang 5 tahun WO: $startYm .. $assessYm\n";

// 1. Ambilsemua loan yang pernah WO dengan tglwo berada di rentang 5 tahun
//    tglwo di format yyyymmdd -> tglwo_ym = substr(tglwo, 0, 6)
$woQuery = DB::table('snapshot')
    ->where('write_off_flag', 1)
    ->whereNotNull('tglwo')
    ->where('tglwo', '<>', '')
    ->whereRaw("SUBSTRING(tglwo, 1, 6) >= ?", [$startYm])
    ->whereRaw("SUBSTRING(tglwo, 1, 6) <= ?", [$assessYm]);

$totalWoRows = $woQuery->count();
echo "Total baris snapshot WO di rentang: $totalWoRows\n";

// Group per loan_number untuk mendapatkan:
// - tglwo
// - outstanding awal WO (dari snapshot pada periode WO dijalankan/terdekat tglwo)
// - posisi outstanding pada periode assesment terakhir ($assessYm)
$loans = DB::table('snapshot')
    ->where('write_off_flag', 1)
    ->whereNotNull('tglwo')
    ->where('tglwo', '<>', '')
    ->whereRaw("SUBSTRING(tglwo, 1, 6) >= ?", [$startYm])
    ->whereRaw("SUBSTRING(tglwo, 1, 6) <= ?", [$assessYm])
    ->select('loan_number', 'tglwo', 'office_code', 'product_code', 'usage_type')
    ->distinct()
    ->get();

echo "Distinct loan_number WO di rentang 5 tahun: " . $loans->count() . "\n";

// Ambil posisi periode assesment terakhir (202609) untuk semua loan
$lastPeriodSnaps = DB::table('snapshot')
    ->where('periode_snapshot', $assessYm)
    ->pluck('outstanding_balance', 'loan_number');

echo "Snapshot periode terakhir ($assessYm) total record: " . $lastPeriodSnaps->count() . "\n";

$totalWoNominal = 0;
$totalRecoveryNominal = 0;
$lunasCount = 0;
$adaSisaCount = 0;

foreach ($loans as $l) {
    // Ambil outstanding saat WO dijalankan (snapshot dengan periode_snapshot = substr(tglwo, 0, 6)
    // atau snapshot pertama di mana write_off_flag=1)
    $woYm = substr($l->tglwo, 0, 6);
    $woSnap = DB::table('snapshot')
        ->where('loan_number', $l->loan_number)
        ->where('write_off_flag', 1)
        ->orderBy('periode_snapshot', 'asc')
        ->first();

    if (!$woSnap) continue;

    $woBalance = (float) $woSnap->outstanding_balance;

    // Cek posisi periode terakhir
    if (isset($lastPeriodSnaps[$l->loan_number])) {
        $lastBalance = (float) $lastPeriodSnaps[$l->loan_number];
        $recovery = max(0, $woBalance - $lastBalance);
        $adaSisaCount++;
    } else {
        // Debitur tidak ditemukan pada periode terakhir -> Lunas dari writeoff (Recovery 100%)
        $lastBalance = 0;
        $recovery = $woBalance;
        $lunasCount++;
    }

    $totalWoNominal += $woBalance;
    $totalRecoveryNominal += $recovery;
}

echo "\n== HASIL SIMULASI LOGIKA KERTAS KERJA ==";
echo "\nTotal WO Nominal (Outstanding Awal WO): " . number_format($totalWoNominal, 2);
echo "\nTotal Recovery Nominal: " . number_format($totalRecoveryNominal, 2);
$overallRr = $totalWoNominal > 0 ? ($totalRecoveryNominal / $totalWoNominal) : 0;
$overallLgd = 1 - $overallRr;
echo "\nOverall Recovery Rate: " . number_format($overallRr * 100, 2) . "%";
echo "\nOverall LGD ER (1 - RR): " . number_format($overallLgd * 100, 2) . "%";
echo "\nDebitur Lunas / Hilang di periode akhir (100% Recovery): $lunasCount";
echo "\nDebitur masih ada posisi sisa di periode akhir: $adaSisaCount\n";
