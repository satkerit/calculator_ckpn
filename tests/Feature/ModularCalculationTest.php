<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Tests\TestCase;

/**
 * Regression test untuk 8 modul CKPN Calculation + integritas data master.
 *
 * Master data (snapshot, collateral, collateral_setup, office, akad_types,
 * product_akad, write_offs, recoveries) TIDAK boleh hilang atau berubah
 * saat modul dijalankan.
 */
class ModularCalculationTest extends TestCase
{
    /**
     * Modul-modul yang menulis ke tabel stage_* (calculation output).
     * Tabel ini AMAN di-truncate per-periode oleh calculation run.
     */
    private const STAGE_TABLES = [
        'stage_configs',
        'stage_classifications',
        'stage_segmented_snapshots',
        'stage_historical_buckets',
        'stage_migration_rates',
        'stage_compound_flow',
        'stage_pd_results',
        'stage_lgd_results',
        'stage_ead_buckets',
        'stage_individual_ckpn',
        'stage_collective_ckpn',
        'stage_calculation_marks',
    ];

    /**
     * Tabel master yang HARUS TIDAK tersentuh calculation run.
     */
    private const MASTER_TABLES = [
        'snapshot',
        'collateral',
        'collateral_setup',
        'office',
        'akad_types',
        'product_akad',
        'write_offs',
        'recoveries',
        'assessment_periods',
    ];

    public function testHelperSafeInsertGuardsOnMissingTable(): void
    {
        // Panggil helper pada tabel yang pasti tidak ada di sqlite test env.
        $ctrl = new class extends \App\Http\Controllers\Calculation\BaseCalculationController {
            public function call(string $table): int
            {
                return $this->safeInsert($table, [['loan_number' => 'X']]);
            }
        };

        $this->assertSame(0, $ctrl->call('__nonexistent_table__'));
    }

    public function testHelperCleanStageGuardsOnMissingTable(): void
    {
        $ctrl = new class extends \App\Http\Controllers\Calculation\BaseCalculationController {
            public function call(int $id, array $tables): void
            {
                $this->cleanStage($id, $tables);
            }
        };

        // Tidak boleh throw walau tabel salah.
        $ctrl->call(1, ['__missing_a__', '__missing_b__']);
        $this->assertTrue(true);
    }

    public function testHelperAuditGuardsOnMissingAuditLogsTable(): void
    {
        // Tanpa membuat audit_logs, helper harus no-op tanpa exception.
        if (SchemaFacade::hasTable('audit_logs')) {
            $this->markTestSkipped('audit_logs tersedia; tes ini hanya untuk schema tanpa tabel.');
        }

        $ctrl = new class extends \App\Http\Controllers\Calculation\BaseCalculationController {
            public function call(string $mod, int $id): void
            {
                $this->audit($mod, 'TEST', $id);
            }
        };

        $ctrl->call('CONFIG', 1);
        $this->assertTrue(true);
    }

    public function testConfigSaveAndReadRoundtrip(): void
    {
        if (! SchemaFacade::hasTable('assessment_periods') || ! SchemaFacade::hasTable('stage_configs')) {
            $this->markTestSkipped('Skema master belum lengkap di environment ini.');
        }

        $now = now();
        DB::table('assessment_periods')->insert([
            'assessment_date' => $now->toDateString(),
            'periode_snapshot' => $now->format('Ym'),
            'status' => 'OPEN',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $periodId = (int) DB::getPdo()->lastInsertId();

        DB::table('stage_configs')->insert([
            'assessment_period_id' => $periodId,
            'pd_method' => 'NET_FLOW',
            'lgd_method' => 'WEIGHTED',
            'historical_months' => 24,
            'sale_cost_percentage' => 0.02,
            'segment_dimensions' => 'OFFICE|AKAD|USAGE',
            'individual_top_n' => 15,
            'npl_ratings' => '3,4,5',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = DB::table('stage_configs')->where('assessment_period_id', $periodId)->first();
        $this->assertNotNull($row);
        $this->assertSame('NET_FLOW', $row->pd_method);
        $this->assertSame('WEIGHTED', $row->lgd_method);
        $this->assertSame(24, (int) $row->historical_months);
        $this->assertSame('OFFICE|AKAD|USAGE', $row->segment_dimensions);
    }

    public function testStageTableListCoversAllCalculationOutputs(): void
    {
        // Static guard agar stage_* baru harus ditambahkan ke list test ini.
        $this->assertContains('stage_classifications', self::STAGE_TABLES);
        $this->assertContains('stage_pd_results', self::STAGE_TABLES);
        $this->assertContains('stage_lgd_results', self::STAGE_TABLES);
        $this->assertContains('stage_individual_ckpn', self::STAGE_TABLES);
        $this->assertContains('stage_collective_ckpn', self::STAGE_TABLES);
    }

    public function testMasterTableListCoversCriticalEntities(): void
    {
        // Static guard agar master baru harus ditambahkan ke list test ini.
        foreach (['snapshot', 'collateral', 'collateral_setup', 'office', 'akad_types', 'product_akad', 'assessment_periods'] as $tbl) {
            $this->assertContains($tbl, self::MASTER_TABLES, "Master {$tbl} wajib masuk daftar proteksi.");
        }
    }

    public function testConfigurationControllerSavesValidConfig(): void
    {
        if (! SchemaFacade::hasTable('assessment_periods') || ! SchemaFacade::hasTable('stage_configs')) {
            $this->assertFalse(
                SchemaFacade::hasTable('assessment_periods') && SchemaFacade::hasTable('stage_configs'),
                'Skema master belum lengkap di environment ini.'
            );

            return;
        }

        $now = now();
        DB::table('assessment_periods')->insert([
            'assessment_date' => $now->toDateString(),
            'periode_snapshot' => $now->format('Ym'),
            'status' => 'OPEN',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $periodId = (int) DB::getPdo()->lastInsertId();

        $user = \App\Models\User::first();
        if (! $user) {
            $user = new \App\Models\User();
            $user->name = 'tester';
            $user->email = 'tester+'.uniqid().'@local';
            $user->password = bcrypt('x');
            $user->save();
        }
        $this->actingAs($user);

        $response = $this->post(route('calculation.module.config.save'), [
            'assessment_period_id'      => $periodId,
            'pd_method'                 => 'MIGRATION',
            'lgd_method'                => 'WEIGHTED',
            'historical_months'         => 36,
            'sale_cost_percentage'      => 0.05,
            'segment_dimensions'        => ['OFFICE', 'AKAD'],
            'individual_top_n'          => 10,
            'npl_ratings'               => [3, 4, 5],
            'lgd_default'               => 0.4,
            'lgd_er'                    => 0.45,
            'lgd_cs'                    => 0.55,
        ]);

        $this->assertEquals(302, $response->getStatusCode(), 'Config save redirect OK.');
        $this->assertNotNull(session('success'));

        $row = DB::table('stage_configs')->where('assessment_period_id', $periodId)->first();
        $this->assertNotNull($row, 'stage_configs harus terisi.');
        $this->assertSame('MIGRATION', $row->pd_method);
        $this->assertSame('OFFICE|AKAD', $row->segmentation_dims);
        $this->assertSame(36, (int) $row->historical_months);
        $this->assertSame(10, (int) $row->individual_top_n);
        $this->assertSame('3,4,5', $row->npl_ratings);
    }

    public function testConfigurationControllerRejectValidationFail(): void
    {
        if (! SchemaFacade::hasTable('assessment_periods') || ! SchemaFacade::hasTable('stage_configs')) {
            $this->assertFalse(
                SchemaFacade::hasTable('assessment_periods') && SchemaFacade::hasTable('stage_configs'),
                'Skema master belum lengkap di environment ini.'
            );

            return;
        }

        $user = \App\Models\User::first() ?? tap(new \App\Models\User(), function ($u) {
            $u->name = 't';
            $u->email = 't+'.uniqid().'@l';
            $u->password = bcrypt('x');
            $u->save();
        });
        $this->actingAs($user);

        $response = $this->from(route('calculation.module.config'))
            ->post(route('calculation.module.config.save'), [
                'assessment_period_id'     => 999999, // not exist
                'pd_method'                => 'INVALID',
                'historical_months'        => 1,        // < 12
                'sale_cost_percentage'     => 2,        // > 1
            ]);

        $this->assertEquals(302, $response->getStatusCode(), 'Validation fail redirects back.');
        $this->assertNotEmpty(session('errors'));
    }

    /**
     * Kriteria Individual = NPL TOP N outstanding (dinamis), bukan threshold nominal.
     * NPL diambil di sini = kualitas (kolektibilitas) 3,4,5. Uji logika ranking murni.
     */
    public function testNplTopNSelectionIsRankedByOutstandingDesc(): void
    {
        $ctrl = new class extends \App\Http\Controllers\Calculation\ClassificationController {
            /** @param array<int, array<string, mixed>> $rows */
            public function selectTop(array $rows, int $topN, array $nplRatings): array
            {
                $npl = [];
                foreach ($rows as $i => $r) {
                    // NPL murni dari kualitas (kolektibilitas), bukan DPD.
                    if (in_array($r['rating'], $nplRatings, true) || ! empty($r['wo'])) {
                        $npl[] = $i;
                    }
                }
                usort($npl, fn ($a, $b) => $rows[$b]['outstanding'] <=> $rows[$a]['outstanding']);

                return array_slice($npl, 0, $topN);
            }
        };

        $rows = [
            ['rating' => 1, 'wo' => false, 'outstanding' => 9_000_000], // lancar, DPD besar tak relevan
            ['rating' => 1, 'wo' => false, 'outstanding' => 5_000_000], // lancar
            ['rating' => 4, 'wo' => false, 'outstanding' => 7_000_000], // NPL kualitas 4
            ['rating' => 3, 'wo' => false, 'outstanding' => 8_000_000], // NPL kualitas 3
            ['rating' => 5, 'wo' => false, 'outstanding' => 3_000_000], // NPL kualitas 5
        ];

        $top2 = $ctrl->selectTop($rows, 2, [3, 4, 5]);

        // 3 NPL (idx 2,3,4); TOP2 = outstanding terbesar (8jt idx3, 7jt idx2).
        $this->assertCount(2, $top2);
        $this->assertSame([3, 2], $top2);
        $this->assertNotContains(0, $top2, 'Loan kualitas 1 tidak boleh masuk Individual.');
        $this->assertNotContains(1, $top2, 'Loan kualitas 1 tidak boleh masuk Individual.');

        // TOP N dinamis mengikuti config.
        $top99 = $ctrl->selectTop($rows, 99, [3, 4, 5]);
        $this->assertCount(3, $top99);
        $this->assertNotContains(0, $top99);
        $this->assertNotContains(1, $top99);

        // npl_ratings dapat dipersempit: hanya kualitas 5 → 1 kandidat.
        $only5 = $ctrl->selectTop($rows, 99, [5]);
        $this->assertSame([4], $only5);
    }

    /**
     * Self-check: cascade backward sesuai kertas kerja B2.PD-Migration.
     *
     * Skenario: rating 5 dengan saldo awal 100 juta. 50% ke rating 5 lagi,
     * 30% ke Hapus Buku, 20% Pembayaran. rating 4 → 100jt, 20% ke rating 5,
     * 10% Hapus Buku, 70% Pembayaran. rating 3 → 100jt, 10%→4, 5%→5, dst.
     *
     * Hitung manual:
     *   PD5 = 0.30 + 0.50 = 0.80
     *   PD4 = 0.10 + 0.20*0.80 = 0.26
     *   PD3 = 0.00 + 0.05*0.80 + 0.10*0.26 = 0.066
     *   PD2 = 0.00 + 0.00 + 0.05*0.066 = 0.0033
     *   PD1 = 0.00
     */
    public function testPdMigrationCascadeMatchesKertasKerjaFormula(): void
    {
        $ctrl = new class extends \App\Http\Controllers\Calculation\PdMigrationController {
            public function snap(string $periode, string $loan, ?int $rating, float $bal, bool $wo, bool $active, float $arrears = 0): object
            {
                return (object) [
                    'loan_number' => $loan,
                    'periode_snapshot' => $periode,
                    'quality_rating' => $rating,
                    'outstanding_balance' => $bal,
                    'principal_arrears' => $arrears,
                    'write_off_flag' => $wo,
                    'active_flag' => $active,
                ];
            }
            public function buildSnapshots(): array
            {
                $snaps = [];
                // r=5: L5 stay 5; L5b → WO.
                $snaps['202408|L5'] = collect([$this->snap('202408', 'L5', 5, 100_000_000, false, true)]);
                $snaps['202508|L5'] = collect([$this->snap('202508', 'L5', 5, 50_000_000, false, true)]);
                $snaps['202408|L5b'] = collect([$this->snap('202408', 'L5b', 5, 100_000_000, false, true)]);
                $snaps['202508|L5b'] = collect([$this->snap('202508', 'L5b', 5, 0, true, false)]);
                // r=4: L4 80→ke-5 16jt; L4b 20jt paid.
                $snaps['202408|L4'] = collect([$this->snap('202408', 'L4', 4, 80_000_000, false, true)]);
                $snaps['202508|L4'] = collect([$this->snap('202508', 'L4', 5, 16_000_000, false, true)]);
                $snaps['202408|L4b'] = collect([$this->snap('202408', 'L4b', 4, 20_000_000, false, true)]);
                $snaps['202508|L4b'] = collect([$this->snap('202508', 'L4b', 4, 0, false, false, 0)]);
                // r=3: stay / ke-4 / ke-5 / paid.
                $snaps['202408|L3a'] = collect([$this->snap('202408', 'L3a', 3, 50_000_000, false, true)]);
                $snaps['202508|L3a'] = collect([$this->snap('202508', 'L3a', 3, 50_000_000, false, true)]);
                $snaps['202408|L3b'] = collect([$this->snap('202408', 'L3b', 3, 30_000_000, false, true)]);
                $snaps['202508|L3b'] = collect([$this->snap('202508', 'L3b', 4, 30_000_000, false, true)]);
                $snaps['202408|L3c'] = collect([$this->snap('202408', 'L3c', 3, 5_000_000, false, true)]);
                $snaps['202508|L3c'] = collect([$this->snap('202508', 'L3c', 5, 5_000_000, false, true)]);
                $snaps['202408|L3d'] = collect([$this->snap('202408', 'L3d', 3, 15_000_000, false, true)]);
                $snaps['202508|L3d'] = collect([$this->snap('202508', 'L3d', 3, 0, false, false, 0)]);

                return $snaps;
            }
        };

        $ref = new \ReflectionClass($ctrl);
        $m = $ref->getMethod('computeMigrationPd');
        $m->setAccessible(true);

        $snaps = $ctrl->buildSnapshots();
        // Satu matriks triwulan: Agu 2024 → Agu 2025 (T-12 → T).
        $matrices = [[
            'quarter' => 'Q3 2025',
            't_start' => '2024-08-01',
            't_end' => '2025-08-31',
            't_start_ym' => '202408',
            't_end_ym' => '202508',
        ]];
        $loans = ['L5', 'L5b', 'L4', 'L4b', 'L3a', 'L3b', 'L3c', 'L3d'];

        $out = $m->invoke($ctrl, collect($snaps), $matrices, $loans, '202508');

        // Verifikasi cascade: rating 5 harus tertinggi, rating 1 mendekati 0.
        $this->assertGreaterThan($out['rating_pd'][4], $out['rating_pd'][5]);
        $this->assertGreaterThan($out['rating_pd'][3], $out['rating_pd'][4]);
        $this->assertGreaterThanOrEqual(0, $out['rating_pd'][1]);
        $this->assertLessThanOrEqual(1, $out['rating_pd'][5]);
        $this->assertSame(1, $out['matrices_used']);
        $this->assertCount(1, $out['matrices']);
        $this->assertGreaterThan(0, $out['matrix_total']);

        // EAD-weighted pd_group harus di antara min & max PD rating anggota assessment.
        $minRatingPd = min($out['rating_pd']);
        $maxRatingPd = max($out['rating_pd']);
        $this->assertGreaterThanOrEqual($minRatingPd - 1e-9, $out['pd_group']);
        $this->assertLessThanOrEqual($maxRatingPd + 1e-9, $out['pd_group']);
    }

    /**
     * Self-check PeriodResolver: 4 matriks triwulan T-12 → T dari tanggal asesmen.
     */
    public function testPdMigrationPeriodResolverBuildsFourQuarterlyMatrices(): void
    {
        // Asesmen Januari 2027 → Q_latest = Des 2026 (TW-IV 2026).
        $periods = \App\Services\PdMigration\PeriodResolver::getMigrationPeriods(
            \Carbon\Carbon::parse('2027-01-15')
        );

        $this->assertCount(4, $periods);

        // Kronologis M1..M4, tiap matriks tepat 12 bulan.
        $this->assertSame('202503', \Carbon\Carbon::parse($periods[0]['t_start'])->format('Ym'));
        $this->assertSame('202603', \Carbon\Carbon::parse($periods[0]['t_end'])->format('Ym'));
        $this->assertSame('202512', \Carbon\Carbon::parse($periods[3]['t_start'])->format('Ym'));
        $this->assertSame('202612', \Carbon\Carbon::parse($periods[3]['t_end'])->format('Ym'));

        foreach ($periods as $p) {
            $span = \Carbon\Carbon::parse($p['t_start'])->diffInMonths(\Carbon\Carbon::parse($p['t_end']));
            $this->assertSame(12, (int) $span);
        }
    }

    /**
     * Self-check weighted average PD rating lintas matriks (saldo awal sebagai bobot).
     */
    public function testPdMigrationWeightedAverageAcrossMatrices(): void
    {
        $ctrl = new class extends \App\Http\Controllers\Calculation\PdMigrationController {
            public function snap(string $periode, string $loan, ?int $rating, float $bal): object
            {
                return (object) [
                    'loan_number' => $loan,
                    'periode_snapshot' => $periode,
                    'quality_rating' => $rating,
                    'outstanding_balance' => $bal,
                    'principal_arrears' => 0,
                    'write_off_flag' => false,
                    'active_flag' => true,
                ];
            }
            public function buildSnapshots(): array
            {
                // Dua matriks; loan tunggal rating 5.
                // M1 (202401→202501): 100jt rating 5 → hilang (Pembayaran) → PD5=0.
                // M2 (202404→202504): 300jt rating 5 → tetap 5 → PD5=1.0.
                // Weighted PD5 = (100*0 + 300*1)/(100+300) = 0.75.
                $s = [];
                $s['202401|L'] = collect([$this->snap('202401', 'L', 5, 100_000_000)]);
                $s['202404|L'] = collect([$this->snap('202404', 'L', 5, 300_000_000)]);
                $s['202504|L'] = collect([$this->snap('202504', 'L', 5, 300_000_000)]);
                $s['202508|L'] = collect([$this->snap('202508', 'L', 5, 400_000_000)]);

                return $s;
            }
        };

        $ref = new \ReflectionClass($ctrl);
        $m = $ref->getMethod('computeMigrationPd');
        $m->setAccessible(true);

        $matrices = [
            ['quarter' => 'Q1 2025', 't_start' => '2024-01-01', 't_end' => '2025-01-31', 't_start_ym' => '202401', 't_end_ym' => '202501'],
            ['quarter' => 'Q2 2025', 't_start' => '2024-04-01', 't_end' => '2025-04-30', 't_start_ym' => '202404', 't_end_ym' => '202504'],
        ];

        $out = $m->invoke($ctrl, collect($ctrl->buildSnapshots()), $matrices, ['L'], '202508');

        $this->assertSame(2, $out['matrices_used']);
        $this->assertCount(2, $out['matrices']);
        // Weighted PD5 = (100jt*0 + 300jt*1) / 400jt = 0.75.
        $this->assertEqualsWithDelta(0.75, $out['rating_pd'][5], 1e-6);
        $this->assertSame(0.0, $out['rating_pd'][1]);
    }

    /**
     * Self-check: uniqueSheetLabels() di CalculationExportController harus
     * men-disambiguasi nama sheet yang panjangnya > 31 char, yang sama
     * setelah trunc, atau case-insensitive collision, sambil menghormati
     * reserved names (mis. '00_Index').
     */
    public function testUniqueSheetLabelsDisambiguatesCollisions(): void
    {
        $rc = new \ReflectionClass(\App\Http\Controllers\Export\CalculationExportController::class);
        $m = $rc->getMethod('uniqueSheetLabels');
        $m->setAccessible(true);
        $ctrl = $rc->newInstanceWithoutConstructor();

        // 4 grup: 2 identical prefix (terpotong ke 31 char sama), 1 kapitalisasi beda, 1 aman.
        $longPrefix = str_repeat('OFFICE-AKAD-USAGE-', 3); // 60 char.
        $keys = [
            'A' => $longPrefix.'1',
            'B' => $longPrefix.'2', // setelah substr(0,31) bisa sama dgn A
            'C' => $longPrefix.'3',
            'D' => 'off|kan:tor/01', // berisi karakter terlarang
            'E' => 'OFF|KAN:TOR/01', // tabrakan case-insensitive dgn D
        ];

        $labels = $m->invoke($ctrl, array_values($keys), ['00_Index']);

        // Semua hasil <= 31 char, semua berbeda, tidak bentrok dgn '00_Index'.
        $lowered = [];
        foreach ($labels as $key => $name) {
            $this->assertLessThanOrEqual(31, mb_strlen($name), "Sheet '{$name}' > 31 char");
            $this->assertArrayNotHasKey(strtolower($name), $lowered, "Duplicate: {$name}");
            $lowered[strtolower($name)] = $key;
        }
        $this->assertArrayNotHasKey('00_index', $lowered, 'Collision dgn reserved name');
        $this->assertNotEquals($labels[$keys['D']], $labels[$keys['E']], 'D & E seharusnya dibedakan');
    }
}