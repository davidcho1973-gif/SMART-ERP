<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Punch;
use Illuminate\Database\Seeder;

/**
 * Realistic completed daily attendance for the previous two-week period
 * (Jun 15–26 2026). These dates sit *before* the current payroll period
 * (Jun 29 – Jul 12), so payroll totals are unaffected — this only gives the
 * daily timesheet real actual/paid/reg/OT history to display.
 */
class AttendanceHistorySeeder extends Seeder
{
    public function run(): void
    {
        // 10 weekdays of the prior period
        $days = [
            '2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19',
            '2026-06-22', '2026-06-23', '2026-06-24', '2026-06-25', '2026-06-26',
        ];

        $workers = Employee::where('emp', 'active')->where('type', 'worker')->get();

        foreach ($workers as $e) {
            $base = $e->id % 2 === 0 ? 360 : 420;           // scheduled 6:00 or 7:00
            foreach ($days as $i => $date) {
                if (Punch::where('employee_id', $e->id)->where('work_date', $date)->exists()) {
                    continue;
                }
                // a few minutes early/late (exercises the ±30min grace snap)
                $jitter = (($e->id * 7 + $i * 3) % 21) - 8;   // −8..+12
                $inMin = $base + $jitter;
                // paid hours vary 7–9h so some days show overtime
                $paid = 8 + ((($e->id + $i) % 3) - 1);        // 7, 8, or 9
                $outMin = $inMin + ($paid * 60) + 60;         // + 1h lunch

                Punch::create([
                    'employee_id' => $e->id,
                    'work_date' => $date,
                    'in_min' => $inMin,
                    'out_min' => $outMin,
                    'no_lunch' => false,
                    'source' => 'seed',
                ]);
            }
        }
    }
}
