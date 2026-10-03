<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Models\Game;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * BAJA review: ZweiteFrauenBundesligaConfig (DEU2), SecondeLigueConfig
 * (FRA2) and SerieBFemminileConfig (ITA2) offered GOAL_PLAYOFF to
 * established-reputation clubs, but none of these leagues has a promotion
 * playoff (playoff_count=0 in config/countries.php) — the goal was
 * unachievable by design. The playoff branch is removed and established
 * clubs aim for direct promotion instead.
 */
class NoPlayoffGoalWithoutPlayoffTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function configsWithoutPlayoff(): array
    {
        return [
            'DEU2' => ['App\\Modules\\Competition\\Configs\\ZweiteFrauenBundesligaConfig'],
            'FRA2' => ['App\\Modules\\Competition\\Configs\\SecondeLigueConfig'],
            'ITA2' => ['App\\Modules\\Competition\\Configs\\SerieBFemminileConfig'],
        ];
    }

    #[DataProvider('configsWithoutPlayoff')]
    public function test_no_playoff_goal_is_offered(string $configClass): void
    {
        $config = new $configClass();

        $this->assertArrayNotHasKey(
            Game::GOAL_PLAYOFF,
            $config->getAvailableGoals(),
            "{$configClass} must not offer a playoff goal: the league has no promotion playoff."
        );
    }

    #[DataProvider('configsWithoutPlayoff')]
    public function test_established_reputation_maps_to_promotion_not_playoff(string $configClass): void
    {
        $config = new $configClass();

        $this->assertSame(
            Game::GOAL_PROMOTION,
            $config->getSeasonGoal(ClubProfile::REPUTATION_ESTABLISHED),
            "{$configClass}: established clubs must aim for direct promotion."
        );
    }
}
