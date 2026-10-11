<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AkadTypeSeeder extends Seeder
{
    /**
     * Kode master akad (numerik, sesuai data snapshot):
     * 03=Musyarakah, 06=Murabahah, 10=IMBT, 13=Multijasa
     */
    public function run(): void
    {
        \DB::table('akad_types')->insert([
            [
                'kode_akad' => '03',
                'nama_akad' => 'Musyarakah',
                'kategori' => 'Bagi Hasil',
                'ead_rule_type' => 'principal_arrears',
                'requires_due_date' => true,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_akad' => '06',
                'nama_akad' => 'Murabahah',
                'kategori' => 'Jual Beli',
                'ead_rule_type' => 'outstanding_balance',
                'requires_due_date' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_akad' => '10',
                'nama_akad' => 'IMBT',
                'kategori' => 'Sewa Beli',
                'ead_rule_type' => 'principal_arrears',
                'requires_due_date' => true,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_akad' => '13',
                'nama_akad' => 'Multi Jasa',
                'kategori' => 'Jasa',
                'ead_rule_type' => 'outstanding_balance',
                'requires_due_date' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
