<?php

namespace Tests\Feature\ReviewBajosFixes;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * B-review fix (fase 5, familia i18n-a): paridad de claves es↔{de,fr,pt} en los
 * ficheros de idioma tocados. es es la lengua de referencia; de/fr/pt usaban
 * `fallback_locale=en` y mostraban claves en inglés.
 *
 * No necesita la app Laravel: carga los ficheros PHP de lang directamente.
 */
class LangDeFrPtParityTest extends TestCase
{
    /** @var string[] */
    private const LOCALES = ['de', 'fr', 'pt'];

    /** @var string[] */
    private const FILES = [
        'game',
        'club',
        'finances',
        'squad',
        'messages',
        'notifications',
        'cup',
        'season',
        'friends',
    ];

    private const NEW_KEYS = [
        'game' => [
            'list_view', 'month_view', 'squad_picker_apps_short', 'squad_picker_caps_short', 'mode_affiliate', 'mode_affiliate_desc',
            'mode_new_badge', 'affiliate_cta_title', 'affiliate_cta_desc', 'affiliate_cta_button', 'affiliate_step_1', 'affiliate_step_1_hint',
            'affiliate_start', 'affiliate_no_reserve', 'affiliate_managing_reserve', 'affiliate_managing_first', 'affiliate_generic_coach', 'affiliate_sack_title',
            'affiliate_sack_message', 'affiliate_sack_reason_relegated', 'affiliate_sack_reason_relegation_zone', 'affiliate_sack_reason_missed_objective', 'affiliate_midseason_sack_title', 'affiliate_midseason_sack_message',
            'affiliate_mode', 'affiliate_go_first', 'affiliate_go_reserve', 'dual_forced_title', 'dual_forced_blocked', 'dual_forced_cta',
            'dual_forced_bounced_title', 'dual_forced_back_title', 'dual_forced_bounced', 'stage_club_title', 'stage_club_subtitle', 'stage_club_budget',
            'stage_club_not_available', 'stage_club_not_enough_budget', 'preseason_family_derby_banner_title', 'preseason_family_derby_banner_body', 'preseason_family_derby_round', 'preseason_family_derby_trophy',
            'preseason_tour_title', 'preseason_tour_subtitle', 'preseason_tour_dest_usa', 'preseason_tour_dest_mexico', 'preseason_tour_dest_england', 'preseason_tour_dest_germany',
            'preseason_tour_cost_label', 'preseason_tour_revenue_label', 'preseason_tour_prestige_label', 'preseason_tour_organize', 'preseason_tour_organized', 'preseason_tour_organized_badge',
            'preseason_tour_already_organized', 'preseason_tour_invalid_destination', 'preseason_tour_no_budget', 'preseason_tour_not_available', 'preseason_tour_not_eligible', 'preseason_tour_expense_desc',
            'stage_expense_desc', 'preseason_tour_budget_label', 'venue_org_title', 'venue_org_subtitle', 'venue_org_budget', 'venue_org_quick',
            'venue_org_awaiting_title', 'venue_org_awaiting_hint', 'venue_org_none_title', 'venue_org_none_hint', 'venue_org_pending_title', 'venue_org_no_venue',
            'venue_org_calendar_title', 'venue_org_calendar_hint', 'cal_mon', 'cal_tue', 'cal_wed', 'cal_thu',
            'cal_fri', 'cal_sat', 'cal_sun', 'venue_org_free_hint', 'venue_org_club_hint', 'venue_org_mens_hint',
            'venue_org_offer', 'venue_org_offer_hint', 'venue_org_budget_left', 'venue_org_submit', 'venue_org_invalid_match', 'venue_org_over_budget',
            'venue_org_confirmed', 'venue_org_neutral', 'venue_org_requested', 'venue_org_accepted_fee', 'venue_org_mens_cant_afford', 'venue_org_rejected',
            'venue_org_rebate', 'venue_rebate_title', 'venue_rebate_club_desc', 'venue_fee_income_desc', 'venue_rebate_income_desc', 'mens_stadium_rent_title',
            'parent_stadium_title', 'parent_stadium_desc', 'parent_stadium_cta', 'parent_stadium_no_match', 'parent_stadium_no_parent', 'parent_stadium_already_moved',
            'parent_stadium_busy', 'parent_stadium_accepted', 'mens_stadium_rent_desc', 'mens_stadium_pick_match', 'mens_stadium_not_in_country', 'mens_stadium_rent_desc_casa',
            'mens_stadium_not_yours', 'mens_stadium_affiliated_badge', 'mens_stadium_municipal_badge', 'mens_stadium_pick_label', 'mens_stadium_ask_price', 'mens_stadium_quote_title',
            'mens_stadium_quote_desc', 'mens_stadium_council_label', 'mens_stadium_quote_free', 'mens_stadium_rejected_excuse_pitch', 'mens_stadium_rejected_excuse_works', 'mens_stadium_rejected_excuse_derby',
            'mens_stadium_rejected_excuse_board', 'mens_stadium_quote_confirm', 'mens_stadium_quote_confirm_free', 'mens_stadium_quote_cancel', 'mens_stadium_rented', 'mens_stadium_rent_tx_desc',
            'mens_stadium_quote_expired', 'mens_stadium_no_budget', 'mens_stadium_cant_afford', 'mens_stadium_rejected_owner_generic', 'mens_stadium_rejected_owner_excuse', 'matchday_pricing_saved',
            'matchday_revenue_line', 'matchday_revenue_line_tour', 'gala_award_ballon_dor', 'gala_award_pichichi', 'gala_award_zamora', 'gala_award_mvp',
            'gov_friendly_page_title', 'gov_friendly_badge', 'gov_friendly_offer_title', 'gov_friendly_offer_body', 'gov_friendly_government', 'gov_friendly_opponent',
            'gov_friendly_fee', 'gov_friendly_accept', 'gov_friendly_reject', 'gov_friendly_no_offer', 'gov_friendly_back', 'gov_friendly_round_name',
            'gov_friendly_accepted_title', 'gov_friendly_accepted_body', 'gov_friendly_accepted_flash', 'gov_friendly_rejected_flash', 'gov_friendly_already_answered', 'gov_friendly_invalid',
            'gov_venue_badge', 'gov_venue_offer_title', 'gov_venue_offer_body', 'gov_venue_free', 'gov_venue_accept', 'gov_venue_reject',
            'gov_venue_no_offer', 'gov_venue_accepted_flash', 'gov_venue_rejected_flash', 'gov_venue_already_answered', 'gov_venue_invalid', 'gov_venue_invalid_stadium',
            'subsidy_city_council', 'subsidy_breakdown_title', 'club_social_title', 'club_social_subtitle', 'club_social_followers', 'club_social_hype',
            'club_social_hype_desc', 'club_social_compose', 'club_social_type_signing', 'club_social_type_sale', 'club_social_type_injury', 'club_social_type_season_tickets',
            'club_social_type_renewal', 'club_social_type_next_home', 'club_social_type_ticket_discount', 'club_social_type_ticket_sales', 'club_social_type_friendly', 'club_social_type_venue',
            'club_social_pick_player', 'club_social_pick_match', 'club_social_destination', 'club_social_weeks', 'club_social_publish', 'club_social_no_home_match',
            'club_social_no_friendly', 'club_social_lang_note', 'club_social_lang_es', 'club_social_lang_ca', 'club_social_lang_va', 'club_social_lang_gl',
            'club_social_no_posts', 'club_social_published', 'club_social_no_match', 'club_social_no_venue', 'internet_title', 'internet_subtitle',
            'internet_placeholder', 'internet_publish', 'internet_posted', 'internet_limit', 'internet_limit_reached', 'internet_empty',
            'internet_tab_all', 'internet_tab_prensa', 'internet_tab_fichajes', 'internet_tab_partidos', 'internet_tab_sedes', 'internet_tab_jugadoras',
            'internet_tab_mister', 'club_social_invalid_type', 'club_social_not_available', 'club_social_no_player', 'club_social_no_recent_signings', 'club_social_no_recent_sales',
            'club_social_already_announced', 'club_social_renewal_needs_negotiation', 'club_social_official_badge', 'national_social_title', 'national_social_subtitle', 'national_social_compose',
            'national_social_type_convocatoria', 'national_social_type_sede', 'national_social_type_entradas', 'national_social_tier_popular', 'national_social_tier_normal', 'national_social_tier_premium',
            'national_social_no_posts', 'national_social_published', 'national_social_already_announced', 'national_social_invalid_type', 'national_social_not_available', 'national_social_no_squad',
            'national_social_no_squad_hint', 'national_social_no_match', 'national_social_no_venue', 'national_social_no_match_hint', 'national_social_convocatoria_ready', 'national_social_federation_note',
            'national_social_convocatoria_text', 'national_social_convocatoria_text_plain', 'national_social_sede_text', 'national_social_entradas_text',
        ],
        'club' => [
            'nav.matchday', 'commercial.slot_shirt', 'commercial.slot_ad_board', 'commercial.shirt_title', 'commercial.shirt_intro', 'commercial.ad_board_title',
            'commercial.ad_board_intro', 'commercial.offers_title', 'commercial.tier_badge_local', 'commercial.tier_badge_regional', 'commercial.tier_badge_nacional', 'commercial.tier_badge_internacional',
            'commercial.annual_value', 'commercial.contract_length', 'commercial.seasons', 'commercial.seasons_remaining', 'commercial.accept_button', 'commercial.reject_button',
            'commercial.accept_confirm', 'commercial.reject_confirm', 'commercial.renewal_badge', 'commercial.active_deal_label', 'matchday.title', 'matchday.intro',
            'matchday.prices_title', 'matchday.ticket_label', 'matchday.shirt_label', 'matchday.merch_label', 'matchday.bar_label', 'matchday.default_is',
            'matchday.ticket_hint', 'matchday.shirt_hint', 'matchday.merch_hint', 'matchday.bar_hint', 'matchday.save', 'matchday.projection_title',
            'matchday.vs', 'matchday.expected_crowd', 'matchday.total', 'matchday.projection_note', 'matchday.no_home_match', 'matchday.recent_title',
        ],
        'finances' => [
            'shirt_sponsor', 'ad_board', 'tooltip_shirt_sponsor', 'tooltip_ad_board', 'category_venue_rent', 'category_tour_cost',
            'category_matchday_tickets', 'category_matchday_shirts', 'category_matchday_merch', 'category_matchday_bars',
        ],
        'squad' => [
            'academy_jewel', 'academy_jewel_tooltip',
        ],
        'messages' => [
            'naming_rights_offer_rejected', 'sponsor_deal_accepted', 'sponsor_deal_rejected', 'sponsor_offer_unavailable', 'sponsor_deal_active',
        ],
        'notifications' => [
            'academy_jewel_title', 'academy_jewel_message', 'sponsor_offers_arrived_title', 'sponsor_offers_arrived_message', 'cwc_qualified_title', 'cwc_qualified_message',
        ],
        'cup' => [
            'bye',
        ],
        'season' => [
            'gala_title',
        ],
    ];

    private static function base(): string
    {
        return dirname(__DIR__, 3) . '/lang';
    }

    /** @return array<string, string> clave punteada => valor */
    private static function flat(array $arr, string $prefix = ''): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $out += self::flat($v, $prefix . $k . '.');
            } else {
                $out[$prefix . $k] = (string) $v;
            }
        }
        return $out;
    }

    /** @return array<string, string> */
    private function lang(string $locale, string $file): array
    {
        $path = self::base() . "/{$locale}/{$file}.php";
        $this->assertFileExists($path);

        return self::flat(require $path);
    }

    /** @return array<string, string> lista de :placeholders en orden de aparición */
    private static function placeholders(string $value): array
    {
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $value, $m);

        return $m[0];
    }

    public static function localeFileProvider(): array
    {
        $out = [];
        foreach (self::LOCALES as $locale) {
            foreach (self::FILES as $file) {
                $out["{$locale}/{$file}"] = [$locale, $file];
            }
        }

        return $out;
    }

    #[DataProvider('localeFileProvider')]
    public function test_locale_has_every_es_key(string $locale, string $file): void
    {
        $es = self::flat(require self::base() . "/es/{$file}.php");
        $target = $this->lang($locale, $file);

        $missing = array_diff(array_keys($es), array_keys($target));
        $this->assertSame(
            [],
            array_values($missing),
            "lang/{$locale}/{$file}.php: claves ausentes vs es: " . implode(', ', array_slice(array_values($missing), 0, 10))
        );
    }

    #[DataProvider('localeFileProvider')]
    public function test_placeholders_match_es(string $locale, string $file): void
    {
        $es = self::flat(require self::base() . "/es/{$file}.php");
        $target = $this->lang($locale, $file);

        $diffs = [];
        foreach ($target as $key => $value) {
            if (!isset($es[$key])) {
                continue;
            }
            $want = self::placeholders($es[$key]);
            sort($want);
            $got = self::placeholders($value);
            sort($got);
            if ($want !== $got) {
                $diffs[] = "{$key} (es: " . implode(',', $want) . ' vs ' . $locale . ': ' . implode(',', $got) . ')';
            }
        }

        // friendly_venue_mens_accepted carece de :fee en de/fr/pt: fix de otro
        // worker (familia i18n-c). Se excluye aquí a propósito.
        $diffs = array_values(array_filter(
            $diffs,
            fn (string $d) => !str_starts_with($d, 'friendly_venue_mens_accepted ')
        ));

        $this->assertSame([], $diffs, "lang/{$locale}/{$file}.php: placeholders distintos de es");
    }

    /** @var array<string, string[]> claves cuya traducción correcta coincide con el inglés */
    private const IDENTICAL_TO_EN_OK = [
        'de' => ['preseason_tour_prestige_label', 'venue_org_budget_left'],
        'fr' => [],
        'pt' => [],
    ];

    #[DataProvider('localeFileProvider')]
    public function test_no_raw_english_leftovers(string $locale, string $file): void
    {
        // Solo las claves añadidas por este fix (friends.php es fichero nuevo:
        // se revisa entero). Las claves preexistentes sin traducir son de otro
        // alcance y no se tocan aquí.
        $en = self::flat(require self::base() . "/en/{$file}.php");
        $es = self::flat(require self::base() . "/es/{$file}.php");
        $target = $this->lang($locale, $file);

        $scope = $file === 'friends' ? array_keys($target) : (self::NEW_KEYS[$file] ?? []);
        $okIdentical = self::IDENTICAL_TO_EN_OK[$locale] ?? [];

        $copies = [];
        foreach ($scope as $key) {
            $value = $target[$key] ?? null;
            if ($value === null || !isset($es[$key], $en[$key]) || in_array($key, $okIdentical, true)) {
                continue;
            }
            if ($es[$key] !== $en[$key] && $value === $en[$key] && mb_strlen($value) > 12) {
                $copies[] = $key;
            }
        }

        $this->assertSame([], $copies, "lang/{$locale}/{$file}.php: valores idénticos al inglés (sin traducir)");
    }
}
