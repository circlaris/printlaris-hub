@extends('layouts.admin')

@section('title', config('app.name') . ' - Job Types')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Job Types</h1>
            <p class="mt-1 text-sm text-slate-500">Filename patterns and the printer queue each one routes to.</p>
        </div>
        <a
            href="{{ route('admin.job-types.create') }}"
            class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-700"
        >
            + New job type
        </a>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3 font-medium">Label</th>
                    <th class="px-5 py-3 font-medium">Pattern</th>
                    <th class="px-5 py-3 font-medium">Printer</th>
                    <th class="px-5 py-3 font-medium">Order</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($jobTypes as $jobType)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium text-slate-900">{{ $jobType->label }}</td>
                        <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $jobType->pattern }}</td>
                        <td class="px-5 py-3">
                            @if ($jobType->cups_queue)
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                                    {{ $jobType->cups_queue }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">
                                    Unassigned
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-500">{{ $jobType->sort_order }}</td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.job-types.edit', $jobType) }}" class="font-medium text-slate-600 hover:text-slate-900">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route('admin.job-types.destroy', $jobType) }}" onsubmit="return confirm('Delete this job type?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-medium text-red-600 hover:text-red-800">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">
                            No job types configured yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
