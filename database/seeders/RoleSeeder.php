<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('roles')->insert([
            [
                'kode_role' => 'MAKER',
                'nama_role' => 'Pembuat Assessment',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_role' => 'CHECKER',
                'nama_role' => 'Pemeriksa Assessment',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_role' => 'APPROVER',
                'nama_role' => 'Penyetuju CKPN',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_role' => 'ADMIN',
                'nama_role' => 'Administrator Sistem',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_role' => 'AUDITOR',
                'nama_role' => 'Auditor Kepatuhan',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
