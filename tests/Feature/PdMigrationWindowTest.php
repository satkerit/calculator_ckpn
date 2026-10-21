<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Historical window PD dipakai oleh PD Net Flow (bucket) dan PD Migration
 * (kualitas/Kol). Untuk Migration, window menentukan jumlah matriks triwulan
 * = floor(historical_months / 3).
 */
class PdMigrationWindowTest extends TestCase
{
    use RefreshDatabase;

    private int $periodId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = $this->makeAdminUser();
        $this->actingAs($user);

        $this->periodId = DB::table('assessment_periods')->insertGetId([
            'period_code' => '2026-09',
            'assessment_date' => '2026-09-30',
            'status' => 'REVIEW',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('offices')->insert([
            'office_code' => '01',
            'office_name' => 'Kantor Pusat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stage_classifications')->insert([
            'assessment_period_id' => $this->periodId,
            'loan_number' => 'L001',
            'office_code' => '01',
            'akad_code' => '03',
            'usage_code' => 'MODAL',
            'ead_amount' => 900_000,
            'classification' => 'COLLECTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Dua titik snapshot untuk satu matriks triwulan (202409 → 202509)
        // + posisi asesmen sebagai bobot PD grup.
        foreach ([
            ['periode_snapshot' => '202409', 'quality_rating' => '2', 'outstanding_balance' => 1_000_000],
            ['periode_snapshot' => '202509', 'quality_rating' => '1', 'outstanding_balance' => 900_000],
            ['periode_snapshot' => '202609', 'quality_rating' => '1', 'outstanding_balance' => 900_000],
        ] as $row) {
            DB::table('snapshot')->insert($row + [
                'loan_number' => 'L001',
                'office_code' => '01',
                'active_flag' => 1,
                'write_off_flag' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function saveConfig(int $historicalMonths): void
    {
        DB::table('stage_configs')->updateOrInsert(
            ['assessment_period_id' => $this->periodId],
            [
                'pd_method' => 'MIGRATION',
                'lgd_method' => 'WEIGHTED',
                'historical_months' => $historicalMonths,
                'lgd_historical_months' => 60,
                'sale_cost_percentage' => 0.05,
                'segmentation_dims' => 'OFFICE',
                'individual_top_n' => 30,
                'npl_ratings' => '3,4,5',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function runMigration(): void
    {
        $response = $this->post(route('calculation.module.pd_migration.run'), [
            'assessment_period_id' => $this->periodId,
        ]);

        $this->assertSame(302, $response->getStatusCode(), 'Run PD Migration harus redirect.');
    }

    private function migrationDetail(): array
    {
        $row = DB::table('stage_pd_results')
            ->where('assessment_period_id', $this->periodId)
            ->where('method', 'MIGRATION')
            ->orderBy('bucket_no')
            ->first();

        $this->assertNotNull($row, 'PD Migration harus menyimpan hasil per rating.');

        return json_decode($row->detail, true) ?: [];
    }

    public function test_migration_uses_configured_historical_window(): void
    {
        $this->saveConfig(12);
        $this->runMigration();

        // Satu matriks per rating (bucket_no 1..5).
        $this->assertSame(5, DB::table('stage_pd_results')
            ->where('assessment_period_id', $this->periodId)
            ->where('method', 'MIGRATION')
            ->count());

        $detail = $this->migrationDetail();
        $this->assertSame(12, (int) ($detail['historical_months'] ?? 0), 'Window harus diambil dari konfigurasi, bukan default 36.');
        // floor(12 / 3) = 4 matriks triwulan.
        $this->assertLessThanOrEqual(4, (int) ($detail['matrices_used'] ?? 0));
    }

    public function test_changing_window_changes_number_of_quarterly_matrices(): void
    {
        $this->saveConfig(12);
        $this->runMigration();
        $matrices12 = (int) ($this->migrationDetail()['matrices_used'] ?? 0);

        // Window 24 bulan → floor(24 / 3) = 8 matriks (maksimum).
        $this->saveConfig(24);
        $this->runMigration();
        $detail = $this->migrationDetail();

        $this->assertSame(24, (int) ($detail['historical_months'] ?? 0));
        $this->assertGreaterThanOrEqual($matrices12, (int) ($detail['matrices_used'] ?? 0));
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
}
