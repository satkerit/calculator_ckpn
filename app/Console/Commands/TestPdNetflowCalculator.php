<?php

namespace App\Console\Commands;

use App\Services\PdNetflow\PdNetflowCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestPdNetflowCalculator extends Command
{
    protected $signature = 'pd:test';
    protected $description = 'Test PD Netflow Calculator';

    public function handle()
    {
        $periodId = 20; // Use test period
        
        $this->info("Testing calculator for group 01|06|1, period {$periodId}");
        
        $calculator = app(PdNetflowCalculator::class);
        $calculator->calculate($periodId, '01|06|1');
        
        $buckets = DB::table('pd_netflow_bucket_snapshots')->count();
        $transitions = DB::table('pd_netflow_transitions')->count();
        $compounds = DB::table('pd_netflow_compound_flows')->count();
        
        $this->info("Results:");
        $this->line("- Bucket snapshots: {$buckets}");
        $this->line("- Transitions: {$transitions}");
        $this->line("- Compound flows: {$compounds}");
    }
}
