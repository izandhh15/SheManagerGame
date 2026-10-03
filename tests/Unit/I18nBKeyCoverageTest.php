<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * i18n-b review: every translation key introduced by the i18n-b fixes
 * must exist in all 5 locales with a non-empty value, and placeholders
 * used by the code must be present in every locale's string.
 */
class I18nBKeyCoverageTest extends TestCase
{
    private const LOCALES = ['es', 'en', 'de', 'fr', 'pt'];

    /**
     * [key => [placeholder, ...]] for keys whose placeholders the code passes.
     */
    private const KEYS_WITH_PLACEHOLDERS = [
        // PoachYouthPlayer
        'messages.poach_not_enough_budget' => [':fee'],
        'messages.poach_player_gone' => [':name'],
        'messages.poach_refused' => [':team', ':name', ':cost'],
        'messages.poach_success' => [':name'],
        'messages.poach_buzz' => [':team', ':player', ':potential'],
        // Awards gala
        'season.gala_notification_title' => [':season'],
        'season.gala_notification_message' => [':season', ':lines'],
        'season.gala_news_text' => [':season', ':parts'],
        'season.gala_headline_pichichi' => [':goals'],
        'season.gala_headline_zamora' => [':conceded'],
        'season.gala_headline_mvp' => [':count'],
        'season.gala_headline_default' => [':goals', ':assists'],
        // National team events
        'game.national_squad_event_injury' => [':name'],
        'game.national_squad_event_resignation' => [':name'],
        'game.national_team_injury_title' => [':name'],
        'game.national_team_injury_message' => [':name'],
        'game.national_team_resignation_title' => [':name'],
        'game.national_team_resignation_message' => [':name'],
        // Press (lineup + pre/post-match)
        'game.press_before_title' => [':reason'],
        // Fixed placeholders from the triage
        'game.national_social_convocatoria_text' => [':count', ':window', ':stars', ':flag'],
        'game.national_social_convocatoria_text_plain' => [':count', ':window', ':flag'],
        'game.national_social_entradas_text' => [':tier', ':price', ':flag'],
        'game.friendly_venue_mens_accepted' => [':stadium', ':fee'],
    ];

    /**
     * Keys without placeholders introduced by the i18n-b fixes.
     */
    private const PLAIN_KEYS = [
        'squad.lineup_must_select_11',
        'squad.lineup_duplicate_players',
        'squad.lineup_invalid_slot',
        'squad.lineup_slot_player_not_in_lineup',
        'squad.injury_ligament_damage',
        'squad.injury_knee_injury',
        'notifications.injury_ligament_damage',
        'notifications.injury_knee_injury',
        'notifications.injury_unknown_injury',
        'messages.season_summary_load_error',
        'messages.new_season_start_error',
        'messages.lineup_confirmed',
        'game.unknown',
        'game.press_published',
        'game.board_warning',
        'game.social_empty',
        'game.social_reply_circulating',
        'game.social_badge_journalist',
        'game.social_tag_sacked',
        'game.social_tag_rumor',
        'game.social_tag_news',
        'game.social_you_badge',
        'game.social_your_reply',
        'game.social_reply_hater',
        'game.social_publish_reply',
        'game.social_like',
        'game.social_verified_newsroom',
        'game.press_reason_final',
        'game.press_reason_derby',
        'game.press_reason_european',
        'game.press_reason_rival',
        'game.press_morale_hint',
        'game.press_face_button',
        'game.press_statements_hint',
        'game.press_make_statement',
        'game.press_skip',
        'game.press_conference_title',
        'game.press_reason_label_final',
        'game.press_reason_label_derby',
        'game.press_reason_label_european',
        'game.press_reason_label_rival',
        'game.press_prematch_hint',
        'game.press_already_done',
        'game.press_effect_morale',
        'game.press_effect_confidence',
        'game.press_back_to_lineup',
        'game.press_answer_button',
        'game.press_postmatch_hint',
        'game.press_already_done_postmatch',
        'game.press_see_reactions',
        'game.youth_scout_title',
        'game.youth_scout_subtitle',
        'game.youth_scout_age_unit',
        'game.youth_scout_ovr_unit',
        'game.youth_scout_potential',
        'game.youth_scout_try_sign',
        'game.youth_scout_empty',
        'game.nav_social',
        'game.nav_club_social',
        'game.nav_rival_academies',
        'game.nav_national_social',
        'game.image_tagline_squad',
        'game.image_tagline_season',
        'admin.admin_badge',
        'app.legal_notice',
    ];

    public function test_all_keys_exist_and_are_non_empty_in_every_locale(): void
    {
        $keys = array_merge(self::PLAIN_KEYS, array_keys(self::KEYS_WITH_PLACEHOLDERS));

        foreach (self::LOCALES as $locale) {
            foreach ($keys as $key) {
                $value = __("{$key}", [], $locale);
                $this->assertNotSame(
                    $key,
                    $value,
                    "missing translation: {$locale}.{$key}"
                );
                $this->assertNotSame(
                    '',
                    trim((string) $value),
                    "empty translation: {$locale}.{$key}"
                );
            }
        }
    }

    public function test_placeholders_present_in_every_locale(): void
    {
        foreach (self::KEYS_WITH_PLACEHOLDERS as $key => $placeholders) {
            foreach (self::LOCALES as $locale) {
                $value = (string) __("{$key}", [], $locale);
                foreach ($placeholders as $placeholder) {
                    $this->assertStringContainsString(
                        $placeholder,
                        $value,
                        "placeholder {$placeholder} missing in {$locale}.{$key}"
                    );
                }
            }
        }
    }

    public function test_knockout_qualified_keeps_feminine_form(): void
    {
        // i18n-b: the masculine duplicate won in fr/pt; the feminine form must remain.
        $this->assertSame(
            'Qualifiée pour la phase à élimination directe',
            __('game.knockout_qualified', [], 'fr')
        );
        $this->assertSame(
            'Qualificada para as eliminatórias',
            __('game.knockout_qualified', [], 'pt')
        );
    }

    public function test_injury_translation_map_covers_ai_injuries(): void
    {
        $map = \App\Modules\Player\Services\InjuryService::INJURY_TRANSLATION_MAP;

        foreach (['Muscle strain', 'Ligament damage', 'Ankle sprain', 'Knee injury', 'Unknown injury'] as $type) {
            $this->assertArrayHasKey($type, $map, "INJURY_TRANSLATION_MAP lacks {$type}");
            foreach (self::LOCALES as $locale) {
                $value = __($map[$type], [], $locale);
                $this->assertNotSame($map[$type], $value, "missing {$locale} for {$map[$type]}");
            }
        }
    }
}
