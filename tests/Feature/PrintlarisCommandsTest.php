<?php

namespace Tests\Feature;

use App\Services\CupsPrinterService;
use App\Services\PrintJobProcessor;
use App\Services\StateStore;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PrintlarisCommandsTest extends TestCase
{
    public function test_commands_do_nothing_without_a_key(): void
    {
        $this->artisan('printlaris:push-printers')->assertSuccessful();
        $this->artisan('printlaris:pull-config')->assertSuccessful();
        $this->artisan('printlaris:run --once')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_printers_are_pushed_and_remembered(): void
    {
        $this->configureHub();
        $printers = [['name' => 'zebra', 'state' => 'idle', 'description' => null, 'location' => null]];
        $this->mock(CupsPrinterService::class)->shouldReceive('listPrinters')->andReturn(collect($printers));
        Http::fake(['circlaris.test/printlaris/client/printers' => Http::response(status: 204)]);

        $this->artisan('printlaris:push-printers')->assertSuccessful();

        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['printers'] === $printers);
        $this->assertSame($printers, app(StateStore::class)->read('printers')['list']);
    }

    public function test_the_config_is_pulled_and_stored(): void
    {
        $this->configureHub();
        Http::fake(['circlaris.test/printlaris/client/config' => Http::response(['version' => 'abc', 'queues' => []])]);

        $this->artisan('printlaris:pull-config')->assertSuccessful();

        $state = app(StateStore::class);
        $this->assertSame('abc', $state->read('config')['version']);
        $this->assertNotNull($state->read('connection')['last_config_pull_at']);
    }

    public function test_a_failing_pull_keeps_the_previous_config(): void
    {
        $this->configureHub();
        app(StateStore::class)->write('config', ['version' => 'old', 'queues' => []]);
        Http::fake(['circlaris.test/*' => Http::response(status: 500)]);

        $this->artisan('printlaris:pull-config')->assertFailed();

        $this->assertSame('old', app(StateStore::class)->read('config')['version']);
    }

    public function test_the_scheduler_runs_both_jobs_every_minute(): void
    {
        $everyMinute = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => $event->expression === '* * * * *')
            ->map(fn ($event) => $event->command);

        $this->assertTrue($everyMinute->contains(fn ($command) => str_contains($command, 'printlaris:push-printers')));
        $this->assertTrue($everyMinute->contains(fn ($command) => str_contains($command, 'printlaris:pull-config')));
    }

    public function test_the_runner_prints_a_job_and_reports_success(): void
    {
        $this->configureHub();
        Http::fake([
            'circlaris.test/printlaris/client/jobs/next' => Http::response(['job' => ['id' => 5, 'filename' => 'a.pdf', 'printer' => 'zebra', 'copies' => 1]]),
            'circlaris.test/printlaris/client/jobs/5/result' => Http::response(status: 204),
        ]);
        $this->mock(PrintJobProcessor::class)->shouldReceive('process')->once()->andReturn(['printer' => 'zebra', 'cups_job' => 'zebra-1']);

        $this->artisan('printlaris:run --once')->assertSuccessful();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), 'jobs/5/result') && $request['status'] === 'printed');
        $this->assertSame('printed', app(StateStore::class)->recentJobs()[0]['status']);
    }

    public function test_the_runner_reports_a_failed_print_with_the_reason(): void
    {
        $this->configureHub();
        Http::fake([
            'circlaris.test/printlaris/client/jobs/next' => Http::response(['job' => ['id' => 5, 'filename' => 'a.pdf', 'printer' => 'zebra', 'copies' => 1]]),
            'circlaris.test/printlaris/client/jobs/5/result' => Http::response(status: 204),
        ]);
        $this->mock(PrintJobProcessor::class)->shouldReceive('process')->andThrow(new RuntimeException('out of paper'));

        $this->artisan('printlaris:run --once')->assertSuccessful();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), 'jobs/5/result')
            && $request['status'] === 'failed' && $request['error'] === 'out of paper');
    }

    public function test_the_runner_exits_with_failure_when_polling_fails(): void
    {
        $this->configureHub();
        Http::fake(['circlaris.test/*' => Http::response(status: 500)]);

        $this->artisan('printlaris:run --once')->assertFailed();
    }
}
