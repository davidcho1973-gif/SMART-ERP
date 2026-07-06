<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Production seeds ONLY the admin account — never the demo workforce
     * (fake employees, punches, chat history). Staging/local/demo get the
     * full demo dataset as before.
     */
    public function run(): void
    {
        if (app()->environment('production') && ! config('workforce.demo')) {
            $this->call(UserSeeder::class);

            return;
        }

        $this->call(WorkforceSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(AttendanceHistorySeeder::class);
        $this->call(CommsSeeder::class);
    }
}
