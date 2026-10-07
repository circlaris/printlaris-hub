<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>{{ config('app.name') }}</title>
        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png" />
        <link rel="stylesheet" href="{{ asset('css/printlaris.css') }}?v={{ filemtime(public_path('css/printlaris.css')) }}" />
        @if (is_file(public_path('hot')))
            <script type="module" src="{{ trim(file_get_contents(public_path('hot'))) }}/@@vite/client"></script>
        @endif
    </head>
    <body>
        <main class="page">
            <header class="brand">
                <img src="{{ asset('favicon.png') }}" alt="" width="32" height="32" />
                <span>{{ config('app.name') }}</span>
                @php($lanIp = app(\App\Services\CupsPrinterService::class)->lanIpAddress())
                <span class="brand-meta">
                    @if ($lanIp)
                        <span>{{ $lanIp }}</span>
                    @endif
                </span>
            </header>

            {{ $slot }}
        </main>
    </body>
</html>
