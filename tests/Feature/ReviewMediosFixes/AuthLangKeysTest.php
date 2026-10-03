<?php

namespace Tests\Feature\ReviewMediosFixes;

use PHPUnit\Framework\TestCase;

/**
 * M-review fix 5/6 (grupos 17, TRIAGE-B): claves auth.* usadas por las
 * vistas de autenticación en los 5 idiomas.
 *
 * Causa: las vistas usaban __('Email') / __('Password') sin el prefijo
 * `auth.`, y sin punto el resolver busca lang/{locale}/Email.php
 * (inexistente) → siempre en inglés. También se añadió
 * `auth.resend_activation_email` para el reenvío de activación.
 *
 * No necesita la app Laravel: carga los ficheros PHP de lang directamente.
 */
class AuthLangKeysTest extends TestCase
{
    /** @var string[] */
    private const LOCALES = ['es', 'en', 'de', 'fr', 'pt'];

    /**
     * Claves que las vistas de auth resuelven con prefijo `auth.`
     * (deben existir y no estar vacías en los 5 idiomas).
     *
     * @var string[]
     */
    private const KEYS = [
        'Email',
        'Password',
        'Confirm Password',
        'Email Password Reset Link',
        'Reset Password',
        'Confirm',
        'resend_activation_email',
        'Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.',
        'This is a secure area of the application. Please confirm your password before continuing.',
    ];

    /** @var array<string, array<string, string>> */
    private static array $loaded = [];

    private static function lang(string $locale): array
    {
        if (! isset(self::$loaded[$locale])) {
            self::$loaded[$locale] = require __DIR__.'/../../../lang/'.$locale.'/auth.php';
        }

        return self::$loaded[$locale];
    }

    public static function localeProvider(): array
    {
        return array_map(fn ($l) => [$l], self::LOCALES);
    }

    /**
     * @dataProvider localeProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('localeProvider')]
    public function test_auth_keys_exist_in_locale(string $locale): void
    {
        $auth = self::lang($locale);

        foreach (self::KEYS as $key) {
            $this->assertArrayHasKey(
                $key,
                $auth,
                "lang/{$locale}/auth.php falta la clave '{$key}'"
            );
            $this->assertIsString($auth[$key]);
            $this->assertNotSame('', trim($auth[$key]), "lang/{$locale}/auth.php: '{$key}' vacía");
        }
    }

    public function test_no_unprefixed_translation_calls_in_auth_views(): void
    {
        $views = [
            'forgot-password' => ['auth.Email', 'auth.Email Password Reset Link'],
            'reset-password' => ['auth.Email', 'auth.Password', 'auth.Confirm Password', 'auth.Reset Password'],
            'confirm-password' => ['auth.Password', 'auth.Confirm'],
            'activation-sent' => ['auth.resend_activation_email'],
        ];

        foreach ($views as $view => $expectedKeys) {
            $src = file_get_contents(__DIR__.'/../../../resources/views/auth/'.$view.'.blade.php');

            foreach ($expectedKeys as $key) {
                $this->assertStringContainsString(
                    "__('{$key}')",
                    $src,
                    "{$view}.blade.php no usa __('{$key}')"
                );
            }

            // Sin prefijo auth. → el resolver busca lang/{locale}/Email.php → siempre inglés.
            $this->assertDoesNotMatchRegularExpression(
                "/__\(\s*'(Email|Password|Confirm Password|Confirm|Reset Password|Email Password Reset Link|Resend Activation Email)'\s*\)/",
                $src,
                "{$view}.blade.php aún usa una clave de traducción sin prefijo auth."
            );
        }
    }

    public function test_activation_sent_posts_to_resend_route(): void
    {
        $src = file_get_contents(__DIR__.'/../../../resources/views/auth/activation-sent.blade.php');

        $this->assertStringContainsString('method="POST"', $src);
        $this->assertStringContainsString("route('activation.resend')", $src);
        $this->assertStringNotContainsString("route('password.request')", $src);
    }
}
