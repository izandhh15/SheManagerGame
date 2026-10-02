<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\Team;

/**
 * Subsidy breakdown (F8, 0.3.9): when a subsidy arrives, show WHO it comes
 * from — national government, regional government, city council — instead
 * of a generic lump sum. All government names are the real official ones.
 */
class SubsidyBreakdownService
{
    /**
     * Regional governments by team-name keyword (Spain). The first match
     * wins; clubs not listed fall back to their national government only.
     */
    private const REGIONAL_GOVERNMENTS = [
        'Valencia' => 'Generalitat Valenciana',
        'Levante' => 'Generalitat Valenciana',
        'Villarreal' => 'Generalitat Valenciana',
        'Elche' => 'Generalitat Valenciana',
        'Castellón' => 'Generalitat Valenciana',
        'Hércules' => 'Generalitat Valenciana',
        'Barcelona' => 'Generalitat de Catalunya',
        'Espanyol' => 'Generalitat de Catalunya',
        'Girona' => 'Generalitat de Catalunya',
        'Madrid' => 'Comunidad de Madrid',
        'Atlético' => 'Comunidad de Madrid',
        'Getafe' => 'Comunidad de Madrid',
        'Rayo' => 'Comunidad de Madrid',
        'Athletic' => 'Gobierno Vasco',
        'Real Sociedad' => 'Gobierno Vasco',
        'Alavés' => 'Gobierno Vasco',
        'Eibar' => 'Gobierno Vasco',
        'Sevilla' => 'Junta de Andalucía',
        'Betis' => 'Junta de Andalucía',
        'Málaga' => 'Junta de Andalucía',
        'Granada' => 'Junta de Andalucía',
        'Celta' => 'Xunta de Galicia',
        'Deportivo' => 'Xunta de Galicia',
        'Sporting' => 'Gobierno del Principado de Asturias',
        'Oviedo' => 'Gobierno del Principado de Asturias',
        'Osasuna' => 'Gobierno de Navarra',
        'Zaragoza' => 'Gobierno de Aragón',
        'Tenerife' => 'Gobierno de Canarias',
    ];

    private const NATIONAL_GOVERNMENTS = [
        'ES' => 'Gobierno de España',
        'FR' => 'Gouvernement français',
        'DE' => 'Bundesregierung',
        'IT' => 'Governo italiano',
        'EN' => 'UK Government',
        'PT' => 'Governo de Portugal',
        'NL' => 'Nederlandse regering',
    ];

    /**
     * Split a subsidy total into its government sources.
     *
     * @return list<array{source: string, amount: int}>
     */
    public function breakdown(int $total, ?Team $team): array
    {
        if ($total <= 0) {
            return [];
        }

        $country = $team?->country ?? 'ES';
        $national = self::NATIONAL_GOVERNMENTS[$country] ?? self::NATIONAL_GOVERNMENTS['ES'];
        $regional = $this->regionalGovernment($team?->name ?? '');

        // 60% national, 25% regional (if any), 15% city council.
        // Amounts in euros; the last line absorbs rounding.
        if ($regional !== null) {
            $nationalAmount = (int) round($total * 0.60);
            $regionalAmount = (int) round($total * 0.25);
            $councilAmount = $total - $nationalAmount - $regionalAmount;

            return [
                ['source' => $national, 'amount' => $nationalAmount],
                ['source' => $regional, 'amount' => $regionalAmount],
                ['source' => __('game.subsidy_city_council'), 'amount' => $councilAmount],
            ];
        }

        $nationalAmount = (int) round($total * 0.85);
        $councilAmount = $total - $nationalAmount;

        return [
            ['source' => $national, 'amount' => $nationalAmount],
            ['source' => __('game.subsidy_city_council'), 'amount' => $councilAmount],
        ];
    }

    private function regionalGovernment(string $teamName): ?string
    {
        foreach (self::REGIONAL_GOVERNMENTS as $keyword => $government) {
            if (stripos($teamName, $keyword) !== false) {
                return $government;
            }
        }

        return null;
    }
}
