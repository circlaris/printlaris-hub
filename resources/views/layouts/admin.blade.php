<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
                <a href="{{ route('admin.job-types.index') }}" class="flex items-center gap-2">
                    <img src="{{ asset('favicon.png') }}" alt="" class="h-8 w-8 rounded-md">
                    <span class="text-base font-semibold tracking-tight">{{ config('app.name') }}</span>
                </a>
                <nav class="text-sm">
                    <a href="{{ route('admin.job-types.index') }}" class="font-medium text-slate-600 hover:text-slate-900">Job Types</a>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-5xl flex-1 px-6 py-10">
            @if (session('success'))
                <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="border-t border-slate-200 py-6 text-center text-xs text-slate-400">
            {{ config('app.name') }} &middot; internal print routing
        </footer>
    </div>
</body>
</html>
