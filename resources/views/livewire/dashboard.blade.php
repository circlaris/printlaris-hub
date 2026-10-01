<div wire:poll.5s>
    <section class="card">
        <div class="row">
            <h1>
                Status
                @if ($connected)
                    <span class="pill ok">Connected</span>
                @else
                    <span class="pill warn">Not connected</span>
                @endif
            </h1>
            <button type="button" class="secondary" wire:click="logout">Sign out</button>
        </div>

        <dl>
            <dt>Last contact</dt>
            <dd>{{ $lastContact?->diffForHumans() ?? 'never' }}</dd>
            <dt>Config pulled</dt>
            <dd>{{ $lastConfigPull?->diffForHumans() ?? 'never' }}</dd>
            <dt>Printers pushed</dt>
            <dd>{{ $lastPrinterPush?->diffForHumans() ?? 'never' }}</dd>
            @if ($lastError)
                <dt>Last error</dt>
                <dd class="error">{{ $lastError }}</dd>
            @endif
        </dl>
    </section>

    <section class="card">
        <h2>Connection key</h2>
        <p><code>{{ $maskedKey }}</code></p>

        <div class="actions">
            <button type="button" class="secondary" wire:click="checkConnection">Test connection</button>
            <button type="button" class="secondary" wire:click="$toggle('changingKey')">Change key</button>
        </div>

        @if ($checkMessage)
            <p class="{{ $checkPassed ? 'ok-text' : 'error' }}">{{ $checkMessage }}</p>
        @endif

        @if ($changingKey)
            <form wire:submit="changeKey" class="stack">
                <label>
                    <span>New connection key</span>
                    <input type="text" wire:model="newKey" autocomplete="off" spellcheck="false" placeholder="prl_…" />
                    @error('newKey') <small class="error">{{ $message }}</small> @enderror
                </label>
                <button type="submit" wire:loading.attr="disabled">Check key and save</button>
            </form>
        @endif
    </section>

    <section class="card">
        <div class="row">
            <h2>Printers</h2>
            <button type="button" class="secondary" wire:click="scanPrinters" wire:loading.attr="disabled" wire:target="scanPrinters">
                <span wire:loading.remove wire:target="scanPrinters">Scan for label printers</span>
                <span wire:loading wire:target="scanPrinters">Scanning…</span>
            </button>
        </div>
        @if ($scanMessage)
            <p class="muted">{{ $scanMessage }}</p>
        @endif
        @forelse ($printers as $printer)
            <div class="row line">
                <span>
                    <strong>{{ $printer['name'] }}</strong>
                    @if ($printer['description'])
                        <span class="muted">&middot; {{ $printer['description'] }}</span>
                    @endif
                    @if ($printer['address'] ?? null)
                        <span class="muted">&middot; {{ $printer['address'] }}</span>
                    @endif
                </span>
                <span class="pill">{{ $printer['state'] }}</span>
            </div>
        @empty
            <p class="muted">No CUPS printers reported yet.</p>
        @endforelse
    </section>

    <section class="card">
        <h2>Configuration</h2>
        @if ($config)
            <pre>{{ json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        @else
            <p class="muted">No configuration pulled yet.</p>
        @endif
    </section>

    <section class="card">
        <h2>Recent jobs</h2>
        @forelse ($jobs as $job)
            <div class="line">
                <div class="row">
                    <span>
                        <strong>#{{ $job['id'] }}</strong>
                        {{ $job['filename'] }}
                        @if ($job['printer'])
                            <span class="muted">&rarr; {{ $job['printer'] }}</span>
                        @endif
                    </span>
                    <span class="pill {{ $job['status'] === 'printed' ? 'ok' : 'warn' }}">{{ $job['status'] }}</span>
                </div>
                <small class="muted">{{ \Illuminate\Support\Carbon::parse($job['at'])->diffForHumans() }}</small>
                @if ($job['error'])
                    <small class="error">{{ $job['error'] }}</small>
                @endif
            </div>
        @empty
            <p class="muted">No jobs yet.</p>
        @endforelse
    </section>
</div>
