<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UsageTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('usage_types')->insert([
            [
                'kode_jenis_penggunaan' => 'MODAL',
                'nama' => 'Modal Kerja',
                'deskripsi' => 'Pembiayaan Modal Kerja',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_jenis_penggunaan' => 'INVESTASI',
                'nama' => 'Investasi',
                'deskripsi' => 'Pembiayaan Investasi',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_jenis_penggunaan' => 'KONSUMSI',
                'nama' => 'Konsumsi',
                'deskripsi' => 'Pembiayaan Konsumsi',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
