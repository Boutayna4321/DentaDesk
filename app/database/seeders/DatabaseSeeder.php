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
        // Demo accounts, documented in the README. Change them before going live.
        $dentist = User::create(['name' => 'Dr Nadia Alaoui', 'email' => 'dentiste@dentadesk.test', 'password' => 'dentadesk2026', 'role' => 'dentist']);
        User::create(['name' => 'Khadija Amrani', 'email' => 'secretaire@dentadesk.test', 'password' => 'dentadesk2026', 'role' => 'secretary']);

        $this->call(CabinetSeeder::class, parameters: ['dentist' => $dentist]);
        $this->call(DemoSeeder::class);
        $this->call(BillingSeeder::class);
        $this->call(MessageSeeder::class);
    }
}
