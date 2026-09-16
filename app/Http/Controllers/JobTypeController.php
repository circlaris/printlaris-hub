<?php

namespace App\Http\Controllers;

use App\Models\JobType;
use App\Services\CupsPrinterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobTypeController extends Controller
{
    public function index(): View
    {
        $jobTypes = JobType::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.job-types.index', [
            'jobTypes' => $jobTypes,
            'printers' => app(CupsPrinterService::class)->listPrinters(),
        ]);
    }

    public function create(): View
    {
        return view('admin.job-types.form', [
            'jobType' => new JobType,
            'printers' => app(CupsPrinterService::class)->listPrinters(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'pattern' => ['required', 'string', 'max:255'],
            'cups_queue' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        JobType::create($validated);

        return redirect()->route('admin.job-types.index')->with('success', 'Job type created.');
    }

    public function edit(JobType $jobType): View
    {
        return view('admin.job-types.form', [
            'jobType' => $jobType,
            'printers' => app(CupsPrinterService::class)->listPrinters(),
        ]);
    }

    public function update(Request $request, JobType $jobType): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'pattern' => ['required', 'string', 'max:255'],
            'cups_queue' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $jobType->fill($validated);
        $jobType->save();

        return redirect()->route('admin.job-types.index')->with('success', 'Job type updated.');
    }

    public function destroy(JobType $jobType): RedirectResponse
    {
        $jobType->delete();

        return redirect()->route('admin.job-types.index')->with('success', 'Job type removed.');
    }
}
