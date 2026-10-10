<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('offices')->insert([
            [
                'office_code' => '01',
                'office_name' => 'Kantor Pusat Operasional',
                'office_type' => 'Branch',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'office_code' => '02',
                'office_name' => 'Kantor Cabang Sungailiat',
                'office_type' => 'Branch',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'office_code' => '03',
                'office_name' => 'Kantor Cabang Muntok',
                'office_type' => 'Branch',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'office_code' => '04',
                'office_name' => 'Kantor Cabang Koba',
                'office_type' => 'Branch',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'office_code' => '05',
                'office_name' => 'Kantor Cabang Tanjung Pandan',
                'office_type' => 'Branch',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'office_code' => '06',
                'office_name' => 'Kantor Cabang Toboali',
                'office_type' => 'Branch',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'office_code' => '07',
                'office_name' => 'Kantor Cabang Manggar',
                'office_type' => 'Branch',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
