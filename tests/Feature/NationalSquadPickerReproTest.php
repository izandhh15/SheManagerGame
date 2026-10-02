<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Repro for the 02-10-2026 500 on /game/{id}/national-squad (Spain
 * convocatoria): seeds template rows from the REAL ESP data files
 * (Spanish-nationality players) and hits the picker in update mode.
 */
class NationalSquadPickerReproTest extends TestCase
{
    use RefreshDatabase;

    public function test_national_squad_picker_update_mode_with_real_data(): void
    {
        $user = User::factory()->create();
        $spain = Team::factory()->create([
            'name' => 'España',
            'type' => 'national',
            'country' => 'ES',
        ]);

        // A club team, so the clubByPlayerId join has something to find.
        $club = Team::factory()->create([
            'name' => 'FC Barcelona',
            'type' => 'club',
            'country' => 'ES',
        ]);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $spain->id,
            'country' => 'ES',
            'season' => '2026',
            'current_date' => '2026-10-02',
        ]);

        $this->seedSpanishTemplates($spain->id, $club->id);

        // An injured player: her template player_id must match a
        // game_players row so the injured badge path is exercised.
        $templatePlayerId = \Illuminate\Support\Facades\DB::table('game_player_templates')
            ->where('season', '2026')
            ->where('team_id', $spain->id)
            ->value('player_id');

        if ($templatePlayerId) {
            $gp = \App\Models\GamePlayer::factory()->create([
                'game_id' => $game->id,
                'team_id' => $spain->id,
                'player_id' => $templatePlayerId,
                'name' => 'Injured Test Player',
            ]);
            \App\Models\GamePlayerMatchState::updateOrCreate(
                ['game_player_id' => $gp->id],
                [
                    'game_id' => $game->id,
                    'injury_until' => '2026-10-20',
                    'injury_type' => 'Test injury',
                ],
            );
        }

        $response = $this->actingAs($user)->get("/game/{$game->id}/national-squad");

        $response->assertStatus(200);
    }

    private function seedSpanishTemplates(string $spainId, string $clubId): void
    {
        $rows = [];
        foreach (glob(base_path('data/2026/ESP*/teams.json')) as $file) {
            $data = json_decode(file_get_contents($file), true);
            foreach ($data['clubs'] ?? [] as $clubData) {
                foreach ($clubData['players'] ?? [] as $p) {
                    $nat = $p['nationality'] ?? [];
                    if (is_string($nat)) {
                        $nat = [$nat];
                    }
                    $isSpanish = false;
                    foreach ((array) $nat as $n) {
                        if (stripos((string) $n, 'spain') !== false || stripos((string) $n, 'españa') !== false) {
                            $isSpanish = true;
                            break;
                        }
                    }
                    if (!$isSpanish) {
                        continue;
                    }

                    $dob = null;
                    if (!empty($p['dateOfBirth'])) {
                        try {
                            $dob = Carbon::parse($p['dateOfBirth'])->toDateString();
                        } catch (\Throwable) {
                            $dob = null;
                        }
                    }

                    // Half the players get a club template too (for the
                    // clubByPlayerId join); all get the Spain row.
                    $playerId = (string) Str::uuid();
                    $rows[] = [
                        'season' => '2026',
                        'player_id' => $playerId,
                        'team_id' => $spainId,
                        'position' => $p['position'] ?? 'Midfielder',
                        'name' => $p['name'] ?? 'Test Player',
                        'date_of_birth' => $dob,
                        'nationality' => json_encode(array_values((array) $nat)),
                        'overall_score' => $p['overall_score'] ?? 70,
                        'number' => null,
                    ];

                    if (count($rows) % 2 === 0) {
                        $rows[] = [
                            'season' => '2026',
                            'player_id' => $playerId,
                            'team_id' => $clubId,
                            'position' => $p['position'] ?? 'Midfielder',
                            'name' => $p['name'] ?? 'Test Player',
                            'date_of_birth' => $dob,
                            'nationality' => json_encode(array_values((array) $nat)),
                            'overall_score' => $p['overall_score'] ?? 70,
                            'number' => null,
                        ];
                    }
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('game_player_templates')->insert($chunk);
        }

        fwrite(STDERR, "\nSeeded " . count($rows) . " template rows\n");
    }
}
