<?php

use App\Services\PdNetflow\PdNetflowCalculator;
use Illuminate\Support\Facades\DB;

// Get latest period
$periodId = DB::table('assessment_periods')->latest('id')->value('id') ?? 1;

// Run calculator
$calculator = app(PdNetflowCalculator::class);
$calculator->calculate($periodId, '01|06|1', 12);

echo "Calculation complete. Check results:\n";
echo "Bucket snapshots: " . DB::table('pd_netflow_bucket_snapshots')->count() . "\n";
echo "Transitions: " . DB::table('pd_netflow_transitions')->count() . "\n";
echo "Compound flows: " . DB::table('pd_netflow_compound_flows')->count() . "\n";
