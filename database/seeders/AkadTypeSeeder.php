<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AkadTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('akad_types')->insert([
            [
                'kode_akad' => 'MURA',
                'nama_akad' => 'Murabahah',
                'kategori' => 'Jual Beli',
                'ead_rule_type' => 'outstanding_balance',
                'requires_due_date' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_akad' => 'MJA',
                'nama_akad' => 'Multi Jasa',
                'kategori' => 'Jasa',
                'ead_rule_type' => 'outstanding_balance',
                'requires_due_date' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_akad' => 'MUSY',
                'nama_akad' => 'Musyarakah',
                'kategori' => 'Bagi Hasil',
                'ead_rule_type' => 'principal_arrears',
                'requires_due_date' => true,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_akad' => 'IMBT',
                'nama_akad' => 'IMBT',
                'kategori' => 'Sewa Beli',
                'ead_rule_type' => 'principal_arrears',
                'requires_due_date' => true,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
