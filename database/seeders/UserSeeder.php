<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: never touch existing accounts.
        if (User::where('email', 'davidcho1973@gmail.com')->exists()) {
            return;
        }

        $password = Hash::make(env('ADMIN_PASSWORD', 'Nahshon!2026'));

        User::create([
            'name' => 'David Cho', 'email' => 'davidcho1973@gmail.com',
            'password' => $password, 'access' => 'admin', 'employee_id' => null,
        ]);
        User::create([
            'name' => 'Minjun Kim', 'email' => 'mkim@nahshon.io',
            'password' => $password, 'access' => 'manager', 'employee_id' => 101,
        ]);
        User::create([
            'name' => 'Carlos Martínez', 'email' => 'cmartinez@nahshon.io',
            'password' => $password, 'access' => 'worker', 'employee_id' => 106,
        ]);
    }
}
