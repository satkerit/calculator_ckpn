<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@bprs.local'],
            [
                'name' => 'Admin BPRS',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'analyst@bprs.local'],
            [
                'name' => 'Credit Analyst',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }
}
