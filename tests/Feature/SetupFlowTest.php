<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdmin;
use App\Livewire\Login;
use App\Livewire\Setup;
use App\Services\HubCredentials;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SetupFlowTest extends TestCase
{
    public function test_every_page_redirects_to_setup_until_the_hub_is_configured(): void
    {
        $this->get('/')->assertRedirect(route('setup'));
        $this->get('/login')->assertRedirect(route('setup'));
        $this->get('/setup')->assertOk();
    }

    public function test_an_invalid_key_is_rejected_and_nothing_is_stored(): void
    {
        Http::fake(['circlaris.test/*' => Http::response(['message' => 'Unauthorized'], 401)]);

        Livewire::test(Setup::class)
            ->set('key', 'prl_wrong')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('save')
            ->assertHasErrors('key')
            ->assertNoRedirect();

        $this->assertFalse(app(HubCredentials::class)->hasKey());
    }

    public function test_a_key_without_the_expected_prefix_is_not_sent_to_circlaris(): void
    {
        Livewire::test(Setup::class)
            ->set('key', 'nonsense')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('save')
            ->assertHasErrors('key');

        Http::assertNothingSent();
    }

    public function test_a_valid_key_is_stored_and_signs_the_admin_in(): void
    {
        Http::fake(['circlaris.test/printlaris/client/ping' => Http::response(['ok' => true, 'customer' => 'Marks', 'server_time' => now()->toIso8601String()])]);

        Livewire::test(Setup::class)
            ->set('key', 'prl_valid')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('save')
            ->assertRedirect(route('dashboard'));

        $credentials = app(HubCredentials::class);

        $this->assertSame('prl_valid', $credentials->key());
        $this->assertTrue($credentials->passwordMatches('secret-password'));
        $this->assertSame(true, session(EnsureAdmin::SESSION_KEY));
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer prl_valid'));
    }

    public function test_setup_cannot_be_reused_once_configured(): void
    {
        $this->configureHub();

        $this->get('/setup')->assertRedirect(route('dashboard'));
    }

    public function test_the_dashboard_requires_the_admin_password(): void
    {
        $this->configureHub();

        $this->get('/')->assertRedirect(route('login'));
        $this->withSession([EnsureAdmin::SESSION_KEY => true])->get('/')->assertOk()->assertSee('Status');
    }

    public function test_login_checks_the_password(): void
    {
        $this->configureHub(password: 'secret-password');

        Livewire::test(Login::class)->set('password', 'nope')->call('login')->assertHasErrors('password');
        Livewire::test(Login::class)->set('password', 'secret-password')->call('login')->assertRedirect(route('dashboard'));
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $this->configureHub();
        $component = Livewire::test(Login::class);

        foreach (range(1, 5) as $ignored) {
            $component->set('password', 'nope')->call('login');
        }

        $component->set('password', 'secret-password')->call('login')->assertHasErrors('password')->assertNoRedirect();
    }
}
