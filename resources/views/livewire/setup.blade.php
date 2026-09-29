<div>
    <section class="card">
        <h1>Connect this hub</h1>
        <p class="muted">
            Enter the connection key from circlaris (Einstellungen &rarr; Printlaris) and choose a password that
            protects this page. Nothing else is available until this is done.
        </p>

        <form wire:submit="save" class="stack">
            <label>
                <span>Connection key</span>
                <input type="text" wire:model="key" autocomplete="off" spellcheck="false" placeholder="prl_…" />
                @error('key') <small class="error">{{ $message }}</small> @enderror
            </label>

            <label>
                <span>Admin password</span>
                <input type="password" wire:model="password" autocomplete="new-password" />
                @error('password') <small class="error">{{ $message }}</small> @enderror
            </label>

            <label>
                <span>Repeat password</span>
                <input type="password" wire:model="password_confirmation" autocomplete="new-password" />
            </label>

            <button type="submit" wire:loading.attr="disabled">Check key and save</button>
        </form>
    </section>
</div>
