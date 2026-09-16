@extends('layouts.admin')

@section('title', config('app.name') . ' - ' . ($jobType->exists ? 'Edit' : 'Create') . ' Job Type')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.job-types.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900">&larr; Back to job types</a>
        <h1 class="mt-2 text-xl font-semibold tracking-tight">{{ $jobType->exists ? 'Edit' : 'Create' }} job type</h1>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ $jobType->exists ? route('admin.job-types.update', $jobType) : route('admin.job-types.store') }}" class="space-y-5">
            @csrf
            @if ($jobType->exists)
                @method('PUT')
            @endif

            <div>
                <label for="label" class="block text-sm font-medium text-slate-700">Label</label>
                <input
                    id="label" name="label" type="text" required
                    value="{{ old('label', $jobType->label) }}"
                    placeholder="e.g. Invoices"
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                >
            </div>

            <div>
                <label for="pattern" class="block text-sm font-medium text-slate-700">Filename pattern</label>
                <input
                    id="pattern" name="pattern" type="text" required
                    value="{{ old('pattern', $jobType->pattern) }}"
                    placeholder="/^invoice-.*\.pdf$/i"
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                >
                <p class="mt-1 text-xs text-slate-500">A regular expression matched against the incoming filename.</p>
            </div>

            <div>
                <label for="cups_queue" class="block text-sm font-medium text-slate-700">Assigned printer</label>
                <select
                    id="cups_queue" name="cups_queue"
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                >
                    <option value="">Unassigned</option>
                    @foreach ($printers as $printer)
                        <option value="{{ $printer['name'] }}" @selected(old('cups_queue', $jobType->cups_queue) == $printer['name'])>
                            {{ $printer['name'] }} &mdash; {{ ucfirst($printer['state']) }}
                        </option>
                    @endforeach
                </select>
                @if ($printers->isEmpty())
                    <p class="mt-1 text-xs text-amber-600">No CUPS queues were found on this device.</p>
                @endif
            </div>

            <div>
                <label for="sort_order" class="block text-sm font-medium text-slate-700">Match order</label>
                <input
                    id="sort_order" name="sort_order" type="number" min="0"
                    value="{{ old('sort_order', $jobType->sort_order ?? 0) }}"
                    class="mt-1 block w-32 rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                >
                <p class="mt-1 text-xs text-slate-500">Lower numbers are matched first.</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-700">
                    Save
                </button>
                <a href="{{ route('admin.job-types.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900">Cancel</a>
            </div>
        </form>
    </div>
@endsection
