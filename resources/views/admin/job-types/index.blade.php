<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - Job Types</title>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p><a href="{{ route('admin.job-types.create') }}">Create job type</a></p>

    @if (session('success'))
        <p>{{ session('success') }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Label</th>
                <th>Pattern</th>
                <th>Assigned Printer</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($jobTypes as $jobType)
                <tr>
                    <td>{{ $jobType->label }}</td>
                    <td>{{ $jobType->pattern }}</td>
                    <td>{{ $jobType->cups_queue ?? 'Unassigned' }}</td>
                    <td>
                        <a href="{{ route('admin.job-types.edit', $jobType) }}">Edit</a>
                        <form method="POST" action="{{ route('admin.job-types.destroy', $jobType) }}" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No job types configured yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
