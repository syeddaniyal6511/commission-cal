<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        $this->call([
            FormulaSeeder::class,           // 1. create formulas (v1 inactive, v2 active)
            ContractSeeder::class,          // 2. create 1000 contracts
            ContractCommissionSeeder::class, // 3. calculate & store commission for every contract
        ]);
    }
}
