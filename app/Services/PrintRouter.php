<?php

namespace App\Services;

use App\Models\JobType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PrintRouter
{
    public function resolve(string $filename): ?string
    {
        foreach ($this->activeRules() as $jobType) {
            $pattern = $this->normalizePattern((string) $jobType->pattern);

            if ($pattern === null) {
                continue;
            }

            if (@preg_match($pattern, $filename) === 1) {
                return $jobType->cups_queue;
            }
        }

        return null;
    }

    protected function normalizePattern(string $pattern): ?string
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return null;
        }

        if (preg_match('/^(.)(?:\\.|(?!\\1).)*\\1[imsxuADSUXJu]*$/', $pattern) === 1) {
            return $pattern;
        }

        return '/'.preg_quote($pattern, '/').'/i';
    }

    /**
     * @return Collection<int, JobType>
     */
    protected function activeRules(): Collection
    {
        return Cache::remember('print-router:active-job-types', 300, function () {
            return JobType::query()
                ->whereNotNull('cups_queue')
                ->whereNotNull('pattern')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });
    }
}
