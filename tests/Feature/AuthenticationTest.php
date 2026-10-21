<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('dogru-sifre')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'dogru-sifre',
        ]);

        $response->assertRedirect(route('teklif.create'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('dogru-sifre')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'yanlis-sifre',
        ]);

        /* Kardeş panellerdeki kalıp: hata doğrulama hatası olarak döner. */
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create(['password' => Hash::make('dogru-sifre')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'dogru-sifre',
        ]);

        /* Kardeş panellerdeki kalıp: hata doğrulama hatası olarak döner. */
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
