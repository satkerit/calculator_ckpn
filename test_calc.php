<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$calc = app(App\Services\PdNetflow\PdNetflowCalculator::class);
$period = App\Models\AssessmentPeriod::orderByDesc('assessment_date')->first();
echo 'Period: ' . $period->assessment_date . ' (ID: ' . $period->id . ')' . PHP_EOL;

// Cek group keys dari stage_classifications
$groups = DB::table('stage_classifications')
    ->where('assessment_period_id', $period->id)
    ->where('classification', 'COLLECTIVE')
    ->selectRaw("CONCAT(office_code, '|', akad_code, '|', usage_code) as gk")
    ->distinct()
    ->pluck('gk');

echo 'Distinct groups: ' . json_encode($groups) . PHP_EOL;

foreach ($groups as $gk) {
    echo "Calculating for group: $gk ... ";
    $calc->calculate($period->id, $gk);
    echo "Done." . PHP_EOL;
    
    // Check compound flows in DB
    $compounds = DB::table('pd_netflow_compound_flows')
        ->where('assessment_period_id', $period->id)
        ->where('group_key', $gk)
        ->get();
    echo "  Total compound rows in DB: " . $compounds->count() . PHP_EOL;
    $avgPd = $compounds->avg('compound_pd');
    echo "  Avg compound_pd: " . $avgPd . PHP_EOL;
}

