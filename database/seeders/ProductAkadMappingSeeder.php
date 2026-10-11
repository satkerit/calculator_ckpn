<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductAkadMappingSeeder extends Seeder
{
    public function run(): void
    {
        $mappings = [
            // Kode akad master numerik: 03=Musyarakah, 06=Murabahah, 10=IMBT, 13=Multijasa
            ['product_code' => '03', 'product_name' => 'Pembiayaan Musyarakah', 'akad_code' => '03', 'akad_name' => 'Musyarakah', 'ead_rule_type' => 'MUSYARAKAH'],
            ['product_code' => '06', 'product_name' => 'Pembiayaan Murabahah', 'akad_code' => '06', 'akad_name' => 'Murabahah', 'ead_rule_type' => 'MURABAHAH'],
            ['product_code' => '10', 'product_name' => 'Pembiayaan IMBT', 'akad_code' => '10', 'akad_name' => 'Ijarah Muntahiya Bittamlik', 'ead_rule_type' => 'IMBT'],
            ['product_code' => '13', 'product_name' => 'Pembiayaan Multijasa', 'akad_code' => '13', 'akad_name' => 'Multijasa', 'ead_rule_type' => 'MULTIJASA'],

            // Produk tambahan (default ke Murabahah)
            ['product_code' => '55', 'product_name' => 'Produk Default 55', 'akad_code' => '06', 'akad_name' => 'Murabahah', 'ead_rule_type' => 'MURABAHAH'],
            ['product_code' => '72', 'product_name' => 'Produk Default 72', 'akad_code' => '06', 'akad_name' => 'Murabahah', 'ead_rule_type' => 'MURABAHAH'],
        ];

        foreach ($mappings as $mapping) {
            DB::table('product_akad_mappings')->updateOrInsert(
                ['product_code' => $mapping['product_code']],
                array_merge($mapping, [
                    'active' => true,
                    'description' => 'Inisialisasi seed - sesuaikan dengan data aktual',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
