<?php

namespace Tests\Feature;

use App\Models\JobType;
use App\Services\PrintRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_the_first_matching_queue_and_ignores_unassigned_types(): void
    {
        JobType::create([
            'label' => 'Invoices',
            'pattern' => '/^invoice-.*\\.pdf$/i',
            'cups_queue' => 'floor-everywhere',
            'sort_order' => 1,
        ]);

        JobType::create([
            'label' => 'Unassigned Rule',
            'pattern' => '/^invoice-.*\\.pdf$/i',
            'cups_queue' => null,
            'sort_order' => 2,
        ]);

        JobType::create([
            'label' => 'Watch Labels',
            'pattern' => '/watch-label-.*\\.pdf/i',
            'cups_queue' => 'zebra-watch',
            'sort_order' => 3,
        ]);

        $this->assertSame('floor-everywhere', app(PrintRouter::class)->resolve('invoice-123.pdf'));
        $this->assertSame('zebra-watch', app(PrintRouter::class)->resolve('watch-label-20x75.pdf'));
    }

    public function test_it_returns_null_when_no_active_queue_matches(): void
    {
        JobType::create([
            'label' => 'Invoices',
            'pattern' => '/^invoice-.*\\.pdf$/i',
            'cups_queue' => null,
            'sort_order' => 1,
        ]);

        $this->assertNull(app(PrintRouter::class)->resolve('delivery-slip-123.pdf'));
    }

    public function test_print_request_fails_loudly_for_unmatched_filenames(): void
    {
        $response = $this->postJson('/print-jobs', [
            'filename' => 'unknown-file.pdf',
            'file_path' => '/tmp/unknown-file.pdf',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No printer queue could be resolved for this filename.');
    }
}
