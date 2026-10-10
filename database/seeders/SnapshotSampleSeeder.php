<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SnapshotSampleSeeder extends Seeder
{
    public function run(): void
    {
        // Periods: Des-20 s/d Feb-21 (3 bulan)
        $periods = ['2020-12-01', '2021-01-01', '2021-02-01'];
        $loanNumbers = [];
        for ($i = 1; $i <= 50; $i++) {
            $loanNumbers[] = 'LOAN' . str_pad($i, 6, '0', STR_PAD_LEFT);
        }
        
        // Generate snapshot per periode, assign bucket & amount
        $snapshot = [];
        foreach ($periods as $idx => $periode) {
            foreach ($loanNumbers as $loanIdx => $loanNumber) {
                // Assign bucket berdasarkan loan index & periode
                $bucketNo = ($loanIdx % 14) + 1;
                $baseAmount = 100000000 * ($loanIdx + 1);
                
                // Vary outstanding per periode
                $factor = 1 + (($idx - 1) * 0.05);
                $outstanding = max(0, intval($baseAmount * $factor));
                
                $snapshot[] = [
                    'loan_number' => $loanNumber,
                    'office_code' => '01',
                    'akad_code' => '06',
                    'usage_code' => '1',
                    'product_code' => '06',
                    'bucket_no' => $bucketNo,
                    'outstanding_balance' => $outstanding,
                    'principal_arrears' => $outstanding * 0.1,
                    'periode_snapshot' => $periode,
                    'active_flag' => true,
                    'write_off_flag' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('snapshot')->insert($snapshot);
    }
}
