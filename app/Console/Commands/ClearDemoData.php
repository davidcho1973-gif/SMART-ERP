<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Punch;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Wipe all seeded demo domain data so a real instance can start clean.
 * Keeps admin logins (so you can still sign in) and unlinks their employee ids.
 * Run once on production: php artisan app:clear-demo
 */
class ClearDemoData extends Command
{
    protected $signature = 'app:clear-demo';

    protected $description = 'Remove all demo data (sites, companies, crews, employees, punches, payments); keeps admin login';

    public function handle(): int
    {
        Punch::query()->delete();
        Payment::query()->delete();
        Employee::query()->delete();
        Team::query()->delete();
        Company::query()->delete();
        Site::query()->delete();

        // keep real admins; drop the seeded demo manager/worker logins
        User::whereIn('email', ['mkim@nahshon.io', 'cmartinez@nahshon.io'])->delete();
        User::query()->update(['employee_id' => null]);

        $this->info('Demo data cleared. Sites, companies, crews, employees, punches and payments removed; admin login kept.');

        return self::SUCCESS;
    }
}
