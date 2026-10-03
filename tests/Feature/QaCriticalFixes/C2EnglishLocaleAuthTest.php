<?php

namespace Tests\Feature\QaCriticalFixes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del bug crítico C2 (fix en el commit 8583c98).
 *
 * `lang/en/auth.php` tenía un apóstrofo sin escapar en la línea 46
 * ('Don't have an account yet?' sin `\` delante del apóstrofo), lo que
 * provocaba un ParseError de PHP en cualquier request que cargase
 * traducciones `auth.*` con locale `en`: login y registro devolvían 500
 * para navegadores con el idioma en inglés.
 *
 * Tests portados de Agent30AuthSettingsQaTest
 * (~/workspace/shemanager/qa/workers/agent-30/Agent30AuthSettingsQaTest.php):
 *   - test_english_locale_auth_pages_do_not_500
 *   - test_english_locale_login_errors_do_not_500
 */
class C2EnglishLocaleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_locale_auth_pages_do_not_500(): void
    {
        // Con navegador en inglés, el middleware SetLocale resuelve 'en' y
        // CUALQUIER traducción auth.* lanzaba ParseError -> 500.
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9');

        $this->get('/login')->assertStatus(200);
        $this->get('/register')->assertStatus(200);
    }

    public function test_english_locale_login_errors_do_not_500(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9');

        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_all_lang_files_have_valid_syntax(): void
    {
        // El QA pasó lint sobre todos los ficheros lang/*/*.php: solo fallaba
        // lang/en/auth.php. Cargar cada fichero dispara \ParseError si tiene
        // un error de sintaxis (el frankenphp php-cli no acepta `php -l`).
        $files = glob(base_path('lang/*/*.php'));

        $this->assertNotEmpty($files, 'No se encontraron ficheros lang/*/*.php');

        $errors = [];
        foreach ($files as $file) {
            try {
                $translations = require $file;

                if (! is_array($translations)) {
                    $errors[] = $file . ': no devuelve un array';
                }
            } catch (\ParseError $e) {
                $errors[] = $file . ' [ParseError]: ' . $e->getMessage();
            } catch (\Throwable $e) {
                $errors[] = $file . ' [' . get_class($e) . ']: ' . $e->getMessage();
            }
        }

        $this->assertEmpty(
            $errors,
            count($errors) . ' fichero(s) de idioma con errores de sintaxis:' . "\n" . implode("\n", $errors)
        );
    }
}
