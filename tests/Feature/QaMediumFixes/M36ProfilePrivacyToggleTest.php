<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del bug medio M36 (QA agent-30).
 *
 * `is_profile_public` siempre era `true`: el campo no estaba en las reglas
 * de `ProfileUpdateRequest` y ninguna vista lo exponía. Ahora el formulario
 * de perfil incluye el toggle (con las claves `profile.public_profile` /
 * `profile.public_profile_description`, ya presentes en los 5 idiomas) y
 * `PATCH /profile` lo persiste; `/manager/{username}` ya devolvía 404 para
 * perfiles privados y sigue haciéndolo.
 */
class M36ProfilePrivacyToggleTest extends TestCase
{
    use RefreshDatabase;

    private function validProfilePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'M36 User',
            'username' => 'm36user',
            'avatar' => 'blue',
            'locale' => 'es',
        ], $overrides);
    }

    public function test_user_can_make_profile_private(): void
    {
        $user = User::factory()->create(['username' => 'm36private']);

        $this->actingAs($user)
            ->patch('/profile', $this->validProfilePayload([
                'username' => 'm36private',
                'is_profile_public' => '0',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard');

        $this->assertFalse($user->refresh()->is_profile_public);

        // El perfil privado ya no es visible públicamente.
        $this->get('/manager/m36private')->assertNotFound();
    }

    public function test_user_can_make_profile_public_again(): void
    {
        $user = User::factory()->create([
            'username' => 'm36public',
            'is_profile_public' => false,
        ]);

        $this->get('/manager/m36public')->assertNotFound();

        $this->actingAs($user)
            ->patch('/profile', $this->validProfilePayload([
                'username' => 'm36public',
                'is_profile_public' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->refresh()->is_profile_public);
        $this->get('/manager/m36public')->assertOk();
    }

    public function test_profile_visibility_is_unchanged_when_field_is_absent(): void
    {
        $user = User::factory()->create(['username' => 'm36unchanged']);

        $this->assertTrue($user->is_profile_public);

        // PATCH sin el campo (p. ej. clientes antiguos): no se toca.
        $this->actingAs($user)
            ->patch('/profile', $this->validProfilePayload(['username' => 'm36unchanged']))
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->refresh()->is_profile_public);
    }

    public function test_profile_edit_page_renders_privacy_toggle(): void
    {
        $user = User::factory()->create(['username' => 'm36toggle']);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('is_profile_public', false);
    }
}
