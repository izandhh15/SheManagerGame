<?php

namespace Tests\Unit;

use App\Support\LocaleFormat;
use Tests\TestCase;

/**
 * i18n-b review: ordinal suffixes must follow the user's locale
 * (previously 'º' was hardcoded for every locale in season-end views,
 * and 'st/nd/rd/th' for every locale in next-match-team-block).
 */
class LocaleFormatTest extends TestCase
{
    public function test_ordinal_spanish(): void
    {
        app()->setLocale('es');

        $this->assertSame('1º', LocaleFormat::ordinal(1));
        $this->assertSame('2º', LocaleFormat::ordinal(2));
        $this->assertSame('3º', LocaleFormat::ordinal(3));
        $this->assertSame('21º', LocaleFormat::ordinal(21));
    }

    public function test_ordinal_english(): void
    {
        app()->setLocale('en');

        $this->assertSame('1st', LocaleFormat::ordinal(1));
        $this->assertSame('2nd', LocaleFormat::ordinal(2));
        $this->assertSame('3rd', LocaleFormat::ordinal(3));
        $this->assertSame('4th', LocaleFormat::ordinal(4));
        $this->assertSame('11th', LocaleFormat::ordinal(11));
        $this->assertSame('12th', LocaleFormat::ordinal(12));
        $this->assertSame('13th', LocaleFormat::ordinal(13));
        $this->assertSame('21st', LocaleFormat::ordinal(21));
        $this->assertSame('22nd', LocaleFormat::ordinal(22));
        $this->assertSame('23rd', LocaleFormat::ordinal(23));
        $this->assertSame('111th', LocaleFormat::ordinal(111));
    }

    public function test_ordinal_french(): void
    {
        app()->setLocale('fr');

        $this->assertSame('1re', LocaleFormat::ordinal(1));
        $this->assertSame('2e', LocaleFormat::ordinal(2));
        $this->assertSame('3e', LocaleFormat::ordinal(3));
    }

    public function test_ordinal_german_and_portuguese(): void
    {
        app()->setLocale('de');
        $this->assertSame('1.', LocaleFormat::ordinal(1));
        $this->assertSame('3.', LocaleFormat::ordinal(3));

        app()->setLocale('pt');
        $this->assertSame('1.', LocaleFormat::ordinal(1));
        $this->assertSame('3.', LocaleFormat::ordinal(3));
    }

    public function test_js_locale_mapping(): void
    {
        $expected = [
            'es' => 'es-ES',
            'en' => 'en-IE', // euro zone: the game economy is in euros
            'de' => 'de-DE',
            'fr' => 'fr-FR',
            'pt' => 'pt-PT',
        ];

        foreach ($expected as $locale => $bcp47) {
            app()->setLocale($locale);
            $this->assertSame($bcp47, LocaleFormat::jsLocale(), "jsLocale for $locale");
        }
    }
}
