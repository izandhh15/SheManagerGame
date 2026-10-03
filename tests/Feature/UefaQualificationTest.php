<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\SeasonArchiveProcessor;
use App\Modules\Season\Processors\UefaQualificationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UefaQualificationTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private CountryConfig $countryConfig;

    /** @var array<string, Team[]> country => teams */
    private array $teamsByCountry = [];

    /** @var Team[] extra EUR pool teams with no configured country */
    private array $eurPoolTeams = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->countryConfig = app(CountryConfig::class);

        // Create competitions
        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        Competition::factory()->league()->create(['id' => 'ENG1', 'country' => 'EN', 'tier' => 1]);
        Competition::factory()->league()->create(['id' => 'DEU1', 'country' => 'DE', 'tier' => 1]);
        Competition::factory()->league()->create(['id' => 'ITA1', 'country' => 'IT', 'tier' => 1]);
        Competition::factory()->league()->create(['id' => 'FRA1', 'country' => 'FR', 'tier' => 1]);

        // Segunda División (for relegation tests)
        Competition::factory()->league()->create(['id' => 'ESP2', 'country' => 'ES', 'tier' => 2]);

        // Copa del Rey (for cup winner tests)
        Competition::factory()->create([
            'id' => 'ESPCUP',
            'name' => 'Copa de la Reina',
            'country' => 'ES',
            'type' => 'cup',
            'role' => Competition::ROLE_DOMESTIC_CUP,
            'handler_type' => 'knockout_cup',
        ]);

        // EUR team pool competition
        Competition::factory()->create([
            'id' => 'EUR',
            'name' => 'European Pool',
            'country' => 'EU',
            'type' => 'league',
            'role' => Competition::ROLE_TEAM_POOL,
            'handler_type' => 'team_pool',
        ]);

        // Swiss format competitions
        Competition::factory()->create([
            'id' => 'UCL',
            'name' => 'Champions League',
            'country' => 'EU',
            'type' => 'cup',
            'role' => Competition::ROLE_EUROPEAN,
            'scope' => Competition::SCOPE_CONTINENTAL,
            'handler_type' => 'swiss_format',
        ]);
        Competition::factory()->create([
            'id' => 'UEL',
            'name' => 'Europa League',
            'country' => 'EU',
            'type' => 'cup',
            'role' => Competition::ROLE_EUROPEAN,
            'scope' => Competition::SCOPE_CONTINENTAL,
            'handler_type' => 'swiss_format',
        ]);
        // Qualifying playoffs (seedInitialContinentalEntries writes entries
        // for them from the configured continental_slots).
        Competition::factory()->create([
            'id' => 'UCLQ',
            'name' => 'UWCL Qualifying',
            'country' => 'EU',
            'type' => 'cup',
            'role' => Competition::ROLE_EUROPEAN,
            'scope' => Competition::SCOPE_CONTINENTAL,
            'handler_type' => 'knockout_cup',
        ]);
        Competition::factory()->create([
            'id' => 'UELQ',
            'name' => 'Europa Cup Qualifying',
            'country' => 'EU',
            'type' => 'cup',
            'role' => Competition::ROLE_EUROPEAN,
            'scope' => Competition::SCOPE_CONTINENTAL,
            'handler_type' => 'knockout_cup',
        ]);
        $user = User::factory()->create();
        $userTeam = Team::factory()->create(['name' => 'User Team', 'country' => 'ES']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
        ]);

        // Create teams per configured country with standings.
        // The user team is placed at position 1 in ES so it qualifies for UCL,
        // which is required for filler allocation (only the user's competition gets filled).
        $this->createCountryTeamsWithStandings('ES', 'ESP1', 16, $userTeam);
        $this->createCountryTeamsWithStandings('EN', 'ENG1', 14);
        $this->createCountryTeamsWithStandings('DE', 'DEU1', 14);
        $this->createCountryTeamsWithStandings('IT', 'ITA1', 12);
        $this->createCountryTeamsWithStandings('FR', 'FRA1', 12);

        // Create EUR pool teams (non-configured countries) — used as fillers
        // for the user's Swiss format competition (only one gets filled to 36)
        $eurCountries = ['PT', 'NL', 'BE', 'TR', 'GR', 'AT', 'PL', 'CZ', 'RO', 'RS', 'HR', 'NO', 'CH', 'IL', 'DK', 'SE', 'UA', 'SC'];
        for ($i = 0; $i < 80; $i++) {
            $country = $eurCountries[$i % count($eurCountries)];
            $team = Team::factory()->create(['country' => $country]);
            $this->eurPoolTeams[] = $team;

            // Register in the EUR competition pool
            CompetitionTeam::create([
                'competition_id' => 'EUR',
                'team_id' => $team->id,
                'season' => '2025',
            ]);

            // Create GamePlayer records for squad presence
            $this->createPlayersForTeam($team, 5_000_000_00 - ($i * 50_000_00));
        }

        // Seed initial CompetitionEntry records for UCL and UEL
        // (mimicking what the initial game setup would create)
        $this->seedInitialContinentalEntries();
    }

    public function test_ucl_has_36_entries_after_qualification(): void
    {
        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();

        $processor->process($this->game, $data);

        $uclCount = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UCL')
            ->count();

        $this->assertEquals(36, $uclCount, "UCL should have exactly 36 entries, got {$uclCount}");
    }

    public function test_uel_only_filled_when_user_participates(): void
    {
        // User team is in UCL (ES position 1), so UEL should NOT be filled to 36.
        // It should only have its qualified teams from configured countries.
        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();

        $processor->process($this->game, $data);

        $uelCount = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UEL')
            ->count();

        // Women's slots: every country's UEL allocation is a single league
        // position (ES/EN/DE/FR: 4th, IT: 3rd) and cup_winner_slot is empty,
        // so no unclaimed cup place cascades down the table either.
        // ES=1, EN=1, DE=1, IT=1, FR=1 = 5 qualified teams (no fillers).
        $this->assertEquals(5, $uelCount, "UEL should only have qualified teams (no fillers), got {$uelCount}");
    }

    public function test_uel_winner_qualifies_for_ucl(): void
    {
        // Pick a UEL team as the winner
        $uelWinner = $this->eurPoolTeams[0];

        // Ensure the winner is in UEL entries (not UCL)
        CompetitionEntry::updateOrCreate(
            [
                'game_id' => $this->game->id,
                'competition_id' => 'UEL',
                'team_id' => $uelWinner->id,
            ],
            ['entry_round' => 1]
        );

        $data = $this->makeTransitionData();
        $data->setMetadata(SeasonTransitionData::META_UEL_WINNER, $uelWinner->id);

        $processor = app(UefaQualificationProcessor::class);
        $processor->process($this->game, $data);

        // UEL winner should now be in UCL
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UCL')
                ->where('team_id', $uelWinner->id)
                ->exists(),
            'UEL winner should be in UCL entries'
        );
    }

    public function test_ucl_winner_qualifies_for_next_ucl(): void
    {
        // Pick an EUR pool team as the UCL winner (not in any configured country's league)
        $uclWinner = $this->eurPoolTeams[0];

        // Make sure they are NOT already in next season's UCL
        CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UCL')
            ->where('team_id', $uclWinner->id)
            ->delete();

        $data = $this->makeTransitionData();
        $data->setMetadata(SeasonTransitionData::META_UCL_WINNER, $uclWinner->id);

        $processor = app(UefaQualificationProcessor::class);
        $processor->process($this->game, $data);

        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UCL')
                ->where('team_id', $uclWinner->id)
                ->exists(),
            'Defending UCL winner should auto-qualify for next season UCL'
        );

        $uclCount = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UCL')
            ->count();
        $this->assertEquals(36, $uclCount, "UCL should still have exactly 36 entries, got {$uclCount}");
    }

    public function test_ucl_winner_already_qualified_via_league_is_not_duplicated(): void
    {
        // Position 1 in ESP1 — already qualifies for UCL via league finish
        $uclWinner = $this->teamsByCountry['ES'][0];

        $data = $this->makeTransitionData();
        $data->setMetadata(SeasonTransitionData::META_UCL_WINNER, $uclWinner->id);

        $processor = app(UefaQualificationProcessor::class);
        $processor->process($this->game, $data);

        $uclCount = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UCL')
            ->count();

        $this->assertEquals(36, $uclCount, "UCL should still have exactly 36 entries, got {$uclCount}");
    }

    public function test_ucl_winner_in_uel_via_league_is_upgraded_and_uel_spot_cascades(): void
    {
        // Position 4 in ESP1 — qualifies for UEL via league. If they also win
        // the UCL, they must move up to UCL and the vacated UEL spot must
        // cascade to the next non-qualified Spanish team (position 5).
        $uclWinner = $this->teamsByCountry['ES'][3]; // position 4 = UEL

        $data = $this->makeTransitionData();
        $data->setMetadata(SeasonTransitionData::META_UCL_WINNER, $uclWinner->id);

        $processor = app(UefaQualificationProcessor::class);
        $processor->process($this->game, $data);

        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UCL')
                ->where('team_id', $uclWinner->id)
                ->exists(),
            'UCL winner from UEL slot should be upgraded to UCL'
        );

        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $uclWinner->id)
                ->exists(),
            'UCL winner should not still hold a UEL spot'
        );

        $nextTeam = $this->teamsByCountry['ES'][4]; // position 5
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $nextTeam->id)
                ->exists(),
            'Vacated UEL spot should cascade to next non-qualified Spanish team'
        );
    }

    public function test_uel_winner_already_in_ucl_is_not_duplicated(): void
    {
        // Pick a team that's already in UCL (from standings-based qualification)
        $espTeam1 = $this->teamsByCountry['ES'][0]; // Position 1 in ESP1 standings

        $data = $this->makeTransitionData();
        $data->setMetadata(SeasonTransitionData::META_UEL_WINNER, $espTeam1->id);

        $processor = app(UefaQualificationProcessor::class);
        $processor->process($this->game, $data);

        // Should still have exactly 36 entries (not 37)
        $uclCount = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UCL')
            ->count();

        $this->assertEquals(36, $uclCount, "UCL should still have exactly 36 entries, got {$uclCount}");
    }

    public function test_no_team_appears_in_both_ucl_and_uel(): void
    {
        $data = $this->makeTransitionData();

        $processor = app(UefaQualificationProcessor::class);
        $processor->process($this->game, $data);

        $uclTeams = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UCL')
            ->pluck('team_id')
            ->toArray();

        $uelTeams = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UEL')
            ->pluck('team_id')
            ->toArray();

        $overlap = array_intersect($uclTeams, $uelTeams);
        $this->assertEmpty($overlap, 'No team should appear in both UCL and UEL');
    }

    public function test_archive_processor_captures_uel_winner_from_cup_tie(): void
    {
        $winnerTeam = $this->eurPoolTeams[0];

        // Create a completed UEL final cup tie
        CupTie::create([
            'game_id' => $this->game->id,
            'competition_id' => 'UEL',
            'round_number' => 5, // SwissKnockoutGenerator::ROUND_FINAL
            'home_team_id' => $winnerTeam->id,
            'away_team_id' => $this->eurPoolTeams[1]->id,
            'winner_id' => $winnerTeam->id,
            'completed' => true,
        ]);

        $processor = app(SeasonArchiveProcessor::class);
        $data = $this->makeTransitionData();

        $result = $processor->process($this->game, $data);

        $this->assertEquals(
            $winnerTeam->id,
            $result->getMetadata(SeasonTransitionData::META_UEL_WINNER),
            'SeasonArchiveProcessor should capture UEL winner from cup tie'
        );
    }

    public function test_archive_processor_picks_random_uel_entry_when_no_cup_tie(): void
    {
        // No UEL cup ties exist, but UEL entries do
        CompetitionEntry::updateOrCreate(
            [
                'game_id' => $this->game->id,
                'competition_id' => 'UEL',
                'team_id' => $this->eurPoolTeams[0]->id,
            ],
            ['entry_round' => 1]
        );

        $processor = app(SeasonArchiveProcessor::class);
        $data = $this->makeTransitionData();

        $result = $processor->process($this->game, $data);

        $this->assertNotNull(
            $result->getMetadata(SeasonTransitionData::META_UEL_WINNER),
            'SeasonArchiveProcessor should pick a random UEL entry when no cup tie exists'
        );
    }

    public function test_non_european_teams_are_not_selected_as_fillers(): void
    {
        // Create non-European teams with GamePlayer records (e.g. from World Cup)
        $nonEuropeanTeams = [];
        foreach (['BR', 'AR', 'MX', 'JP', 'KR'] as $country) {
            $team = Team::factory()->create(['country' => $country]);
            $nonEuropeanTeams[] = $team;
            $this->createPlayersForTeam($team, 99_999_999_99); // Very high value
        }

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();

        $processor->process($this->game, $data);

        $swissEntryTeamIds = CompetitionEntry::where('game_id', $this->game->id)
            ->whereIn('competition_id', ['UCL', 'UEL'])
            ->pluck('team_id')
            ->toArray();

        foreach ($nonEuropeanTeams as $team) {
            $this->assertNotContains(
                $team->id,
                $swissEntryTeamIds,
                "Non-European team {$team->country} should not be in any UEFA competition"
            );
        }
    }

    // =========================================
    // Cup winner + UEL winner edge cases
    // =========================================

    public function test_cup_winner_outside_european_places_gets_no_european_place(): void
    {
        // Team at position 10 wins the Copa de la Reina. Women's football
        // configures no cup_winner_slot, so the cup winner takes no
        // European place at all — the league table alone decides.
        $cupWinner = $this->teamsByCountry['ES'][9]; // position 10
        $this->createCupFinal('ESPCUP', $cupWinner->id);

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $processor->process($this->game, $data);

        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $cupWinner->id)
                ->exists(),
            'Cup winner outside the European places should not take a UEL spot'
        );
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UCL')
                ->where('team_id', $cupWinner->id)
                ->exists(),
            'Cup winner outside the European places should not take a UCL spot'
        );
    }

    public function test_cup_winner_already_in_ucl_gets_no_extra_spot(): void
    {
        // Team at position 1 (already UCL via league) wins the Copa de la
        // Reina. There is no cup place to cascade, so position 5 stays out
        // of Europe.
        $cupWinner = $this->teamsByCountry['ES'][0]; // position 1 = UCL
        $this->createCupFinal('ESPCUP', $cupWinner->id);

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $processor->process($this->game, $data);

        // Cup winner should be in UCL (via league), NOT in UEL
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UCL')
                ->where('team_id', $cupWinner->id)
                ->exists()
        );
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $cupWinner->id)
                ->exists()
        );

        // Position 5 gets nothing: no cup place exists to cascade down.
        $fifth = $this->teamsByCountry['ES'][4]; // position 5
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $fifth->id)
                ->exists(),
            'Position 5 should stay out of Europe when the cup pays no place'
        );
    }

    public function test_cup_and_uel_winner_only_appears_in_ucl(): void
    {
        // Team at position 10 wins the Copa de la Reina AND the UEL. The cup
        // pays no European place; the UEL title promotes them to the UCL.
        $team = $this->teamsByCountry['ES'][9]; // position 10
        $this->createCupFinal('ESPCUP', $team->id);

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $data->setMetadata(SeasonTransitionData::META_UEL_WINNER, $team->id);

        $processor->process($this->game, $data);

        // Should be in UCL (via UEL winner upgrade)
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UCL')
                ->where('team_id', $team->id)
                ->exists(),
            'Cup+UEL winner should be in UCL'
        );

        // Should NOT be in UEL
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $team->id)
                ->exists(),
            'Cup+UEL winner should NOT remain in UEL'
        );
    }

    public function test_uel_winner_vacated_uel_spot_cascades_to_next_team(): void
    {
        // Team at position 4 (the league's UEL place) wins the UEL and is
        // promoted to the UCL. The vacated UEL spot must cascade to the next
        // non-qualified Spanish team (position 5).
        $team = $this->teamsByCountry['ES'][3]; // position 4 = UEL

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $data->setMetadata(SeasonTransitionData::META_UEL_WINNER, $team->id);

        $processor->process($this->game, $data);

        $nextTeam = $this->teamsByCountry['ES'][4]; // position 5
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $nextTeam->id)
                ->exists(),
            'Vacated UEL spot should cascade to next non-qualified Spanish team'
        );
    }

    public function test_no_team_appears_in_multiple_european_competitions(): void
    {
        // Team at position 10 wins Copa AND UEL — complex scenario
        $team = $this->teamsByCountry['ES'][9];
        $this->createCupFinal('ESPCUP', $team->id);

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $data->setMetadata(SeasonTransitionData::META_UEL_WINNER, $team->id);

        $processor->process($this->game, $data);

        $entries = CompetitionEntry::where('game_id', $this->game->id)
            ->whereIn('competition_id', ['UCL', 'UEL'])
            ->get()
            ->groupBy('team_id');

        $duplicates = $entries->filter(fn ($group) => $group->count() > 1);
        $this->assertTrue(
            $duplicates->isEmpty(),
            'No team should appear in multiple European competitions. Duplicates: ' .
            $duplicates->map(fn ($group, $teamId) => $teamId . ' in ' . $group->pluck('competition_id')->implode(', '))->implode('; ')
        );
    }

    // =========================================
    // Filler team edge cases
    // =========================================

    public function test_relegated_team_is_not_filler_in_european_competitions(): void
    {
        // Register a Spanish team (position 15) in the EUR pool so it could be a filler
        $relegatedTeam = $this->teamsByCountry['ES'][14]; // position 15
        CompetitionTeam::create([
            'competition_id' => 'EUR',
            'team_id' => $relegatedTeam->id,
            'season' => '2025',
        ]);

        // Simulate relegation: move team to ESP2
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP2',
            'team_id' => $relegatedTeam->id,
            'entry_round' => 1,
        ]);

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $processor->process($this->game, $data);

        $inEuropean = CompetitionEntry::where('game_id', $this->game->id)
            ->whereIn('competition_id', ['UCL', 'UEL'])
            ->where('team_id', $relegatedTeam->id)
            ->exists();

        $this->assertFalse($inEuropean, 'A team from a configured country should never be a filler');
    }

    public function test_configured_country_teams_never_used_as_fillers(): void
    {
        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $processor->process($this->game, $data);

        $configuredCountries = ['ES', 'EN', 'DE', 'IT', 'FR'];

        foreach (['UCL', 'UEL'] as $competitionId) {
            $entries = CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', $competitionId)
                ->pluck('team_id')
                ->toArray();

            // Get teams from configured countries that are in this competition
            $configuredTeamsInComp = Team::whereIn('id', $entries)
                ->whereIn('country', $configuredCountries)
                ->pluck('id')
                ->toArray();

            // Every configured-country team in the competition must have qualified
            // via league standings (not as a filler). Check they're in qualifying positions.
            foreach ($configuredTeamsInComp as $teamId) {
                $team = Team::find($teamId);
                $slots = $this->countryConfig->continentalSlots($team->country);
                $qualifyingTeamIds = [];
                $allDeclaredPositions = [];
                foreach ($slots as $leagueId => $allocations) {
                    foreach ($allocations as $continentalId => $positions) {
                        $allDeclaredPositions = array_merge($allDeclaredPositions, $positions);
                        if ($continentalId === $competitionId) {
                            foreach ($positions as $pos) {
                                $qualifyingTeamIds[] = $this->teamsByCountry[$team->country][$pos - 1]->id ?? null;
                            }
                        }
                    }
                }

                // A cup place whose cup wasn't played cascades to the next
                // teams in the table — a legitimate qualification, not a
                // filler. One extra position is reachable per cup slot.
                $cupSlots = $this->countryConfig->cupWinnerSlots($team->country);
                $cupSlotsForThisCompetition = array_filter(
                    $cupSlots,
                    fn (array $slot) => $slot['competition'] === $competitionId,
                );
                if (!empty($cupSlotsForThisCompetition) && !empty($allDeclaredPositions)) {
                    $deepest = max($allDeclaredPositions);
                    for ($pos = $deepest + 1; $pos <= $deepest + count($cupSlots); $pos++) {
                        $qualifyingTeamIds[] = $this->teamsByCountry[$team->country][$pos - 1]->id ?? null;
                    }
                }

                $this->assertContains(
                    $teamId,
                    $qualifyingTeamIds,
                    "Team {$teamId} from {$team->country} in {$competitionId} should have qualified via standings, not as a filler"
                );
            }
        }
    }

    public function test_a_squadless_ghost_winning_the_copa_does_not_enter_europe(): void
    {
        // Most of the Copa's 116-club field is squad-less, so this is the
        // shape the guard was written for.
        $ghost = Team::factory()->create(['country' => 'ES']);
        $this->createCupFinal('ESPCUP', $ghost->id, 7);

        $processor = app(UefaQualificationProcessor::class);
        $processor->process($this->game, $this->makeTransitionData());

        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $ghost->id)
                ->exists(),
            'A squad-less cup winner must not take a European place'
        );
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UCL')
                ->where('team_id', $ghost->id)
                ->exists(),
            'A squad-less cup winner must not sneak into the UCL either'
        );

        // No cup place exists to cascade: position 5 stays out of Europe.
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $this->teamsByCountry['ES'][4]->id)
                ->exists(),
            'Position 5 should stay out of Europe when the cup pays no place'
        );
    }

    public function test_a_squadless_ghost_winning_a_cup_does_not_enter_europe(): void
    {
        $this->setUpEnglishCups();

        // A lower-division cup entrant: a team row with no players, the way
        // SeedReferenceData creates one. It can win a cup outright, but it
        // has no squad to field in a European league phase.
        $ghost = Team::factory()->create(['country' => 'EN']);
        $this->createCupFinal('ENGCUP', $ghost->id, 6);

        $processor = app(UefaQualificationProcessor::class);
        $processor->process($this->game, $this->makeTransitionData());

        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $ghost->id)
                ->exists(),
            'A squad-less cup winner must not take a European place'
        );

        // England's cup pays no European place either: position 5 of the
        // Women's Super League stays out of Europe.
        $nextTeam = $this->teamsByCountry['EN'][4];
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $nextTeam->id)
                ->exists(),
            'Position 5 should stay out of Europe when the cup pays no place'
        );
    }

    // =========================================
    // Transition metadata logging
    // =========================================

    public function test_processor_logs_null_cup_winner_metadata_without_cup_slot(): void
    {
        // ES configures no cup_winner_slot: even a played cup final must not
        // produce cup-winner metadata.
        $cupWinner = $this->teamsByCountry['ES'][9]; // position 10
        $this->createCupFinal('ESPCUP', $cupWinner->id);

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $processor->process($this->game, $data);

        $this->assertNull($data->getMetadata('cupWinner'), 'No cup place exists, so no cup winner is logged');
        $this->assertNull($data->getMetadata('cupWinnerCascade'), 'No cup place exists, so no cascade is logged');

        // Same when no final was played at all.
        $processor->process($this->game, $data = $this->makeTransitionData());
        $this->assertNull($data->getMetadata('cupWinner'));
        $this->assertNull($data->getMetadata('cupWinnerCascade'));
    }

    public function test_processor_logs_qualifications_metadata(): void
    {
        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $processor->process($this->game, $data);

        $qualifications = $data->getMetadata('uefaQualifications');
        $this->assertNotNull($qualifications, 'UEFA qualifications metadata should be set');
        $this->assertArrayHasKey('ES', $qualifications);

        // Women's slots for ES: UCL [1,2,3] + UEL [4]. No cup place
        // cascades — cup_winner_slot is empty — so exactly four teams
        // qualify: three for the UCL, one for the UEL.
        $esQualifications = $qualifications['ES'];
        $this->assertCount(4, $esQualifications);
        $this->assertCount(3, array_filter($esQualifications, fn ($c) => $c === 'UCL'));
        $this->assertCount(1, array_filter($esQualifications, fn ($c) => $c === 'UEL'));
    }

    // =========================================
    // Helpers
    // =========================================

    private function makeTransitionData(): SeasonTransitionData
    {
        return new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP1',
        );
    }

    private function createCupFinal(string $cupId, string $winnerId, int $round = 7): void
    {
        $loser = $this->eurPoolTeams[0]; // arbitrary opponent
        CupTie::create([
            'game_id' => $this->game->id,
            'competition_id' => $cupId,
            'round_number' => $round, // last round of the cup's schedule.json
            'home_team_id' => $winnerId,
            'away_team_id' => $loser->id,
            'winner_id' => $winnerId,
            'completed' => true,
        ]);
    }

    /**
     * England's two cups, and a game whose base season has their data, so
     * LeagueFixtureGenerator can locate each final.
     */
    private function setUpEnglishCups(): void
    {
        foreach (['ENGCUP' => 'FA Cup', 'ENGLC' => 'EFL Cup'] as $id => $name) {
            Competition::factory()->create([
                'id' => $id,
                'name' => $name,
                'country' => 'EN',
                'type' => 'cup',
                'role' => Competition::ROLE_DOMESTIC_CUP,
                'handler_type' => 'knockout_cup',
            ]);
        }

        $this->game->update(['base_season' => '2026']);
    }

    private function createCountryTeamsWithStandings(string $country, string $competitionId, int $count, ?Team $firstTeam = null): void
    {
        $teams = [];
        for ($i = 0; $i < $count; $i++) {
            $team = ($i === 0 && $firstTeam) ? $firstTeam : Team::factory()->create(['country' => $country]);
            $teams[] = $team;

            // Create standings
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'position' => $i + 1,
                'played' => 38,
                'won' => max(0, 20 - $i),
                'drawn' => 10,
                'lost' => max(0, $i),
                'goals_for' => max(10, 60 - $i * 2),
                'goals_against' => 20 + $i,
                'points' => max(0, (20 - $i) * 3 + 10),
            ]);

            // Create GamePlayer records for each team
            $this->createPlayersForTeam($team, 10_000_000_00 - ($i * 200_000_00));
        }

        $this->teamsByCountry[$country] = $teams;
    }

    private function createPlayersForTeam(Team $team, int $marketValue): void
    {
        GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $team->id,
            'market_value_cents' => $marketValue,
        ]);
    }

    /**
     * Seed initial continental entries that mimic the game setup state.
     * Places configured-country teams + EUR pool teams to reach ~36 per competition.
     */
    private function seedInitialContinentalEntries(): void
    {
        // Group entries by competition from configured countries
        $byCompetition = []; // competitionId => [teamIds]

        foreach (['ES', 'EN', 'DE', 'IT', 'FR'] as $country) {
            $slots = $this->countryConfig->continentalSlots($country);
            foreach ($slots as $leagueId => $allocations) {
                foreach ($allocations as $continentalId => $positions) {
                    foreach ($positions as $pos) {
                        $teamIndex = $pos - 1;
                        if (!isset($this->teamsByCountry[$country][$teamIndex])) {
                            continue;
                        }
                        $byCompetition[$continentalId][] = $this->teamsByCountry[$country][$teamIndex]->id;
                    }
                }
            }
        }

        // Add configured-country teams to each competition
        foreach ($byCompetition as $competitionId => $teamIds) {
            foreach ($teamIds as $teamId) {
                CompetitionEntry::create([
                    'game_id' => $this->game->id,
                    'competition_id' => $competitionId,
                    'team_id' => $teamId,
                    'entry_round' => 1,
                ]);
            }
        }

        // Fill remaining slots with EUR pool teams
        $poolIndex = 0;
        foreach (['UCL', 'UEL'] as $competitionId) {
            $needed = 36 - count($byCompetition[$competitionId] ?? []);
            for ($i = 0; $i < $needed && $poolIndex < count($this->eurPoolTeams); $i++) {
                CompetitionEntry::create([
                    'game_id' => $this->game->id,
                    'competition_id' => $competitionId,
                    'team_id' => $this->eurPoolTeams[$poolIndex]->id,
                    'entry_round' => 1,
                ]);
                $poolIndex++;
            }
        }
    }
}
