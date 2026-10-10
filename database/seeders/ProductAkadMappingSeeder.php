<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductAkadMappingSeeder extends Seeder
{
    public function run(): void
    {
        $mappings = [
            // Data dari QA probe: financing_contract_code 03, 06, 10, 13 (proksi product_code)
            // Asumsi: 03=Murabahah, 06=Multijasa, 10=Musyarakah, 13=IMBT (adjust per data real)
            ['product_code' => '03', 'product_name' => 'Pembiayaan Murabahah', 'akad_code' => 'MURA', 'akad_name' => 'Murabahah', 'ead_rule_type' => 'MURABAHAH'],
            ['product_code' => '06', 'product_name' => 'Pembiayaan Multijasa', 'akad_code' => 'MJA', 'akad_name' => 'Multijasa', 'ead_rule_type' => 'MULTIJASA'],
            ['product_code' => '10', 'product_name' => 'Pembiayaan Musyarakah', 'akad_code' => 'MUSY', 'akad_name' => 'Musyarakah', 'ead_rule_type' => 'MUSYARAKAH'],
            ['product_code' => '13', 'product_name' => 'Pembiayaan IMBT', 'akad_code' => 'IMBT', 'akad_name' => 'Ijarah Muntahiya Bittamlik', 'ead_rule_type' => 'IMBT'],
            
            // Tambahan: product_code numerik lain yang mungkin ada (55, 72 dari snapshot)
            ['product_code' => '55', 'product_name' => 'Produk Default 55', 'akad_code' => 'MURA', 'akad_name' => 'Murabahah', 'ead_rule_type' => 'MURABAHAH'],
            ['product_code' => '72', 'product_name' => 'Produk Default 72', 'akad_code' => 'MURA', 'akad_name' => 'Murabahah', 'ead_rule_type' => 'MURABAHAH'],
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
