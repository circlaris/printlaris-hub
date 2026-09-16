<?php

namespace Database\Seeders;

use App\Models\JobType;
use Illuminate\Database\Seeder;

class JobTypeSeeder extends Seeder
{
    public function run(): void
    {
        JobType::query()->upsert([
            [
                'label' => 'Invoices',
                'pattern' => '/^invoice-.*\\.pdf$/i',
                'cups_queue' => 'floor-everywhere',
                'sort_order' => 1,
            ],
            [
                'label' => 'Delivery Slips',
                'pattern' => '/^delivery-slip-.*\\.pdf$/i',
                'cups_queue' => 'floor-everywhere',
                'sort_order' => 2,
            ],
            [
                'label' => 'Watch Labels',
                'pattern' => '/^watch-label-.*\\.pdf$/i',
                'cups_queue' => 'zebra-watch',
                'sort_order' => 3,
            ],
            [
                'label' => 'Jewelry Labels',
                'pattern' => '/^jewelry-label-.*\\.pdf$/i',
                'cups_queue' => 'zebra-jewelry',
                'sort_order' => 4,
            ],
        ], ['label']);
    }
}
