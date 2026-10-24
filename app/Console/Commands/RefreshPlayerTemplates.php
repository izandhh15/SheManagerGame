<?php

namespace App\Console\Commands;

use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Season\Services\GamePlayerTemplateService;
use Illuminate\Console\Command;

class RefreshPlayerTemplates extends Command
{
    protected $signature = 'app:refresh-player-templates
                            {--country= : Refresh only a specific country (e.g., ES)}
                            {--season= : Season to refresh (defaults to config season.current)}';

    protected $description = 'Regenerate game player templates from JSON data (safe for production)';

    public function handle(GamePlayerTemplateService $templateService, CountryConfig $countryConfig): int
    {
        $season = $this->option('season') ?: config('season.current');
        $countryFilter = $this->option('country');

        $countryCodes = $countryConfig->playableCountryCodes($season);

        if ($countryFilter) {
            $countryFilter = strtoupper($countryFilter);
            if (!in_array($countryFilter, $countryCodes)) {
                $this->error("Country '{$countryFilter}' is not a playable country.");
                return self::FAILURE;
            }
            $countryCodes = [$countryFilter];
        }

        $this->info("Refreshing player templates for season {$season}...");

        // SIN transacción a propósito (24-10-2026, Neon pooler): envolver
        // clear+generate en DB::transaction abortaba la transacción en
        // silencio y el primer generate fallaba con SQLSTATE[25P02] sin
        // mostrar el error real. Sin transacción cada query va en
        // autocommit y un fallo se ve tal cual. La generación es
        // reanudable: generateTemplates salta los equipos ya procesados
        // en esta temporada, así que un reintento continúa donde quedó.
        if ($countryFilter) {
            $templateService->clearTemplatesForCountry($season, $countryFilter);
        } else {
            $templateService->clearTemplates($season);
        }

        $totalCount = 0;

        foreach ($countryCodes as $countryCode) {
            $count = $templateService->generateTemplates($season, $countryCode);
            $this->line("  {$countryCode}: {$count} player templates");
            $totalCount += $count;
        }

        $this->newLine();
        $this->info("Total templates: {$totalCount}");

        return self::SUCCESS;
    }
}
