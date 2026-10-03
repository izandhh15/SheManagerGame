<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\DTOs\MatchNarrative;
use App\Modules\Media\Services\ClubSocialService;
use App\Modules\Media\Services\PressNewsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regresión M6: el parte médico en prensa describía mal 5 tipos de lesión
 * en español porque PressNewsService::injuryTypeEs() solo mapeaba 7 tipos y
 * el resto caía en el genérico "una lesión muscular" (una rotura de
 * cruzado o de Aquiles presentada como "lesión muscular").
 * (qa/bugs/agent-10.md)
 *
 * Fix: el mapa cubre los 10 tipos de InjuryService::INJURY_TYPES, con el
 * vocabulario de lang/es/squad.php.
 */
class M6InjuryTypeSpanishTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public static function injuryTypeProvider(): array
    {
        return [
            'fatiga' => ['Muscle fatigue', 'fatiga muscular'],
            'distensión' => ['Muscle strain', 'una rotura muscular'],
            'gemelo' => ['Calf strain', 'una sobrecarga en el gemelo'],
            'esguince de tobillo' => ['Ankle sprain', 'un esguince de tobillo'],
            'ingle' => ['Groin strain', 'una distensión en la ingle'],
            'isquiotibial' => ['Hamstring tear', 'una rotura de isquiotibial'],
            'contusión de rodilla' => ['Knee contusion', 'una contusión de rodilla'],
            'metatarso' => ['Metatarsal fracture', 'una fractura de metatarso'],
            'cruzado' => ['ACL tear', 'una rotura del ligamento cruzado'],
            'Aquiles' => ['Achilles rupture', 'una rotura del tendón de Aquiles'],
        ];
    }

    #[DataProvider('injuryTypeProvider')]
    public function test_injury_type_has_correct_spanish_description(string $type, string $expected): void
    {
        app()->setLocale('es');
        [$game, $team] = $this->buildScenario();

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Jugadora M6 ' . $type,
            'injury_until' => Carbon::parse('2026-10-03')->addWeeks(6),
            'injury_type' => $type,
        ]);

        $announced = app(ClubSocialService::class)
            ->announce($game, 'injury', $player->id, ['weeks' => 6]);
        $this->assertTrue($announced['ok'], 'el parte médico debería publicarse');

        $articles = app(PressNewsService::class)->articles($game->refresh(), null);
        $injury = $this->findByCategory($articles, 'injury');
        $this->assertNotNull($injury, "debería haber noticia de lesión para {$type}");

        $body = implode(' ', $injury->body);
        $this->assertStringContainsString(
            $expected,
            $body,
            "el parte de '{$type}' debería describirse como '{$expected}'"
        );
        $this->assertStringNotContainsString(
            'lesión muscular',
            $body,
            "el parte de '{$type}' no puede caer en el genérico 'lesión muscular'"
        );
    }

    /**
     * @return array{0:Game, 1:Team}
     */
    private function buildScenario(): array
    {
        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F (test)',
            'country' => 'ES',
            'tier' => 1,
        ]);

        $team = Team::factory()->create(['name' => 'Test WFC M6', 'country' => 'ES']);
        $user = User::factory()->create();
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'country' => 'ES',
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'current_date' => Carbon::parse('2026-10-03'),
        ]);

        return [$game, $team];
    }

    private function findByCategory(array $articles, string $category): ?MatchNarrative
    {
        foreach ($articles as $article) {
            if ($article->category === $category) {
                return $article;
            }
        }

        return null;
    }
}
