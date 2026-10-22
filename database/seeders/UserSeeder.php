<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = config('app.env') === 'production'
            ? str()->random(32)
            : env('SEEDER_DEFAULT_PASSWORD', 'password');

        User::firstOrCreate(
            ['email' => 'admin@bprs.local'],
            [
                'name' => 'Admin BPRS',
                'password' => Hash::make($defaultPassword),
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'analyst@bprs.local'],
            [
                'name' => 'Credit Analyst',
                'password' => Hash::make($defaultPassword),
                'email_verified_at' => now(),
            ]
        );
    }
}
