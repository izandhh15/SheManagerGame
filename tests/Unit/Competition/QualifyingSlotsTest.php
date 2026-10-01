<?php

namespace Tests\Unit\Competition;

use Tests\TestCase;

/**
 * UEFA qualifying slots: Spain's champion goes straight to the UWCL league
 * phase; 2nd and 3rd go to the qualifying playoff (UCLQ). Same pattern for
 * the other big leagues; Italy sends its top 2 (1st UCL, 2nd UCLQ).
 */
class QualifyingSlotsTest extends TestCase
{
    private function slots(string $country, string $league): array
    {
        return config("countries.{$country}.continental_slots.{$league}", []);
    }

    public function test_spain_champion_direct_2nd_3rd_to_qualifying(): void
    {
        $slots = $this->slots('ES', 'ESP1');

        $this->assertSame([1], $slots['UCL'] ?? null);
        $this->assertSame([2, 3], $slots['UCLQ'] ?? null);
        $this->assertSame([4], $slots['UEL'] ?? null);
        $this->assertSame([5], $slots['UELQ'] ?? null);
    }

    public function test_big_leagues_follow_spain_pattern(): void
    {
        foreach (['EN' => 'ENG1', 'DE' => 'DEU1', 'FR' => 'FRA1'] as $country => $league) {
            $slots = $this->slots($country, $league);
            $this->assertSame([1], $slots['UCL'] ?? null, "{$country} UCL");
            $this->assertSame([2, 3], $slots['UCLQ'] ?? null, "{$country} UCLQ");
            $this->assertSame([4], $slots['UEL'] ?? null, "{$country} UEL");
            $this->assertSame([5], $slots['UELQ'] ?? null, "{$country} UELQ");
        }
    }

    public function test_italy_sends_top_two(): void
    {
        $slots = $this->slots('IT', 'ITA1');

        $this->assertSame([1], $slots['UCL'] ?? null);
        $this->assertSame([2], $slots['UCLQ'] ?? null);
        $this->assertSame([3], $slots['UEL'] ?? null);
        $this->assertSame([4], $slots['UELQ'] ?? null);
    }

    public function test_qualifying_competitions_are_knockout_not_swiss(): void
    {
        $es = config('countries.ES.support.continental', []);

        $this->assertSame('knockout_cup', $es['UCLQ']['handler'] ?? null);
        $this->assertSame('knockout_cup', $es['UELQ']['handler'] ?? null);
        $this->assertSame('swiss_format', $es['UCL']['handler'] ?? null);
        $this->assertSame('swiss_format', $es['UEL']['handler'] ?? null);
    }

    public function test_qualifying_has_own_config_class(): void
    {
        $class = config('countries.ES.continental_competitions.UCLQ.config_class');
        $this->assertSame(
            \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            $class
        );
    }
}
