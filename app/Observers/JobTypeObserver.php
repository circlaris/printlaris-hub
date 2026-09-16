<?php

namespace App\Observers;

use App\Models\JobType;
use Illuminate\Support\Facades\Cache;

class JobTypeObserver
{
    public function created(JobType $jobType): void
    {
        Cache::forget('print-router:active-job-types');
    }

    public function updated(JobType $jobType): void
    {
        Cache::forget('print-router:active-job-types');
    }

    public function deleted(JobType $jobType): void
    {
        Cache::forget('print-router:active-job-types');
    }
}
