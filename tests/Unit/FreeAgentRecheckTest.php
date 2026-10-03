<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Modules\Transfer\Services\AITransferMarketService;
use App\Modules\Transfer\Services\TransferCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * BAJA review (hardening; latent today by execution order): the free-agent
 * completion paths never re-checked that the player was still unattached.
 *
 * - TransferCompletionService::completeFreeAgentSigning() must not steal a
 *   player who signed elsewhere between negotiation and completion.
 * - AITransferMarketService::processFreeAgentSignings() wrote
 *   $alreadyTransferredSet but never read it in that method, and never
 *   re-checked team_id on fresh data before queueing the batched update.
 */
class FreeAgentRecheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_free_agent_signing_skips_a_player_who_is_no_longer_unattached(): void
    {
        $userTeam = Team::factory()->create(['name' => 'User FC']);
        $otherTeam = Team::factory()->create(['name' => 'Other FC']);
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $game = Game::factory()->create([
            'team_id' => $userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);

        // Stale $player instance as the completion path would receive it,
        // but the DB row shows she already signed elsewhere.
        $player = GamePlayer::factory()
            ->forGame($game)
            ->create(['team_id' => null, 'date_of_birth' => '1998-06-15']);
        GamePlayer::whereKey($player->id)->update(['team_id' => $otherTeam->id]);

        $offer = TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $userTeam->id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => 800_000,
            'offered_years' => 3,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => $game->current_date->copy()->addDays(14),
            'game_date' => $game->current_date,
        ]);

        app(TransferCompletionService::class)->completeFreeAgentSigning($game, $player, $offer);

        $this->assertSame($otherTeam->id, GamePlayer::whereKey($player->id)->value('team_id'), 'must not steal a contracted player');
        $this->assertSame(
            0,
            GameTransfer::where('game_id', $game->id)->where('game_player_id', $player->id)->count(),
            'no transfer row must be recorded for the skipped signing'
        );
        $this->assertSame(TransferOffer::STATUS_AGREED, $offer->refresh()->status, 'offer must not be marked completed');
    }

    public function test_ai_free_agent_signings_skip_already_transferred_players(): void
    {
        $team = Team::factory()->create(['name' => 'AI FC', 'parent_team_id' => null]);
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $game = Game::factory()->create([
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);

        $freeAgent = GamePlayer::factory()
            ->forGame($game)
            ->create([
                'team_id' => null,
                'date_of_birth' => '2001-06-15',
                'position' => 'Central Midfield',
                'overall_score' => 75,
                'tier' => 1,
            ]);

        // The method preserves a MIN_FREE_AGENT_POOL (15) cushion: seed
        // filler free agents so maxSignings > 0 and our target (the best
        // by ability) is processed first.
        for ($i = 0; $i < 19; $i++) {
            GamePlayer::factory()
                ->forGame($game)
                ->create([
                    'team_id' => null,
                    'date_of_birth' => '2001-06-15',
                    'position' => 'Central Midfield',
                    'overall_score' => 40,
                    'tier' => 1,
                ]);
        }

        $service = app(AITransferMarketService::class);
        $method = new \ReflectionMethod($service, 'processFreeAgentSignings');
        $method->setAccessible(true);

        // Control: with an empty transferred set the signing is queued.
        $updates = $this->invokeFreeAgentSignings($method, $service, $game, $team, $freeAgent, []);
        $this->assertContains(
            $freeAgent->id,
            array_column($updates, 'id'),
            'control: unattached free agent must be queued for signing'
        );

        // Hardening: a pre-seeded alreadyTransferredSet entry skips her
        // (other free agents may still be signed).
        $updates = $this->invokeFreeAgentSignings($method, $service, $game, $team, $freeAgent, [$freeAgent->id => true]);
        $this->assertNotContains(
            $freeAgent->id,
            array_column($updates, 'id'),
            'player in alreadyTransferredSet must be skipped'
        );
    }

    /**
     * @return array<int, array<string, mixed>> queued player updates
     */
    private function invokeFreeAgentSignings(
        \ReflectionMethod $method,
        AITransferMarketService $service,
        Game $game,
        Team $team,
        GamePlayer $freeAgent,
        array $alreadyTransferredSet,
    ): array {
        $teamRosters = new Collection([$team->id => new Collection()]);
        $teamAverages = new Collection([$team->id => 70]);
        $teams = new Collection([$team->id => $team]);
        $takenNumbers = new Collection();
        $playerUpdates = [];
        $transferInserts = [];
        $reputationLevels = new Collection([$team->id => ClubProfile::REPUTATION_LOCAL]);

        $method->invokeArgs($service, [
            $game,
            'summer',
            $teamRosters,
            $teamAverages,
            $teams,
            &$takenNumbers,
            &$playerUpdates,
            &$transferInserts,
            &$alreadyTransferredSet,
            $reputationLevels,
        ]);

        return $playerUpdates;
    }
}
