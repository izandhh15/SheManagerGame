<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\Team;
use App\Models\TournamentSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TRIAGE-B G15: ShowTournamentSummary read $data[...] directly from the
 * free-form summary_data JSON blob — an old-schema snapshot persisted
 * across deploys 500ed the page. All reads are now ??-guarded.
 */
class TournamentSummaryNullSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_schema_summary_renders_without_500(): void
    {
        $user = User::factory()->create();

        $team = Team::factory()->create();
        $competition = Competition::factory()->league()->create();

        $summary = TournamentSummary::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => $competition->id,
            'tournament_date' => now()->toDateString(),
            'result_label' => 'champion',
            'your_record' => ['played' => 7, 'won' => 7, 'drawn' => 0, 'lost' => 0],
            // Old schema: most keys the view expects are missing.
            'summary_data' => [
                'player_team_id' => 'ESP',
                'teams' => [],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('tournament-summary.show', $summary->id))
            ->assertOk();
    }

    public function test_empty_summary_data_renders_without_500(): void
    {
        $user = User::factory()->create();

        $team = Team::factory()->create();
        $competition = Competition::factory()->league()->create();

        $summary = TournamentSummary::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => $competition->id,
            'tournament_date' => now()->toDateString(),
            'result_label' => 'group_stage',
            'your_record' => ['played' => 3, 'won' => 0, 'drawn' => 1, 'lost' => 2],
            // Minimal blob: none of the keys the view reads.
            'summary_data' => [],
        ]);

        $this->actingAs($user)
            ->get(route('tournament-summary.show', $summary->id))
            ->assertOk();
    }

    public function test_other_users_summary_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $team = Team::factory()->create();
        $competition = Competition::factory()->league()->create();

        $summary = TournamentSummary::create([
            'user_id' => $owner->id,
            'team_id' => $team->id,
            'competition_id' => $competition->id,
            'tournament_date' => now()->toDateString(),
            'result_label' => 'champion',
            'your_record' => ['played' => 7, 'won' => 7, 'drawn' => 0, 'lost' => 0],
            'summary_data' => [],
        ]);

        $this->actingAs($intruder)
            ->get(route('tournament-summary.show', $summary->id))
            ->assertForbidden();
    }
}
