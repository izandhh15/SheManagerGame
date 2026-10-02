<?php

namespace Tests\Feature;

use App\Http\Views\ShowNationalSquadPicker;
use App\Models\GamePlayer;
use App\Modules\Season\Services\ClubFormService;
use Tests\TestCase;

class SquadPickerPositionGroupTest extends TestCase
{
    public function test_defence_position_maps_to_defender(): void
    {
        // Regression: 586 players carry position 'Defence' in the data files;
        // the picker sent them to the midfielders section (default arm).
        $this->assertSame('Defender', ShowNationalSquadPicker::positionGroup('Defence'));
        $this->assertSame('Defender', ShowNationalSquadPicker::positionGroup('Defender'));
        $this->assertSame('Defender', ShowNationalSquadPicker::positionGroup('Centre-Back'));
        $this->assertSame('Defender', ShowNationalSquadPicker::positionGroup('Left-Back'));
        $this->assertSame('Defender', ShowNationalSquadPicker::positionGroup('Right-Back'));
    }

    public function test_midfield_position_maps_to_midfielder(): void
    {
        $this->assertSame('Midfielder', ShowNationalSquadPicker::positionGroup('Midfield'));
        $this->assertSame('Midfielder', ShowNationalSquadPicker::positionGroup('Midfielder'));
        $this->assertSame('Midfielder', ShowNationalSquadPicker::positionGroup('Central Midfield'));
    }

    public function test_game_player_position_group_matches_picker(): void
    {
        $gp = new GamePlayer(['position' => 'Defence']);
        $this->assertSame('Defender', $gp->position_group);
    }

    public function test_synthetic_club_form_never_negative(): void
    {
        // Regression: keepers with overall < 60 got '-1g · -1a' from the
        // deterministic generator (negative quality * rand).
        for ($overall = 40; $overall <= 95; $overall++) {
            foreach (['Goalkeeper', 'Defender', 'Midfielder', 'Forward'] as $group) {
                $stats = ClubFormService::statsFor('test-player-' . $overall, $overall, $group, '2025', 1.0);
                $this->assertGreaterThanOrEqual(0, $stats['goals'], "goals negative for overall $overall / $group");
                $this->assertGreaterThanOrEqual(0, $stats['assists'], "assists negative for overall $overall / $group");
                $this->assertGreaterThanOrEqual(0, $stats['appearances']);
            }
        }
    }
}
