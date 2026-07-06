<?php

namespace Tests\Feature;

use App\Models\Punch;
use App\Support\Xlsx;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * Bi-weekly payroll register export.
 * Seeded facts: worker 106 (Carlos) is a local, hourly-paid worker; employee 101
 * (Minjun Kim) is a Korean manager and therefore salaried.
 */
class PayrollExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);   // bypass auth like the other feature tests
        $this->seed(WorkforceSeeder::class);
    }

    /** Read sheet1.xml text out of the returned .xlsx binary. */
    private function sheetXml(string $binary): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsxtest');
        file_put_contents($tmp, $binary);
        $zip = new ZipArchive();
        $zip->open($tmp);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);

        return $xml;
    }

    public function test_seeder_marks_koreans_and_managers_salaried_locals_hourly(): void
    {
        $this->assertSame('salary', \App\Models\Employee::find(101)->pay_type);   // Korean manager
        $this->assertSame('hourly', \App\Models\Employee::find(106)->pay_type);   // local worker
    }

    public function test_export_downloads_xlsx_with_period_in_filename(): void
    {
        $res = $this->get('/export/payroll?start=2026-06-15&end=2026-06-28&recipient=hourly&lang=en');
        $res->assertOk();
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('payroll_2026-06-15_2026-06-28.xlsx', $res->headers->get('content-disposition'));
    }

    public function test_hourly_worker_rows_carry_live_formulas(): void
    {
        // five 8-hour weekdays in week 1 → 40 reg hours, no OT
        foreach (['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19'] as $d) {
            Punch::create(['employee_id' => 106, 'work_date' => $d, 'in_min' => 420, 'out_min' => 960, 'source' => 'worker']);
        }

        $xml = $this->sheetXml($this->get('/export/payroll?start=2026-06-15&end=2026-06-27&recipient=hourly&lang=en')->getContent());

        $this->assertStringContainsString('Carlos', $xml);
        $this->assertStringContainsString('<f>SUM(G6:M6)</f>', $xml);      // week-1 total, same cell map as the original
        $this->assertStringContainsString('<f>MIN(N6, 40)</f>', $xml);     // weekly regular cap
        $this->assertStringContainsString('<f>MAX(N6 - 40, 0)</f>', $xml); // weekly overtime
        $this->assertStringContainsString('*1.5', $xml);                   // OT rate = 1.5× regular
        $this->assertStringContainsString('<f>AC6+AE6</f>', $xml);         // 2wks grand total per row
    }

    public function test_recipient_filter_splits_hourly_and_salaried(): void
    {
        $hourly = $this->sheetXml($this->get('/export/payroll?recipient=hourly&lang=en')->getContent());
        $this->assertStringContainsString('Carlos', $hourly);
        $this->assertStringNotContainsString('Minjun', $hourly);

        $salary = $this->sheetXml($this->get('/export/payroll?recipient=salary&lang=en')->getContent());
        $this->assertStringContainsString('Minjun', $salary);
        $this->assertStringNotContainsString('Carlos', $salary);
    }

    public function test_sheet_reproduces_the_nahshon_template(): void
    {
        $bin = $this->get('/export/payroll?start=2026-06-15&end=2026-06-27&recipient=hourly&lang=en')->getContent();
        $xml = $this->sheetXml($bin);

        $this->assertStringContainsString('Project :', $xml);                    // big title block
        $this->assertStringContainsString('6/15/26-6/27/26', $xml);              // period label
        $this->assertStringContainsString('1ST WEEK', $xml);
        $this->assertStringContainsString('2ND WEEK', $xml);
        $this->assertStringContainsString('2Wks Total by Hours', $xml);
        $this->assertStringContainsString('# of Hours Worked', $xml);
        $this->assertStringContainsString('PAYMENT BANK INFORMATION', $xml);     // footer block
        $this->assertStringContainsString('orientation="landscape"', $xml);      // print setup
        $this->assertStringContainsString('fitToWidth="1" fitToHeight="1"', $xml);

        // styles carry the original palette (yellow grand total, peach hours, green amounts)
        $tmp = tempnam(sys_get_temp_dir(), 'xlsxtest');
        file_put_contents($tmp, $bin);
        $zip = new ZipArchive();
        $zip->open($tmp);
        $styles = (string) $zip->getFromName('xl/styles.xml');
        $hasLogo = $zip->getFromName('xl/media/image1.png') !== false;
        $zip->close();
        @unlink($tmp);

        foreach (['FFFFFF00', 'FFF7CAAC', 'FFE2EFD9', 'FFFBE4D5', 'FFFFE598', 'FFD9E2F3'] as $rgb) {
            $this->assertStringContainsString($rgb, $styles);
        }
        $this->assertStringContainsString('Arial', $styles);
        $this->assertTrue($hasLogo, 'company logo embedded');
    }

    public function test_xlsx_renders_numeric_and_formula_cells(): void
    {
        $bin = Xlsx::build([[
            'name' => 'T',
            'rows' => [
                ['label', 8, 2.5],
                ['sum', ['f' => 'SUM(B1:C1)', 'v' => 10.5]],
            ],
        ]]);
        $xml = $this->sheetXml($bin);

        $this->assertStringContainsString('t="n"><v>8</v>', $xml);              // integer numeric cell
        $this->assertStringContainsString('<f>SUM(B1:C1)</f><v>10.5</v>', $xml); // formula + cached value
        $this->assertStringContainsString('t="inlineStr"', $xml);              // strings still inline
    }
}
