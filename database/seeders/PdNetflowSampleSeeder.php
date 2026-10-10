<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PdNetflowSampleSeeder extends Seeder
{
    public function run(): void
    {
        // Use existing assessment period atau buat baru
        $periodId = DB::table('assessment_periods')->latest('id')->value('id');

        if (!$periodId) {
            $userId = DB::table('users')->value('id') ?? 1;
            $periodId = DB::table('assessment_periods')->insertGetId([
                'period_code' => 'PD2021Q4',
                'period_name' => 'Q4 2021',
                'fiscal_month' => 12,
                'fiscal_year' => 2021,
                'assessment_date' => '2021-12-01',
                'status' => 'DRAFT',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $groupKey = '1|01|1'; // office|akad|usage
        $periods = [
            '2020-12-01', '2021-01-01', '2021-02-01', '2021-03-01',
            '2021-04-01', '2021-05-01', '2021-06-01', '2021-07-01',
        ];

        // Tabel 1: Bucket Snapshots (dari Excel row 12-25)
        $bucketData = [
            1 => [77620800000, 80034000000, 82811600000, 0, 0, 0, 0, 0],
            2 => [576400000, 847100000, 1601100000, 0, 0, 0, 0, 0],
            3 => [175800000, 385800000, 451200000, 0, 0, 0, 0, 0],
            4 => [530700000, 137500000, 319200000, 0, 0, 0, 0, 0],
            5 => [202500000, 418900000, 108900000, 0, 0, 0, 0, 0],
            6 => [118500000, 202500000, 358000000, 0, 0, 0, 0, 0],
            7 => [209200000, 103200000, 152400000, 0, 0, 0, 0, 0],
            8 => [115400000, 155800000, 92000000, 0, 0, 0, 0, 0],
            9 => [129600000, 112500000, 152800000, 0, 0, 0, 0, 0],
            10 => [165100000, 104900000, 67600000, 0, 0, 0, 0, 0],
            11 => [178200000, 126200000, 86700000, 0, 0, 0, 0, 0],
            12 => [95800000, 147100000, 124800000, 0, 0, 0, 0, 0],
            13 => [53600000, 81400000, 104300000, 0, 0, 0, 0, 0],
            14 => [62200000, 52500000, 60500000, 0, 0, 0, 0, 0],
        ];

        foreach ($bucketData as $bucketNo => $values) {
            foreach ($periods as $i => $period) {
                if ($values[$i] > 0) {
                    DB::table('pd_netflow_bucket_snapshots')->insert([
                        'assessment_period_id' => $periodId,
                        'group_key' => $groupKey,
                        'bucket_no' => $bucketNo,
                        'periode_snapshot' => $period,
                        'outstanding_balance' => $values[$i],
                        'account_count' => rand(10, 100),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Tabel 2: Transition Rates (dari Excel row 32-44)
        $transitionData = [
            [1, 2, [0, 0.010913, 0.020005, 0.022651, 0.007660, 0.034889, 0.032864, 0.023232]],
            [2, 3, [0, 0.669327, 0.532641, 0.622822, 0.758716, 0.942870, 0.553828, 0.555084]],
            [3, 4, [0, 0.782139, 0.827372, 0.845745, 0.823907, 0.732926, 0.760263, 0.766472]],
            [4, 5, [0, 0.789335, 0.792000, 0.776629, 0.828878, 0.714703, 0.788611, 1.029158]],
            [5, 6, [0, 1.000000, 0.854619, 0.912764, 0.860428, 0.956687, 0.817439, 0.804036]],
            [6, 7, [0, 0.870886, 0.752593, 0.860894, 0.846076, 0.795124, 0.841705, 0.765000]],
            [7, 8, [0, 0.744742, 0.891473, 0.856299, 0.864374, 0.850178, 0.838443, 0.870436]],
            [8, 9, [0, 0.974870, 0.980745, 0.771739, 0.832950, 0.798799, 0.851748, 0.911392]],
            [9, 10, [0, 0.809414, 0.600889, 0.783377, 0.833803, 0.895124, 0.808741, 0.812808]],
            [10, 11, [0, 0.764385, 0.826501, 0.954142, 0.832080, 0.841216, 0.840699, 0.796630]],
            [11, 12, [0, 0.825477, 0.988906, 0.807382, 0.810853, 0.714859, 0.815261, 0.921760]],
            [12, 13, [0, 0.849687, 0.709041, 0.713141, 0.780000, 0.908222, 0.772472, 0.751232]],
            [13, 14, [0, 0.979478, 0.743243, 0.804410, 0.902247, 0.871795, 0.884211, 0.996364]],
        ];

        foreach ($transitionData as [$from, $to, $rates]) {
            foreach ($periods as $i => $period) {
                if ($rates[$i] > 0) {
                    DB::table('pd_netflow_transitions')->insert([
                        'assessment_period_id' => $periodId,
                        'group_key' => $groupKey,
                        'from_bucket' => $from,
                        'to_bucket' => $to,
                        'periode' => $period,
                        'transition_rate' => $rates[$i],
                        'flow_count' => rand(5, 50),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Tabel 3: Compound Flow to Loss (dari Excel row 48-50)
        $compoundData = [
            [1, [0, 0.000984, 0.001723, 0.002587, 0.001242, 0.002727, 0.003095, 0.002365]],
            [2, [0, 0.083449, 0.090142, 0.086125, 0.114215, 0.162160, 0.078150, 0.094182]],
            [3, [0, 0.286689, 0.124676, 0.169236, 0.138282, 0.150538, 0.171985, 0.141108]],
        ];

        foreach ($compoundData as [$sourceBucket, $pds]) {
            foreach ($periods as $i => $period) {
                if ($pds[$i] > 0) {
                    DB::table('pd_netflow_compound_flows')->insert([
                        'assessment_period_id' => $periodId,
                        'group_key' => $groupKey,
                        'source_bucket' => $sourceBucket,
                        'periode' => $period,
                        'compound_pd' => $pds[$i],
                        'flow_path' => json_encode([$sourceBucket, 14]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info("✓ PD Netflow sample data seeded for period {$periodId}, group {$groupKey}");
    }
}
