<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del bug medio M35 (QA agent-30).
 *
 * `POST /register/career` no tenía rate-limit: se podían crear cuentas
 * en masa con emails distintos. Ahora lleva `throttle:3,1`, siguiendo el
 * mismo patrón que las otras rutas POST sensibles de `routes/auth.php`
 * (`forgot-password`, `reset-password`).
 */
class M35RegisterThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(int $i): array
    {
        return [
            'name' => "M35 User {$i}",
            'email' => "m35user{$i}@example.com",
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ];
    }

    public function test_register_is_rate_limited(): void
    {
        // Los 3 primeros registros entran (límite 3 por minuto). Tras cada
        // uno hay que hacer logout: si no, el middleware `guest` redirige
        // los siguientes intentos a /dashboard sin llegar al throttle.
        for ($i = 1; $i <= 3; $i++) {
            $this->post('/register/career', $this->registerPayload($i))
                ->assertRedirect('/dashboard');
            $this->post('/logout')->assertRedirect('/');
        }

        $this->assertSame(3, User::where('email', 'like', 'm35user%@example.com')->count());

        // El 4.º, dentro del mismo minuto, es rechazado con 429.
        $this->post('/register/career', $this->registerPayload(4))
            ->assertStatus(429);

        $this->assertSame(3, User::where('email', 'like', 'm35user%@example.com')->count());
    }

    public function test_first_register_still_works(): void
    {
        $this->post('/register/career', $this->registerPayload(1))
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['email' => 'm35user1@example.com']);
    }
}
