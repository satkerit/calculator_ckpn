<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BucketDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Idempotent: bersihkan dulu agar ID selalu 1-14 (bucket_no di seluruh
        // sistem = urutan 1..14, bukan auto-increment ID).
        \DB::table('bucket_definitions')->truncate();

        \DB::table('bucket_definitions')->insert([
            [
                'kode_bucket' => 'B0',
                'deskripsi_hari' => '0 hari (Lancar)',
                'hari_min' => 0,
                'hari_max' => 0,
                'urutan' => 1,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_bucket' => 'B1',
                'deskripsi_hari' => '1-30 hari',
                'hari_min' => 1,
                'hari_max' => 30,
                'urutan' => 2,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_bucket' => 'B2',
                'deskripsi_hari' => '31-60 hari',
                'hari_min' => 31,
                'hari_max' => 60,
                'urutan' => 3,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_bucket' => 'B3',
                'deskripsi_hari' => '61-90 hari',
                'hari_min' => 61,
                'hari_max' => 90,
                'urutan' => 4,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_bucket' => 'B4',
                'deskripsi_hari' => '91-120 hari',
                'hari_min' => 91,
                'hari_max' => 120,
                'urutan' => 5,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_bucket' => 'B5',
                'deskripsi_hari' => '121-150 hari',
                'hari_min' => 121,
                'hari_max' => 150,
                'urutan' => 6,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_bucket' => 'B6',
                'deskripsi_hari' => '151-180 hari',
                'hari_min' => 151,
                'hari_max' => 180,
                'urutan' => 7,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_bucket' => 'B7',
                'deskripsi_hari' => '181-210 hari',
                'hari_min' => 181,
                'hari_max' => 210,
                'urutan' => 8,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_bucket' => 'B8',
                'deskripsi_hari' => '211-240 hari',
                'hari_min' => 211,
                'hari_max' => 240,
                'urutan' => 9,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_bucket' => 'B9',
                'deskripsi_hari' => '241-270 hari',
                'hari_min' => 241,
                'hari_max' => 270,
                'urutan' => 10,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_bucket' => 'B10',
                'deskripsi_hari' => '271-300 hari',
                'hari_min' => 271,
                'hari_max' => 300,
                'urutan' => 11,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_bucket' => 'B11',
                'deskripsi_hari' => '301-330 hari',
                'hari_min' => 301,
                'hari_max' => 330,
                'urutan' => 12,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_bucket' => 'B12',
                'deskripsi_hari' => '331-360 hari',
                'hari_min' => 331,
                'hari_max' => 360,
                'urutan' => 13,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_bucket' => 'B13',
                'deskripsi_hari' => '>360 hari (Write-Off)',
                'hari_min' => 361,
                'hari_max' => 99999,
                'urutan' => 14,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
