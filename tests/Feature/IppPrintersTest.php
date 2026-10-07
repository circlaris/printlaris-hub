<?php

namespace Tests\Feature;

use App\Services\CupsPrinterService;
use App\Services\Ipp\IppPrinters;
use App\Services\Ipp\IppTool;
use App\Services\PrintJobProcessor;
use App\Services\StateStore;
use App\Services\Zebra\ZebraScanner;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class IppPrintersTest extends TestCase
{
    private function fakeScanner(array $hosts): void
    {
        $this->mock(ZebraScanner::class, function ($mock) use ($hosts): void {
            $mock->shouldReceive('candidateHosts')->andReturn($hosts);
            $mock->shouldReceive('openHosts')->andReturn($hosts);
        });
    }

    /** @return array<string, string> */
    private function attributes(array $overrides = []): array
    {
        return [
            'printer-name' => 'Office',
            'printer-make-and-model' => 'HP LaserJet',
            'printer-uuid' => 'urn:uuid:ABCD1234-0000-1111-2222-333344445555',
            'printer-state' => 'idle',
            'document-format-supported' => 'application/pdf,image/jpeg',
            ...$overrides,
        ];
    }

    public function test_discovery_identifies_printers_by_uuid_and_detects_pdf_support(): void
    {
        $this->fakeScanner(['10.0.0.5', '10.0.0.6']);
        $this->mock(IppTool::class)
            ->shouldReceive('attributes')
            ->with('ipp://10.0.0.5:631/ipp/print')->andReturn($this->attributes())
            ->shouldReceive('attributes')
            ->with('ipp://10.0.0.6:631/ipp/print')->andThrow(new \RuntimeException('not found'));

        $printers = app(IppPrinters::class)->discover();

        $this->assertSame(['ipp-abcd12340000'], array_keys($printers));
        $this->assertTrue($printers['ipp-abcd12340000']['pdf']);
        $this->assertTrue($printers['ipp-abcd12340000']['online']);
        $this->assertSame('HP LaserJet', app(IppPrinters::class)->report()[0]['description']);
        $this->assertSame('ipp', app(IppPrinters::class)->report()[0]['type']);
    }

    public function test_a_printer_that_is_no_longer_found_is_reported_offline(): void
    {
        $this->fakeScanner(['10.0.0.5']);
        $tool = $this->mock(IppTool::class);
        $tool->shouldReceive('attributes')->once()->andReturn($this->attributes());
        app(IppPrinters::class)->discover();

        $this->mock(ZebraScanner::class, function ($mock): void {
            $mock->shouldReceive('candidateHosts')->andReturn([]);
            $mock->shouldReceive('openHosts')->andReturn([]);
        });
        $printers = app(IppPrinters::class)->discover();

        $this->assertFalse($printers['ipp-abcd12340000']['online']);
        $this->assertSame('offline', app(IppPrinters::class)->report()[0]['state']);
    }

    public function test_pdf_printers_are_printed_directly(): void
    {
        $this->fakeScanner(['10.0.0.5']);
        $tool = $this->mock(IppTool::class);
        $tool->shouldReceive('attributes')->andReturn($this->attributes());
        $tool->shouldReceive('printPdf')->once()->with('ipp://10.0.0.5:631/ipp/print', '/tmp/a.pdf', 2)->andReturn('17');
        $this->mock(CupsPrinterService::class)->shouldNotReceive('submit');
        app(IppPrinters::class)->discover();

        $this->assertSame('17', app(IppPrinters::class)->print('ipp-abcd12340000', '/tmp/a.pdf', 2));
    }

    public function test_printers_without_pdf_support_go_through_a_cups_queue(): void
    {
        $this->fakeScanner(['10.0.0.5']);
        $this->mock(IppTool::class)->shouldReceive('attributes')
            ->andReturn($this->attributes(['document-format-supported' => 'image/urf']));
        $this->mock(CupsPrinterService::class, function ($mock): void {
            $mock->shouldReceive('ensureQueue')->once()->with('ipp-abcd12340000', 'ipp://10.0.0.5:631/ipp/print', 'HP LaserJet');
            $mock->shouldReceive('submit')->once()->with('/tmp/a.pdf', 'ipp-abcd12340000', 1)->andReturn('ipp-abcd12340000-3');
        });
        app(IppPrinters::class)->discover();

        $this->assertSame('ipp-abcd12340000-3', app(IppPrinters::class)->print('ipp-abcd12340000', '/tmp/a.pdf', 1));
    }

    public function test_the_processor_routes_known_ipp_printers_to_ipp(): void
    {
        $this->configureHub();
        app(StateStore::class)->write('ipp', ['printers' => ['ipp-abc' => ['name' => 'ipp-abc']]]);
        Http::fake(['circlaris.test/*' => Http::response('pdf')]);
        $this->mock(IppPrinters::class, function ($mock): void {
            $mock->shouldReceive('find')->with('ipp-abc')->andReturn(['name' => 'ipp-abc']);
            $mock->shouldReceive('print')->once()->with('ipp-abc', Mockery::type('string'), 1)->andReturn('9');
        });

        $result = app(PrintJobProcessor::class)->process(
            ['id' => 42, 'filename' => 'a.pdf', 'queue' => null, 'printer' => 'ipp-abc', 'copies' => 1],
        );

        $this->assertSame(['printer' => 'ipp-abc', 'cups_job' => '9'], $result);
    }
}
