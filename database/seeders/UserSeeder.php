<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: never touch existing accounts.
        if (User::where('email', 'davidcho1973@gmail.com')->exists()) {
            return;
        }

        $isProd = app()->environment('production') && ! config('workforce.demo');

        // Production requires ADMIN_PASSWORD to be set; otherwise the account is
        // created with an unguessable random password and sign-in is via Google.
        $adminPassword = env('ADMIN_PASSWORD');
        if ($isProd && ! $adminPassword) {
            $adminPassword = Str::password(32);
            $this->command?->warn('ADMIN_PASSWORD not set — admin account created for Google sign-in only.');
        }

        User::create([
            'name' => 'David Cho', 'email' => 'davidcho1973@gmail.com',
            'password' => Hash::make($adminPassword ?: 'Nahshon!2026'),
            'access' => 'admin', 'employee_id' => null,
        ]);

        if ($isProd) {
            return;   // no demo manager/worker accounts in production
        }

        $password = Hash::make(env('ADMIN_PASSWORD', 'Nahshon!2026'));
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
