<?php

namespace Tests\Feature;

use App\Http\Controllers\Calculation\BaseCalculationController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Invalidasi hasil kalkulasi saat konfigurasi berubah.
 *
 * Aturan: segmentasi / historical window / metode PD berubah → hasil PD & CKPN
 * Kolektif tidak valid; segmentasi berubah → hasil LGD tidak valid.
 * Finalize CKPN diblokir sampai modul yang tidak valid dihitung ulang.
 */
class CalculationConfigInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private int $periodId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = $this->makeAdminUser();
        $this->actingAs($user);

        $this->periodId = $this->makePeriod($user);
        $this->seedConfig();
    }

    /** Hasil PD + LGD + Klasifikasi tersedia untuk periode uji. */
    private function seedResults(): void
    {
        $now = now();

        DB::table('stage_classifications')->insert([
            'assessment_period_id' => $this->periodId,
            'loan_number' => 'L001',
            'office_code' => '01',
            'akad_code' => '03',
            'usage_code' => 'MODAL',
            'ead_amount' => 1_000_000,
            'classification' => 'COLLECTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('stage_pd_results')->insert([
            'assessment_period_id' => $this->periodId,
            'group_key' => '01',
            'pd_calculated' => 0.05,
            'method' => 'NET_FLOW',
            'periods_analyzed' => 36,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('stage_lgd_results')->insert([
            'assessment_period_id' => $this->periodId,
            'group_key' => '01',
            'combo_segmentation_key' => 'OFFICE',
            'office_code' => '01',
            'method' => 'ER',
            'lgd_calculated' => 0.5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** Helper anonim: buka method protected BaseController utk asserting. */
    private function ctrl(): BaseCalculationController
    {
        return new class extends BaseCalculationController
        {
            public function stale(int $id): array
            {
                return $this->staleResultTypes($id);
            }

            public function mark(int $id, string $type, array $overrides = []): void
            {
                $this->markResults($id, $type, $overrides);
            }
        };
    }

    private function makeAdminUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'kode_role' => 'ADMIN',
            'nama_role' => 'Admin',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permId = DB::table('permissions')->insertGetId([
            'kode_permission' => 'calculation.run',
            'nama_permission' => 'Run Calculation',
            'name' => 'calculation.run',
            'display_name' => 'Run Calculation',
            'module' => 'CKPN_CALCULATION',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create(['role_id' => $roleId]);
    }

    private function makePeriod(User $user): int
    {
        return DB::table('assessment_periods')->insertGetId([
            'period_code' => '2026-09',
            'assessment_date' => '2026-09-30',
            'status' => 'REVIEW',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedConfig(): void
    {
        DB::table('stage_configs')->insert([
            'assessment_period_id' => $this->periodId,
            'pd_method' => 'NET_FLOW',
            'lgd_method' => 'WEIGHTED',
            'historical_months' => 36,
            'lgd_historical_months' => 60,
            'sale_cost_percentage' => 0.05,
            'segmentation_dims' => 'OFFICE',
            'individual_top_n' => 30,
            'npl_ratings' => '3,4,5',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** POST konfigurasi valid lewat route resmi Modul 1. */
    private function saveConfig(array $overrides = []): void
    {
        $payload = array_merge([
            'assessment_period_id' => $this->periodId,
            'pd_method' => 'NET_FLOW',
            'lgd_method' => 'WEIGHTED',
            'historical_months' => 36,
            'lgd_historical_months' => 60,
            'sale_cost_percentage' => 0.05,
            'segment_dimensions' => ['OFFICE'],
            'individual_top_n' => 30,
            'npl_ratings' => [3, 4, 5],
        ], $overrides);

        $response = $this->post(route('calculation.module.config.save'), $payload);

        $this->assertEquals(302, $response->getStatusCode(), 'Config save harus redirect.');
        $this->assertNotNull(session('success'));
    }

    public function test_results_without_mark_are_stale(): void
    {
        $this->seedResults();

        // Hasil ada tapi tanpa penanda (data lama / konfigurasi tak diketahui) → basi.
        $this->assertSame(['PD', 'LGD'], $this->ctrl()->stale($this->periodId));
    }

    public function test_marked_results_are_fresh_until_segmentation_changes(): void
    {
        $this->seedResults();

        $ctrl = $this->ctrl();
        $ctrl->mark($this->periodId, 'PD');
        $ctrl->mark($this->periodId, 'LGD');
        $this->assertSame([], $ctrl->stale($this->periodId), 'Setelah ditandai, hasil harus VALID.');

        // Segmentasi ulang (OFFICE → OFFICE|AKAD) membatalkan PD & LGD.
        $this->saveConfig(['segment_dimensions' => ['OFFICE', 'AKAD']]);
        $this->assertSame(['PD', 'LGD'], $ctrl->stale($this->periodId));

        $success = (string) session('success');
        $this->assertStringContainsString('wajib dihitung ulang', $success, 'Flash config save harus memberi tahu hasil jadi tidak valid.');
    }

    public function test_pd_method_change_invalidates_pd_but_not_lgd(): void
    {
        $this->seedResults();

        $ctrl = $this->ctrl();
        $ctrl->mark($this->periodId, 'PD');
        $ctrl->mark($this->periodId, 'LGD');

        // Ganti metode PD saja: LGD (hanya bergantung segmentasi) tetap sah.
        $this->saveConfig(['pd_method' => 'MIGRATION']);
        $this->assertSame(['PD'], $ctrl->stale($this->periodId));
    }

    public function test_historical_window_change_invalidates_pd_but_not_lgd(): void
    {
        $this->seedResults();

        $ctrl = $this->ctrl();
        $ctrl->mark($this->periodId, 'PD');
        $ctrl->mark($this->periodId, 'LGD');

        // Ganti window historis PD saja: LGD tidak memakai window ini → tetap sah.
        $this->saveConfig(['historical_months' => 24]);
        $this->assertSame(['PD'], $ctrl->stale($this->periodId));
    }

    public function test_unrelated_config_change_keeps_results_valid(): void
    {
        $this->seedResults();

        $ctrl = $this->ctrl();
        $ctrl->mark($this->periodId, 'PD');
        $ctrl->mark($this->periodId, 'LGD');

        // Perubahan di luar trigger (sale cost, TOP N, NPL ratings) tidak membatalkan.
        $this->saveConfig([
            'sale_cost_percentage' => 0.10,
            'individual_top_n' => 50,
            'npl_ratings' => [4, 5],
        ]);
        $this->assertSame([], $ctrl->stale($this->periodId));
    }

    public function test_finalize_blocked_when_config_changed_after_calculation(): void
    {
        $this->seedResults();

        // Hasil basi (tanpa penanda / konfigurasi berubah) → Finalize ditolak.
        $response = $this->post(route('calculation.module.detail_ckpn.finalize'), [
            'assessment_period_id' => $this->periodId,
        ]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertNotEmpty(session('error'), 'Finalize harus dibalas error saat hasil basi.');
        $this->assertStringContainsString('Hitung ulang', (string) session('error'));
        $this->assertSame(0, DB::table('stage_collective_ckpn')->count(), 'CKPN Kolektif tidak boleh ditulis saat basi.');
    }

    public function test_finalize_succeeds_when_fresh_and_marks_ckpn(): void
    {
        $this->seedResults();

        $ctrl = $this->ctrl();
        $ctrl->mark($this->periodId, 'PD');
        $ctrl->mark($this->periodId, 'LGD');

        $response = $this->post(route('calculation.module.detail_ckpn.finalize'), [
            'assessment_period_id' => $this->periodId,
        ]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertNull(session('error'), 'Finalize harus sukses saat semua hasil VALID.');
        $this->assertNotNull(session('success'));

        $this->assertSame(1, DB::table('stage_collective_ckpn')->count());

        // Hasil CKPN ikut ditandai agar perubahan konfigurasi berikutnya terdeteksi.
        $this->assertNotNull(
            DB::table('stage_calculation_marks')
                ->where('assessment_period_id', $this->periodId)
                ->where('result_type', 'CKPN')
                ->value('config_fingerprint')
        );

        $ckpn = DB::table('stage_collective_ckpn')->first();
        $this->assertSame('01', $ckpn->group_key);
        $this->assertEqualsWithDelta(0.05 * 0.5 * 1_000_000, (float) $ckpn->ckpn_kolektif, 0.01, 'CKPN Kolektif = PD × LGD × EAD.');
    }

    public function test_finalize_blocked_again_after_fresh_ckpn_then_config_change(): void
    {
        $this->seedResults();

        $ctrl = $this->ctrl();
        $ctrl->mark($this->periodId, 'PD');
        $ctrl->mark($this->periodId, 'LGD');

        $this->post(route('calculation.module.detail_ckpn.finalize'), [
            'assessment_period_id' => $this->periodId,
        ]);
        $this->assertSame(1, DB::table('stage_collective_ckpn')->count());

        // Segmentasi ulang setelah finalize → semua hasil (PD, LGD, CKPN) tidak valid.
        $this->saveConfig(['segment_dimensions' => ['OFFICE', 'USAGE']]);
        $this->assertSame(['PD', 'LGD', 'CKPN'], $ctrl->stale($this->periodId));

        $response = $this->post(route('calculation.module.detail_ckpn.finalize'), [
            'assessment_period_id' => $this->periodId,
        ]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertNotEmpty(session('error'), 'Finalize harus diblokir lagi setelah konfigurasi berubah.');
    }

    public function test_module_pages_show_and_hide_stale_banner(): void
    {
        $this->seedResults();

        // Tanpa penanda → banner "TIDAK VALID" tampil di halaman PD, LGD, dan CKPN.
        $this->get(route('calculation.module.pd_netflow', ['assessment_period_id' => $this->periodId]))
            ->assertOk()
            ->assertSee('TIDAK VALID');
        $this->get(route('calculation.module.lgd_er', ['assessment_period_id' => $this->periodId]))
            ->assertOk()
            ->assertSee('TIDAK VALID');
        $this->get(route('calculation.module.lgd_cs', ['assessment_period_id' => $this->periodId]))
            ->assertOk()
            ->assertSee('TIDAK VALID');
        $this->get(route('calculation.module.detail_ckpn', ['assessment_period_id' => $this->periodId]))
            ->assertOk()
            ->assertSee('TIDAK VALID');

        // Setelah ditandai dengan konfigurasi berlaku → banner hilang.
        $ctrl = $this->ctrl();
        $ctrl->mark($this->periodId, 'PD');
        $ctrl->mark($this->periodId, 'LGD');

        $this->get(route('calculation.module.pd_netflow', ['assessment_period_id' => $this->periodId]))
            ->assertOk()
            ->assertDontSee('TIDAK VALID');
        $this->get(route('calculation.module.lgd_cs', ['assessment_period_id' => $this->periodId]))
            ->assertOk()
            ->assertDontSee('TIDAK VALID');
    }
}
