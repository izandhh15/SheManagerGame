<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\ManagerTrophy;
use App\Models\Team;
use App\Modules\Manager\Services\TrophySectionService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class TrophySectionServiceTest extends TestCase
{
    private TrophySectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TrophySectionService();
    }

    private function makeTrophy(string $type, array $attrs = []): ManagerTrophy
    {
        $trophy = new ManagerTrophy();
        $trophy->trophy_type = $type;
        $trophy->season = $attrs['season'] ?? '2026-2027';
        $trophy->custom_name = $attrs['custom_name'] ?? null;

        if ($type !== 'friendly_trophy') {
            $competition = new Competition();
            $competition->name = $attrs['competition_name'] ?? 'Liga F';
            $trophy->setRelation('competition', $competition);
        }

        $team = new Team();
        $team->name = $attrs['team_name'] ?? 'Valencia CF';
        $trophy->setRelation('team', $team);

        return $trophy;
    }

    public function test_section_for_type_mapping(): void
    {
        $this->assertSame('leagues', $this->service->sectionForType('league'));
        $this->assertSame('cups', $this->service->sectionForType('cup'));
        $this->assertSame('supercups', $this->service->sectionForType('supercup'));
        $this->assertSame('international', $this->service->sectionForType('european'));
        $this->assertSame('preseason', $this->service->sectionForType('friendly_trophy'));
    }

    public function test_unknown_trophy_type_goes_to_other_section(): void
    {
        $this->assertSame('other', $this->service->sectionForType('mystery_type'));
        $this->assertSame('other', $this->service->sectionForType(''));
    }

    public function test_trophies_group_into_correct_sections_in_order(): void
    {
        $trophies = new Collection([
            $this->makeTrophy('league', ['competition_name' => 'Liga F', 'season' => '2025-2026']),
            $this->makeTrophy('cup', ['competition_name' => 'Copa de la Reina', 'season' => '2025-2026']),
            $this->makeTrophy('supercup', ['competition_name' => 'Supercopa de España', 'season' => '2025-2026']),
            $this->makeTrophy('european', ['competition_name' => 'UWCL', 'season' => '2025-2026']),
            $this->makeTrophy('friendly_trophy', ['custom_name' => 'Trofeu TM', 'season' => '2026-2027']),
            $this->makeTrophy('mystery_type', ['competition_name' => 'Cosa rara', 'season' => '2024-2025']),
        ]);

        $sections = $this->service->groupTrophies($trophies);

        $this->assertSame(
            ['leagues', 'cups', 'supercups', 'international', 'preseason', 'other'],
            array_column($sections, 'key'),
        );

        $byKey = [];
        foreach ($sections as $section) {
            $byKey[$section['key']] = $section;
        }

        $this->assertSame(['Liga F'], array_column($byKey['leagues']['trophies'], 'name'));
        $this->assertSame(['Copa de la Reina'], array_column($byKey['cups']['trophies'], 'name'));
        $this->assertSame(['Supercopa de España'], array_column($byKey['supercups']['trophies'], 'name'));
        $this->assertSame(['UWCL'], array_column($byKey['international']['trophies'], 'name'));
        $this->assertSame(['Trofeu TM'], array_column($byKey['preseason']['trophies'], 'name'));
        $this->assertSame(['Cosa rara'], array_column($byKey['other']['trophies'], 'name'));

        // Every section carries a title, subtitle and a count.
        foreach ($sections as $section) {
            $this->assertNotEmpty($section['title']);
            $this->assertNotEmpty($section['subtitle']);
            $this->assertSame(count($section['trophies']), $section['count']);
        }
    }

    public function test_friendly_trophy_shows_custom_name(): void
    {
        $trophy = $this->makeTrophy('friendly_trophy', ['custom_name' => 'Trofeu TM']);

        $this->assertSame('Trofeu TM', $this->service->displayName($trophy));

        $sections = $this->service->groupTrophies(new Collection([$trophy]));

        $this->assertCount(1, $sections);
        $this->assertSame('preseason', $sections[0]['key']);
        $this->assertSame('Trofeu TM', $sections[0]['trophies'][0]['name']);
    }

    public function test_empty_trophy_list_returns_no_sections(): void
    {
        $this->assertSame([], $this->service->groupTrophies(new Collection()));
    }
}
