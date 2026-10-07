<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Services\CupsPrinterService;
use App\Services\PrintJobProcessor;
use App\Services\StateStore;
use App\Services\TestPage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class TestPageTest extends TestCase
{
    public function test_the_pdf_is_a_well_formed_document(): void
    {
        $pdf = app(TestPage::class)->pdf('Office (1)');

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('Printer: Office \\(1\\)', $pdf);
        preg_match('/startxref\n(\d+)/', $pdf, $matches);
        $this->assertSame('xref', substr($pdf, (int) $matches[1], 4));
    }

    public function test_a_zebra_printer_gets_zpl(): void
    {
        $this->assertStringStartsWith('^XA', app(TestPage::class)->zpl('zebra-1'));
    }

    public function test_an_unknown_printer_gets_a_pdf_through_cups(): void
    {
        $this->mock(CupsPrinterService::class)
            ->shouldReceive('submit')
            ->once()
            ->with(Mockery::on(fn (string $file): bool => str_ends_with($file, '.pdf') && str_starts_with((string) file_get_contents($file), '%PDF')), 'office', 1)
            ->andReturn('office-1');

        $this->assertSame('office-1', app(PrintJobProcessor::class)->printTestPage('office'));
    }

    public function test_the_dashboard_action_reports_the_outcome(): void
    {
        $this->configureHub();
        app(StateStore::class)->write('printers', ['list' => [['name' => 'office', 'state' => 'idle', 'description' => null, 'type' => 'cups']]]);
        $this->mock(PrintJobProcessor::class)->shouldReceive('printTestPage')->once()->with('office');

        Livewire::test(Dashboard::class)
            ->call('printTestPage', 'office')
            ->assertSet('testPassed', true);
    }
}
