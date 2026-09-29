<div>
    <section class="card">
        <h1>Sign in</h1>

        <form wire:submit="login" class="stack">
            <label>
                <span>Admin password</span>
                <input type="password" wire:model="password" autocomplete="current-password" autofocus />
                @error('password') <small class="error">{{ $message }}</small> @enderror
            </label>

            <button type="submit" wire:loading.attr="disabled">Sign in</button>
        </form>
    </section>
</div>
