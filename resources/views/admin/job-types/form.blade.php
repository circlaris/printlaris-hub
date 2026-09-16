<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - {{ $jobType->exists ? 'Edit' : 'Create' }} Job Type</title>
</head>
<body>
    <h1>{{ $jobType->exists ? 'Edit' : 'Create' }} Job Type</h1>

    <form method="POST" action="{{ $jobType->exists ? route('admin.job-types.update', $jobType) : route('admin.job-types.store') }}">
        @csrf
        @if ($jobType->exists)
            @method('PUT')
        @endif

        <p>
            <label for="label">Label</label><br>
            <input id="label" name="label" type="text" value="{{ old('label', $jobType->label) }}" required>
        </p>

        <p>
            <label for="pattern">Pattern</label><br>
            <input id="pattern" name="pattern" type="text" value="{{ old('pattern', $jobType->pattern) }}" required>
        </p>

        <p>
            <label for="cups_queue">Assigned printer</label><br>
            <select id="cups_queue" name="cups_queue">
                <option value="">Unassigned</option>
                @foreach ($printers as $printer)
                    <option value="{{ $printer['name'] }}" {{ old('cups_queue', $jobType->cups_queue) == $printer['name'] ? 'selected' : '' }}>
                        {{ $printer['name'] }} ({{ ucfirst($printer['state']) }})
                    </option>
                @endforeach
            </select>
        </p>

        <p>
            <label for="sort_order">Order</label><br>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $jobType->sort_order ?? 0) }}">
        </p>

        <button type="submit">Save</button>
        <a href="{{ route('admin.job-types.index') }}">Cancel</a>
    </form>
</body>
</html>
