<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Models\Game;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * BAJA review: NWSL, Liga MX Femenil, Brasileirão Feminino and Argentine
 * Primera offered GOAL_EUROPA_LEAGUE ("Clasificarse para la UEFA Women's
 * Europa Cup") — a UEFA competition that does not exist in their
 * confederations. The correct continental goals are the CONCACAF W
 * Champions Cup (NWSL/Liga MX) and the Copa Libertadores (BRA/ARG), with
 * target positions aligned to the real continental slots.
 */
class NonUefaContinentalGoalsTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, int}>
     */
    public static function continentalGoals(): array
    {
        return [
            'NWSL' => ['App\\Modules\\Competition\\Configs\\NWSLConfig', Game::GOAL_CONCACHAMPIONS, 'game.goal_concachampions', 3],
            'LigaMX' => ['App\\Modules\\Competition\\Configs\\LigaMXFemenilConfig', Game::GOAL_CONCACHAMPIONS, 'game.goal_concachampions', 3],
            'BRA1' => ['App\\Modules\\Competition\\Configs\\BrasileiraoFemininoConfig', Game::GOAL_LIBERTADORES, 'game.goal_libertadores', 4],
            'ARG1' => ['App\\Modules\\Competition\\Configs\\ArgentinePrimeraConfig', Game::GOAL_LIBERTADORES, 'game.goal_libertadores', 2],
        ];
    }

    #[DataProvider('continentalGoals')]
    public function test_offers_the_correct_continental_goal(
        string $configClass,
        string $goal,
        string $label,
        int $targetPosition,
    ): void {
        $config = new $configClass();
        $goals = $config->getAvailableGoals();

        $this->assertArrayNotHasKey(
            Game::GOAL_EUROPA_LEAGUE,
            $goals,
            "{$configClass} must not offer the UEFA Women's Europa Cup goal."
        );
        $this->assertArrayHasKey($goal, $goals);
        $this->assertSame($label, $goals[$goal]['label']);
        $this->assertSame($targetPosition, $goals[$goal]['targetPosition']);
        $this->assertSame($goal, $config->getSeasonGoal(ClubProfile::REPUTATION_ESTABLISHED));
    }

    #[DataProvider('continentalGoals')]
    public function test_continental_goal_label_exists_in_all_locales(
        string $configClass,
        string $goal,
        string $label,
        int $targetPosition,
    ): void {
        [$file, $key] = explode('.', $label, 2);

        foreach (['es', 'en', 'de', 'fr', 'pt'] as $locale) {
            $strings = include base_path("lang/{$locale}/{$file}.php");
            $this->assertArrayHasKey($key, $strings, "missing lang key '{$label}' in {$locale}/{$file}.php");
        }
    }
}
