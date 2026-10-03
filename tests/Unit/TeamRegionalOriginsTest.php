<?php

namespace Tests\Unit;

use App\Modules\Squad\Configs\TeamRegionalOrigins;
use Tests\TestCase;

/**
 * BAJA review: TeamRegionalOrigins carried 8 dead mappings with masculine
 * club names ('Barakaldo CF', 'Bilbao Athletic', 'Arenas Club',
 * 'CA Osasuna Promesas', 'RCD Espanyol Barcelona', 'Girona FC',
 * 'Gimnàstic de Tarragona', 'CE Sabadell FC') that don't exist in
 * data/2026/{league}/teams.json — canteranas from those clubs silently fell back
 * to generic es_ES naming. Every mapped name must exist in the real data.
 */
class TeamRegionalOriginsTest extends TestCase
{
    public function test_every_mapped_team_exists_in_the_real_team_data(): void
    {
        $realNames = [];
        foreach (glob(base_path('data/2026/*/teams.json')) as $file) {
            $decoded = json_decode(file_get_contents($file), true);
            $teams = is_array($decoded) && array_is_list($decoded)
                ? $decoded
                : ($decoded['teams'] ?? $decoded['clubs'] ?? []);
            foreach ($teams as $team) {
                if (isset($team['name'])) {
                    $realNames[] = $team['name'];
                }
            }
        }

        $this->assertNotEmpty($realNames, 'test data must load real team names');

        $map = (new \ReflectionClass(TeamRegionalOrigins::class))->getConstant('TEAMS');

        foreach (array_keys($map) as $teamName) {
            $this->assertContains(
                $teamName,
                $realNames,
                "Regional mapping '{$teamName}' is dead: no such club in data/2026/*/teams.json."
            );
        }
    }

    public function test_removed_masculine_names_resolve_to_no_region(): void
    {
        foreach ([
            'Barakaldo CF',
            'Bilbao Athletic',
            'Arenas Club',
            'CA Osasuna Promesas',
            'RCD Espanyol Barcelona',
            'Girona FC',
            'Gimnàstic de Tarragona',
            'CE Sabadell FC',
        ] as $deadName) {
            $this->assertNull(
                TeamRegionalOrigins::regionFor($deadName),
                "Dead masculine mapping '{$deadName}' should no longer resolve."
            );
        }
    }

    public function test_real_filial_names_resolve_to_their_region(): void
    {
        $this->assertSame('basque', TeamRegionalOrigins::regionFor('Athletic Club B'));
        $this->assertSame('basque', TeamRegionalOrigins::regionFor('CA Osasuna B'));
        $this->assertSame('basque', TeamRegionalOrigins::regionFor('SD Eibar B'));
        $this->assertSame('catalan', TeamRegionalOrigins::regionFor('FC Barcelona B'));
        $this->assertSame('catalan', TeamRegionalOrigins::regionFor('FC Barcelona C'));
        $this->assertSame('catalan', TeamRegionalOrigins::regionFor('RCD Espanyol B'));
        $this->assertSame('catalan', TeamRegionalOrigins::regionFor('RCD Espanyol'));
    }

    public function test_unknown_team_returns_null(): void
    {
        $this->assertNull(TeamRegionalOrigins::regionFor('Valencia CF'));
        $this->assertNull(TeamRegionalOrigins::regionFor(null));
    }
}
