<?php

namespace Tests\Feature;

use App\Services\CupsPrinterService;
use App\Services\PrintJobProcessor;
use App\Services\StateStore;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PrintJobProcessorTest extends TestCase
{
    /** @return array{id: int, filename: string, queue: ?string, printer: ?string, copies: int} */
    private function job(array $overrides = []): array
    {
        return ['id' => 42, 'filename' => 'label.pdf', 'queue' => null, 'printer' => 'zebra', 'copies' => 2, ...$overrides];
    }

    public function test_the_file_is_downloaded_printed_and_removed(): void
    {
        $this->configureHub();
        Http::fake(['circlaris.test/printlaris/client/jobs/42/file' => Http::response('pdf-bytes')]);
        $path = null;

        $this->mock(CupsPrinterService::class)
            ->shouldReceive('submit')
            ->once()
            ->with(Mockery::on(function (string $file) use (&$path): bool {
                $path = $file;

                return str_ends_with($file, '.pdf') && file_get_contents($file) === 'pdf-bytes';
            }), 'zebra', 2)
            ->andReturn('zebra-9');

        $result = app(PrintJobProcessor::class)->process($this->job());

        $this->assertSame(['printer' => 'zebra', 'cups_job' => 'zebra-9'], $result);
        $this->assertFileDoesNotExist($path);
    }

    public function test_the_temporary_file_is_removed_when_cups_fails(): void
    {
        $this->configureHub();
        Http::fake(['circlaris.test/*' => Http::response('pdf-bytes')]);
        $path = null;

        $this->mock(CupsPrinterService::class)
            ->shouldReceive('submit')
            ->andReturnUsing(function (string $file) use (&$path): never {
                $path = $file;

                throw new RuntimeException('lp failed');
            });

        try {
            app(PrintJobProcessor::class)->process($this->job());
            $this->fail('Expected the CUPS failure to bubble up.');
        } catch (RuntimeException) {
        }

        $this->assertFileDoesNotExist($path);
    }

    public function test_the_printer_falls_back_to_the_pulled_queue_config(): void
    {
        $this->configureHub();
        app(StateStore::class)->write('config', ['version' => 'x', 'queues' => [['key' => 'labels', 'printer' => 'zebra-labels']]]);
        Http::fake(['circlaris.test/*' => Http::response('pdf-bytes')]);
        $this->mock(CupsPrinterService::class)->shouldReceive('submit')->once()->with(Mockery::any(), 'zebra-labels', 2)->andReturnNull();

        $result = app(PrintJobProcessor::class)->process($this->job(['printer' => null, 'queue' => 'labels']));

        $this->assertSame('zebra-labels', $result['printer']);
    }

    public function test_a_job_without_a_resolvable_printer_fails_before_downloading(): void
    {
        $this->configureHub();

        $this->expectExceptionMessage('No printer could be resolved');

        try {
            app(PrintJobProcessor::class)->process($this->job(['printer' => null, 'queue' => 'unknown']));
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_printer_names_that_look_like_options_are_refused(): void
    {
        $this->configureHub();

        $this->expectExceptionMessage('not a valid CUPS queue name');

        app(PrintJobProcessor::class)->process($this->job(['printer' => '-o raw']));
    }
}
