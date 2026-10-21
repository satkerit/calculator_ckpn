<?php

namespace Tests\Feature;

use App\Models\AssessmentPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LgdWeightedPageTest extends TestCase
{
    use RefreshDatabase;

    private function createPeriod(User $user): AssessmentPeriod
    {
        return AssessmentPeriod::create([
            'period_code' => '2026-09',
            'period_name' => 'September 2026',
            'fiscal_month' => 9,
            'fiscal_year' => 2026,
            'assessment_date' => '2026-09-30',
            'status' => AssessmentPeriod::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);
    }

    public function testLgdWeightedPageRenders(): void
    {
        $user = User::factory()->create();
        $this->createPeriod($user);

        $this->actingAs($user)
            ->get('/calculation/module/lgd-weighted')
            ->assertOk()
            ->assertSee('LGD Weighted')
            ->assertSee('September 2026');
    }

    /**
     * ER menyimpan eksposur di total_write_off/total_recovery, CS memakai
     * total_exposure_wo/total_shortfall. Regresi: kolom ER pernah dibaca dari
     * kolom CS sehingga WO/Recovery/Loss ER tampil kosong (0).
     */
    public function testErExposureAndRecoveryAreReadFromErColumns(): void
    {
        $user = User::factory()->create();
        $period = $this->createPeriod($user);

        $common = [
            'assessment_period_id' => $period->id,
            'group_key' => '01|MURABAHAH|MODAL_KERJA',
            'combo_segmentation_key' => 'OFFICE|AKAD|USAGE',
            'office_code' => '01',
            'akad_code' => 'MURABAHAH',
            'usage_code' => 'MODAL_KERJA',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('stage_lgd_results')->insert($common + [
            'method' => 'ER',
            'recovery_rate' => 0.4,
            'total_write_off' => 1_000,
            'total_recovery' => 400,
            'years_analyzed' => 5,
            'lgd_calculated' => 0.6,
        ]);

        DB::table('stage_lgd_results')->insert($common + [
            'method' => 'CS',
            'total_exposure_wo' => 500,
            'total_shortfall' => 100,
            'account_count' => 2,
            'lgd_calculated' => 0.2,
        ]);

        $response = $this->actingAs($user)->get('/calculation/module/lgd-weighted');

        $response->assertOk()
            // WO ER, Recovery ER, Loss ER, lalu Baki CS & Shortfall CS.
            ->assertSee('1.000,00')
            ->assertSee('400,00')
            ->assertSee('600,00')
            ->assertSee('500,00')
            ->assertSee('100,00')
            // Total WO = 1.000 + 500; Total LGD = 600 + 100; rate = 700/1.500.
            ->assertSee('1.500,00')
            ->assertSee('700,00')
            ->assertSee('46,67%');
    }
}
