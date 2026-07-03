<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Site;
use App\Models\Team;
use Illuminate\Database\Seeder;

class WorkforceSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: once real data exists, never overwrite it. Safe to run on every deploy.
        // (A clean reseed is still available via `php artisan migrate:fresh --seed`.)
        if (Site::query()->exists()) {
            return;
        }

        foreach ([
            ['id' => 's1', 'name' => 'TSMC Fab 21', 'city' => 'Phoenix, AZ', 'gc' => 'Hoffman', 'code' => 'AZ-P21'],
            ['id' => 's2', 'name' => 'Intel Ocotillo', 'city' => 'Chandler, AZ', 'gc' => 'Hoffman', 'code' => 'AZ-OCO'],
            ['id' => 's3', 'name' => 'Samsung Taylor', 'city' => 'Taylor, TX', 'gc' => 'Hoffman', 'code' => 'TX-TAY'],
        ] as $row) {
            Site::create($row);
        }

        foreach ([
            ['id' => 'c1', 'name' => 'Sonoran MEP', 'site_id' => 's1'],
            ['id' => 'c2', 'name' => 'Copper State Electric', 'site_id' => 's1'],
            ['id' => 'c3', 'name' => 'Rio Mechanical', 'site_id' => 's1'],
        ] as $row) {
            Company::create($row);
        }

        foreach ([
            ['id' => 't1', 'name' => 'Electrical Crew A', 'company_id' => 'c2', 'lead' => 101, 'color' => '#3B72E0'],
            ['id' => 't2', 'name' => 'Piping Crew', 'company_id' => 'c3', 'lead' => 102, 'color' => '#1F9D6B'],
            ['id' => 't3', 'name' => 'HVAC / Duct', 'company_id' => 'c1', 'lead' => 103, 'color' => '#E85D2A'],
            ['id' => 't4', 'name' => 'Fire Systems', 'company_id' => 'c1', 'lead' => 104, 'color' => '#D9483B'],
            ['id' => 't5', 'name' => 'Demo / Steel', 'company_id' => 'c3', 'lead' => 105, 'color' => '#8A5CF6'],
        ] as $row) {
            Team::create($row);
        }

        // id, empId, first, last, ko, nat, code, team, company, site, role, type, lang, access, rate, issued, phone, email, status, inT, outT, wh, emp, term
        $E = [
            [101, 'HOF-AZ-100311', 'Minjun', 'Kim', '김민준', 'Korea', 'KR', 't1', 'c2', 's1', 'Foreman', 'manager', 'ko', 'manager', 46.00, '01/12/2025', '(480) 555-0132', 'mkim@nahshon.io', 'present', '6:41 AM', '—', 82, 'active', null],
            [102, 'HOF-AZ-100312', 'Seojun', 'Park', '박서준', 'Korea', 'KR', 't2', 'c3', 's1', 'Foreman', 'manager', 'ko', 'manager', 45.50, '02/03/2025', '(480) 555-0147', 'spark@nahshon.io', 'present', '6:45 AM', '—', 80, 'active', null],
            [103, 'HOF-AZ-100313', 'Dohyun', 'Lee', '이도현', 'Korea', 'KR', 't3', 'c1', 's1', 'Foreman', 'manager', 'ko', 'manager', 47.00, '11/20/2024', '(602) 555-0188', 'dlee@nahshon.io', 'present', '6:38 AM', '—', 84, 'active', null],
            [104, 'HOF-AZ-100314', 'Jihoon', 'Choi', '최지훈', 'Korea', 'KR', 't4', 'c1', 's1', 'Foreman', 'manager', 'ko', 'manager', 44.00, '03/09/2025', '(602) 555-0192', 'jchoi@nahshon.io', 'late', '7:22 AM', '—', 78, 'active', null],
            [105, 'HOF-AZ-100315', 'Woojin', 'Jung', '정우진', 'Korea', 'KR', 't5', 'c3', 's1', 'Foreman', 'manager', 'ko', 'manager', 43.50, '12/15/2024', '(480) 555-0210', 'wjung@nahshon.io', 'present', '6:50 AM', '—', 80, 'active', null],
            [106, 'HOF-AZ-100402', 'Carlos', 'Martínez', null, 'Mexico', 'MX', 't1', 'c2', 's1', 'Electrician', 'worker', 'es', 'worker', 32.50, '03/14/2026', '(480) 555-0331', 'cmartinez@nahshon.io', 'present', '6:52 AM', '—', 86, 'active', null],
            [107, 'HOF-AZ-100403', 'José', 'Hernández', null, 'Guatemala', 'GT', 't1', 'c2', 's1', 'Helper', 'worker', 'es', 'worker', 24.00, '03/18/2026', '(480) 555-0342', 'jhernandez@nahshon.io', 'present', '6:55 AM', '—', 84, 'active', null],
            [108, 'HOF-AZ-100404', 'Miguel', 'Torres', null, 'Honduras', 'HN', 't2', 'c3', 's1', 'Pipefitter', 'worker', 'es', 'worker', 34.00, '02/22/2026', '(602) 555-0355', 'mtorres@nahshon.io', 'late', '7:18 AM', '—', 79, 'active', null],
            [109, 'HOF-AZ-100405', 'Luis', 'García', null, 'El Salvador', 'SV', 't2', 'c3', 's1', 'Helper', 'worker', 'es', 'worker', 23.50, '04/02/2026', '(602) 555-0361', 'lgarcia@nahshon.io', 'present', '6:49 AM', '—', 82, 'active', null],
            [110, 'HOF-AZ-100406', 'Juan', 'Morales', null, 'Mexico', 'MX', 't3', 'c1', 's1', 'Sheet Metal', 'worker', 'es', 'worker', 31.00, '01/28/2026', '(480) 555-0377', 'jmorales@nahshon.io', 'present', '6:44 AM', '—', 85, 'active', null],
            [111, 'HOF-AZ-100407', 'Diego', 'Flores', null, 'Guatemala', 'GT', 't3', 'c1', 's1', 'HVAC Tech', 'worker', 'es', 'worker', 33.50, '02/10/2026', '(480) 555-0384', 'dflores@nahshon.io', 'present', '6:57 AM', '—', 83, 'active', null],
            [112, 'HOF-AZ-100408', 'Pedro', 'Sánchez', null, 'Mexico', 'MX', 't4', 'c1', 's1', 'Sprinkler Fitter', 'worker', 'es', 'worker', 35.00, '03/01/2026', '(602) 555-0390', 'psanchez@nahshon.io', 'absent', '—', '—', 72, 'active', null],
            [113, 'HOF-AZ-100409', 'Andrés', 'Vargas', null, 'Honduras', 'HN', 't4', 'c1', 's1', 'Helper', 'worker', 'es', 'worker', 24.50, '04/11/2026', '(602) 555-0402', 'avargas@nahshon.io', 'present', '6:53 AM', '—', 81, 'active', null],
            [114, 'HOF-AZ-100410', 'Roberto', 'Cruz', null, 'Mexico', 'MX', 't5', 'c3', 's1', 'Laborer', 'worker', 'es', 'worker', 22.00, '02/17/2026', '(480) 555-0418', 'rcruz@nahshon.io', 'present', '6:47 AM', '—', 80, 'active', null],
            [115, 'HOF-AZ-100411', 'Fernando', 'Reyes', null, 'El Salvador', 'SV', 't5', 'c3', 's1', 'Demo Tech', 'worker', 'es', 'worker', 28.00, '03/22/2026', '(480) 555-0424', 'freyes@nahshon.io', 'off', '—', '—', 64, 'active', null],
            [116, 'HOF-AZ-100412', 'Ricardo', 'Gómez', null, 'Mexico', 'MX', 't1', 'c2', 's1', 'Electrician', 'worker', 'es', 'worker', 32.00, '01/15/2026', '(480) 555-0436', 'rgomez@nahshon.io', 'present', '6:51 AM', '—', 87, 'active', null],
            [117, 'HOF-AZ-100388', 'Antonio', 'Díaz', null, 'Guatemala', 'GT', 't2', 'c3', 's1', 'Pipefitter', 'worker', 'es', 'worker', 33.00, '11/05/2025', '(602) 555-0449', 'adiaz@nahshon.io', 'off', '—', '—', 0, 'terminated', '06/20/2026'],
        ];

        foreach ($E as $r) {
            $e = new Employee([
                'emp_id' => $r[1], 'first' => $r[2], 'last' => $r[3], 'ko' => $r[4],
                'nat' => $r[5], 'code' => $r[6], 'team_id' => $r[7], 'company_id' => $r[8], 'site_id' => $r[9],
                'role' => $r[10], 'type' => $r[11], 'lang' => $r[12], 'access' => $r[13], 'rate' => $r[14],
                'issued' => $r[15], 'phone' => $r[16], 'email' => $r[17], 'status' => $r[18],
                'in_t' => $r[19], 'out_t' => $r[20], 'wh' => $r[21], 'emp' => $r[22], 'term' => $r[23],
            ]);
            $e->id = $r[0];
            $e->save();
        }
    }
}
