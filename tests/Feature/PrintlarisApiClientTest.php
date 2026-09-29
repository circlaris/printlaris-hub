<?php

namespace Tests\Feature;

use App\Services\PrintlarisApiClient;
use App\Services\StateStore;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrintlarisApiClientTest extends TestCase
{
    public function test_no_job_is_reported_as_null(): void
    {
        $this->configureHub();
        Http::fake(['circlaris.test/printlaris/client/jobs/next' => Http::response(status: 204)]);

        $this->assertNull(app(PrintlarisApiClient::class)->nextJob());
    }

    public function test_a_job_is_normalised(): void
    {
        $this->configureHub();
        Http::fake(['circlaris.test/printlaris/client/jobs/next' => Http::response(['job' => [
            'id' => 7, 'filename' => 'label.pdf', 'queue' => null, 'printer' => 'zebra', 'copies' => 0,
        ]])]);

        $job = app(PrintlarisApiClient::class)->nextJob();

        $this->assertSame(['id' => 7, 'filename' => 'label.pdf', 'queue' => null, 'printer' => 'zebra', 'copies' => 1], $job);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer prl_valid'));
    }

    public function test_a_malformed_job_is_rejected(): void
    {
        $this->configureHub();
        Http::fake(['circlaris.test/*' => Http::response(['job' => ['filename' => 'label.pdf']])]);

        $this->expectExceptionMessage('invalid print job');

        app(PrintlarisApiClient::class)->nextJob();
    }

    public function test_contact_and_errors_are_recorded_in_the_state(): void
    {
        $this->configureHub();
        Http::fakeSequence('circlaris.test/*')->push(status: 204)->push(['message' => 'nope'], 401)->push(status: 204);
        $client = app(PrintlarisApiClient::class);
        $state = app(StateStore::class);

        $client->nextJob();
        $this->assertNotNull($state->read('connection')['last_contact_at']);

        try {
            $client->nextJob();
            $this->fail('Expected a request exception.');
        } catch (\Throwable) {
        }

        $this->assertSame('The key is not valid (anymore).', $state->read('connection')['last_error']);

        $client->nextJob();
        $this->assertNull($state->read('connection')['last_error']);
    }

    public function test_a_key_check_with_a_candidate_key_does_not_touch_the_stored_state(): void
    {
        $this->configureHub();
        Http::fake(['circlaris.test/*' => Http::response(['ok' => true, 'customer' => 'Marks', 'server_time' => 'x'])]);

        app(PrintlarisApiClient::class)->ping('prl_candidate');

        $this->assertSame([], app(StateStore::class)->read('connection'));
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer prl_candidate'));
    }

    public function test_a_non_https_endpoint_is_refused(): void
    {
        $this->configureHub();
        config(['printlaris.endpoint' => 'http://circlaris.test/printlaris/client']);

        $this->expectExceptionMessage('HTTPS');

        app(PrintlarisApiClient::class)->ping();
    }
}
