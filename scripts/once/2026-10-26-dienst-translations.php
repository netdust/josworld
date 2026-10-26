<?php
/**
 * One-off, per environment (local, staging, then production), with `wp eval-file`:
 *
 *   wp eval-file scripts/once/2026-10-26-dienst-translations.php
 *
 * The five `dienst` terms existed only in Dutch, so a "+" copy of a case lost its diensten (Polylang drops a term
 * with no translation in the target language). This adds the English and French term for each and links the three.
 * Polylang (free) keeps term slugs unique per taxonomy, so a slug the Dutch term already holds gets a language suffix.
 * Idempotent: a language that already has its translation is left alone. Database content, not code.
 */
const DIENST_TRANSLATIONS = [
    'branding'     => ['en' => ['Branding', 'branding-en'], 'fr' => ['Branding', 'branding-fr']],
    'campagne'     => ['en' => ['Campaign', 'campaign'], 'fr' => ['Campagne', 'campagne-fr']],
    'communicatie' => ['en' => ['Communication', 'communication'], 'fr' => ['Communication', 'communication-fr']],
    'digitaal'     => ['en' => ['Digital', 'digital'], 'fr' => ['Numérique', 'numerique']],
    'strategie'    => ['en' => ['Strategy', 'strategy'], 'fr' => ['Stratégie', 'strategie-fr']],
];

foreach (DIENST_TRANSLATIONS as $slug => $languages) {
    $nl = get_term_by('slug', $slug, 'dienst');

    if (!$nl instanceof WP_Term || pll_get_term_language($nl->term_id) !== 'nl') {
        WP_CLI::warning("dienst {$slug}: no Dutch term, skipped");
        continue;
    }

    $group = pll_get_term_translations($nl->term_id);

    foreach ($languages as $lang => [$name, $translatedSlug]) {
        if (isset($group[$lang])) {
            WP_CLI::log("dienst {$slug} {$lang}: already {$group[$lang]}");
            continue;
        }

        $created = wp_insert_term($name, 'dienst', ['slug' => $translatedSlug]);

        if (is_wp_error($created)) {
            WP_CLI::warning("dienst {$slug} {$lang}: " . $created->get_error_message());
            continue;
        }

        pll_set_term_language($created['term_id'], $lang);
        $group[$lang] = $created['term_id'];
        WP_CLI::log("dienst {$slug} {$lang}: created {$created['term_id']} ({$name})");
    }

    pll_save_term_translations($group);
}

WP_CLI::success('dienst translations in place');
