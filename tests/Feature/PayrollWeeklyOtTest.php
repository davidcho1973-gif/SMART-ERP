<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Punch;
use App\Support\Payroll;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * One overtime rule everywhere (FLSA weekly 40h): the payroll screen, the
 * recorded check and the Excel register must all pay the same person the same
 * money for the same punches — including front-loaded weeks and period edges.
 */
class PayrollWeeklyOtTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
        // fix "today" inside the anchored period so currentPeriod() is deterministic
        Carbon::setTestNow('2026-07-08 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** a 10h-paid day for employee 106 (6:00–17:00, −1h lunch, legacy 6–3 guess, no snap on the late out) */
    private function tenHourDay(string $date): void
    {
        Punch::create(['employee_id' => 106, 'work_date' => $date, 'in_min' => 360, 'out_min' => 1020, 'no_lunch' => false, 'source' => 'qr']);
    }

    public function test_front_loaded_week_pays_weekly_overtime(): void
    {
        // period Jun 29 – Jul 12 · week1: 5×10h = 50h · week2: 3×10h = 30h
        foreach (['2026-06-29', '2026-06-30', '2026-07-01', '2026-07-02', '2026-07-03'] as $d) {
            $this->tenHourDay($d);
        }
        foreach (['2026-07-06', '2026-07-07', '2026-07-08'] as $d) {
            $this->tenHourDay($d);
        }

        [$start, $end] = Payroll::currentPeriod();
        $this->assertSame(['2026-06-29', '2026-07-12'], [$start, $end]);

        $b = Payroll::breakdownFor(Employee::find(106), $start, $end);
        // weekly 40h: wk1 → 40reg+10ot, wk2 → 30reg. NOT period-80 (which would say 80reg/0ot).
        $this->assertEqualsWithDelta(70.0, $b['reg'], 0.01);
        $this->assertEqualsWithDelta(10.0, $b['ot'], 0.01);

        $rate = (float) Employee::find(106)->rate;
        $this->assertEqualsWithDelta(70 * $rate + 10 * $rate * 1.5, Payroll::grossPay($b, $rate), 0.01);
    }

    public function test_recorded_check_uses_the_same_weekly_rule(): void
    {
        foreach (['2026-06-29', '2026-06-30', '2026-07-01', '2026-07-02', '2026-07-03'] as $d) {
            $this->tenHourDay($d);
        }

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openPayDetail', 106)
            ->set('checkNo', '1001')
            ->call('printVoucher');

        $pay = Payment::where('employee_id', 106)->first();
        $this->assertNotNull($pay);
        $rate = (float) Employee::find(106)->rate;
        // 50h in one week → 40 reg + 10 OT (period-80 would have written 50 reg / 0 OT)
        $this->assertSame(40, $pay->reg_hours);
        $this->assertSame(10, $pay->ot_hours);
        $this->assertEqualsWithDelta(40 * $rate + 10 * $rate * 1.5, (float) $pay->amount, 0.01);
    }

    public function test_excel_register_grid_covers_exactly_the_period(): void
    {
        $m = new \ReflectionMethod(\App\Http\Controllers\PayrollExportController::class, 'calendarWeeks');
        $weeks = $m->invoke(new \App\Http\Controllers\PayrollExportController, '2026-06-29', '2026-07-12');

        // exactly two Mon–Sun weeks, Jun 29 → Jul 12 — no Sunday-before, no week after
        $this->assertCount(2, $weeks);
        $this->assertSame('2026-06-29', $weeks[0]['days'][0]['date']);
        $this->assertSame('Mon', $weeks[0]['days'][0]['dow']);
        $this->assertSame('2026-07-12', $weeks[1]['days'][6]['date']);

        // and the export itself still renders (Monday-first headers)
        $xml = $this->xml($this->get('/export/payroll?start=2026-06-29&end=2026-07-12&recipient=hourly&lang=en')->getContent());
        $this->assertMatchesRegularExpression('/Mon.*Tue.*Wed.*Thu.*Fri.*Sat.*Sun/s', $xml);
    }

    public function test_no_punch_employee_pays_zero_in_real_mode(): void
    {
        config(['workforce.demo' => false]);
        $e = Employee::find(107);   // seeded wh=84 but NO punches this period
        $b = Payroll::breakdownFor($e, '2026-06-29', '2026-07-12');
        $this->assertSame(0.0, $b['reg']);
        $this->assertSame(0.0, $b['ot']);

        config(['workforce.demo' => true]);   // demo keeps the seeded showcase figure
        $b = Payroll::breakdownFor($e, '2026-06-29', '2026-07-12');
        $this->assertEqualsWithDelta(80.0, $b['reg'], 0.01);
        $this->assertEqualsWithDelta(4.0, $b['ot'], 0.01);
    }

    public function test_terminated_mid_period_worker_still_appears_on_the_register(): void
    {
        $this->tenHourDay('2026-06-30');
        Employee::whereKey(106)->update(['emp' => 'terminated', 'term' => '07/02/2026']);

        $xml = $this->xml($this->get('/export/payroll?start=2026-06-29&end=2026-07-12&recipient=hourly&lang=en')->getContent());
        $this->assertStringContainsString('Carlos', $xml);   // worked days are still owed
    }

    private function xml(string $binary): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsxot');
        file_put_contents($tmp, $binary);
        $zip = new \ZipArchive();
        $zip->open($tmp);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);

        return $xml;
    }

}
