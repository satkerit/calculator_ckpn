<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CollateralSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('collateral_setup')->insert([
            [
                'collateral_type' => 'TANAH',
                'collateral_ket' => 'Tanah & Bangunan',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'collateral_type' => 'KENDARAAN',
                'collateral_ket' => 'Kendaraan Bermotor',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'collateral_type' => 'PIUTANG',
                'collateral_ket' => 'Piutang / Receivable',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'collateral_type' => 'LAINNYA',
                'collateral_ket' => 'Jaminan Lainnya',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
