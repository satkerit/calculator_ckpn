<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('permissions')->insert([
            [
                'kode_permission' => 'assessment.create',
                'nama_permission' => 'Buat Assessment',
                'module' => 'assessment',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_permission' => 'assessment.edit',
                'nama_permission' => 'Edit Assessment',
                'module' => 'assessment',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_permission' => 'assessment.view',
                'nama_permission' => 'Lihat Assessment',
                'module' => 'assessment',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_permission' => 'calculation.run',
                'nama_permission' => 'Jalankan Kalkulasi',
                'module' => 'calculation',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_permission' => 'calculation.validate',
                'nama_permission' => 'Validasi Data',
                'module' => 'calculation',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_permission' => 'report.export',
                'nama_permission' => 'Export Laporan',
                'module' => 'report',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_permission' => 'audit.view',
                'nama_permission' => 'Lihat Audit Trail',
                'module' => 'audit',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
