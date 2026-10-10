<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QualityRatingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('quality_ratings')->insert([
            [
                'kode_kualitas' => '1',
                'nama_kualitas' => 'Rating 1 (Lancar)',
                'urutan' => 1,
                'status' => 'ACTIVE',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_kualitas' => '2',
                'nama_kualitas' => 'Rating 2 (DPK)',
                'urutan' => 2,
                'status' => 'ACTIVE',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_kualitas' => '3',
                'nama_kualitas' => 'Rating 3 (KURANG LANCAR)',
                'urutan' => 3,
                'status' => 'ACTIVE',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_kualitas' => '4',
                'nama_kualitas' => 'Rating 4 (DIRAGUKAN)',
                'urutan' => 4,
                'status' => 'ACTIVE',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_kualitas' => '5',
                'nama_kualitas' => 'Rating 5 (MACET)',
                'urutan' => 5,
                'status' => 'ACTIVE',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_kualitas' => 'WO',
                'nama_kualitas' => 'Write-Off',
                'urutan' => 6,
                'status' => 'ACTIVE',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
