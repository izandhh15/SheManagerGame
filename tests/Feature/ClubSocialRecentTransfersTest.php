<?php

namespace Tests\Feature;

use App\Http\Views\ShowClubSocial;
use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The signing/sale announcement dropdowns only list this season's actual
 * transfers — not the whole squad. Announcing a signing only makes sense
 * for someone you just signed; announcing a sale only for someone just sold.
 */
class ClubSocialRecentTransfersTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP3', 'country' => 'ES', 'tier' => 3]);

        $user = User::factory()->create();
        $this->team = Team::factory()->create(['name' => 'CD Getafe Femenino', 'country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->team->id,
            'competition_id' => 'ESP3',
            'country' => 'ES',
            'season' => '2026',
            'social_hype' => 0,
        ]);

        ClubProfile::create([
            'team_id' => $this->team->id,
            'reputation_level' => ClubProfile::REPUTATION_MODEST,
        ]);
    }

    private function player(string $name, ?string $teamId = null): GamePlayer
    {
        return GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $teamId ?? $this->team->id,
            'name' => $name,
            'overall_score' => 75,
        ]);
    }

    private function viewData(): array
    {
        return app(ShowClubSocial::class)($this->game->id)->getData();
    }

    public function test_signing_dropdown_lists_only_this_seasons_signings(): void
    {
        $newSigning = $this->player('Recién Fichada');
        $oldPlayer = $this->player('Veterana del Club');
        $lastSeason = $this->player('Fichaje del Año Pasado');

        GameTransfer::record($this->game->id, $newSigning->id, null, $this->team->id, 0, GameTransfer::TYPE_FREE_AGENT, '2026', 'summer');
        GameTransfer::record($this->game->id, $lastSeason->id, null, $this->team->id, 0, GameTransfer::TYPE_FREE_AGENT, '2025', 'summer');

        $names = $this->viewData()['recentSignings']->pluck('name')->all();

        $this->assertContains('Recién Fichada', $names);
        $this->assertNotContains('Veterana del Club', $names);
        $this->assertNotContains('Fichaje del Año Pasado', $names);
    }

    public function test_sale_dropdown_lists_only_this_seasons_sales(): void
    {
        $otherTeam = Team::factory()->create(['name' => 'Rival FC', 'country' => 'ES']);
        $sold = $this->player('Recién Vendida', $otherTeam->id);
        $staying = $this->player('Se Queda');

        GameTransfer::record($this->game->id, $sold->id, $this->team->id, $otherTeam->id, 500000, GameTransfer::TYPE_TRANSFER, '2026', 'summer');

        $names = $this->viewData()['recentSales']->pluck('name')->all();

        $this->assertContains('Recién Vendida', $names);
        $this->assertNotContains('Se Queda', $names);
    }

    public function test_empty_lists_when_no_transfers_this_season(): void
    {
        $this->player('Veterana del Club');

        $data = $this->viewData();

        $this->assertTrue($data['recentSignings']->isEmpty());
        $this->assertTrue($data['recentSales']->isEmpty());
    }
}
