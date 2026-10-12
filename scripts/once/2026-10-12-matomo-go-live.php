<?php
/**
 * Matomo (the in-WordPress plugin) for go-live. Per environment, with `wp eval-file`:
 *
 *   wp eval-file scripts/once/2026-10-12-matomo-go-live.php          # prints the state, changes nothing
 *   wp eval-file scripts/once/2026-10-12-matomo-go-live.php apply    # sets what must be set, prints the state again
 *
 * What a production copy of staging gets wrong, and what apply sets:
 *  - tracking mode: the copy carries "disabled" (staging never tracks); production runs Auto, cookieless (the theme's
 *    analytics.js adds the Form / Booking / Contact events Auto cannot see);
 *  - the site record: timezone and currency are the defaults (UTC, USD); the site is Belgian (Europe/Brussels, EUR). The
 *    timezone is WordPress's own setting, so the site's dates follow it too;
 *  - headless browsers (the smoke check) are excluded from the reports, in the plugin option AND in Matomo's own setting;
 *  - IP anonymisation (2 bytes) is switched on explicitly rather than left to a default;
 *  - JavaScript errors on the site are reported as events, and the goals below exist (the events of src/analytics.js).
 * Idempotent. Not touched: goals (they live in Matomo's UI), Do-Not-Track (a choice for Stefan, off by default).
 */
\WpMatomo\Bootstrap::do_bootstrap();

use Piwik\Access;
use Piwik\Plugins\PrivacyManager\Config as PrivacyConfig;
use Piwik\Plugins\SitesManager\API as SitesApi;

const SITE_ID = 1;
const USER_AGENTS = ['HeadlessChrome'];

/** The events theme src/analytics.js sends (Category | Action | Name), as goals: name => [attribute, pattern, type]. */
const GOALS = [
    'Afspraak ingepland' => ['event_category', 'Booking', 'exact'],
    'Nieuwsbrief-inschrijving' => ['event_name', 'newsletter', 'exact'],
    'Tool opgevraagd' => ['event_name', 'download', 'contains'],
    'Stageaanvraag' => ['event_name', 'stage', 'exact'],
    'Contactklik (mail of telefoon)' => ['event_category', 'Contact', 'exact'],
];

$settings = new \WpMatomo\Settings();
$asSuperUser = static fn (callable $do): mixed => Access::doAsSuperUser($do);

function state(\WpMatomo\Settings $settings, callable $asSuperUser): void
{
    $line = static fn (string $label, mixed $value) => print('  ' . str_pad($label, 30) . json_encode($value, JSON_UNESCAPED_SLASHES) . "\n");
    $line('tracking mode', $settings->get_global_option('track_mode'));
    $line('cookies disabled', $settings->get_global_option('disable_cookies'));
    $line('plugin user-agent exclusions', $asSuperUser(static fn () => $settings->get_global_user_agent_exclusions()));

    $site = $asSuperUser(static fn () => SitesApi::getInstance()->getSiteFromId(SITE_ID));
    $line('site', array_intersect_key((array) $site, array_flip(['idsite', 'name', 'main_url', 'timezone', 'currency'])));
    $line('Matomo global user agents', $asSuperUser(static fn () => SitesApi::getInstance()->getExcludedUserAgentsGlobal()));

    $privacy = new PrivacyConfig();
    $line('ip anonymiser / mask bytes', [$privacy->ipAnonymizerEnabled, $privacy->ipAddressMaskLength]);
    $line('do-not-track respected', $privacy->doNotTrackEnabled);
    $line('javascript errors tracked', $settings->get_global_option('track_jserrors'));
    $line('goals', array_map(
        static fn (array $goal): string => $goal['name'] . ' (' . $goal['match_attribute'] . ' ' . $goal['pattern_type'] . ' ' . $goal['pattern'] . ')',
        array_values($asSuperUser(static fn () => \Piwik\Plugins\Goals\API::getInstance()->getGoals(SITE_ID)))
    ));
    $line('archive cron scheduled', wp_next_scheduled('matomo_scheduled_archive') ? 'yes' : 'NO');
}

echo "== before\n";
state($settings, $asSuperUser);

if (($args[0] ?? '') !== 'apply') {
    exit("\nnothing changed (add `apply` to set it)\n");
}

$settings->apply_tracking_related_changes(['track_mode' => 'default', 'disable_cookies' => true, 'track_jserrors' => true]);

$asSuperUser(static function () use ($settings): void {
    $settings->set_global_user_agent_exclusions(
        array_values(array_filter(array_unique([...$settings->get_global_user_agent_exclusions(), ...USER_AGENTS])))
    );
});
$asSuperUser(static function (): void {
    $api = SitesApi::getInstance();
    $current = array_filter(array_map('trim', explode(',', (string) $api->getExcludedUserAgentsGlobal())));
    $api->setGlobalExcludedUserAgents(implode(',', array_values(array_unique([...$current, ...USER_AGENTS]))));
});

// WP-Matomo owns the site record (Matomo's own site admin is off): the timezone follows WordPress's, the currency is
// a plugin setting, and a sync writes both to the site.
update_option('timezone_string', 'Europe/Brussels');
$settings->set_global_option(\WpMatomo\Settings::SITE_CURRENCY, 'EUR');
(new \WpMatomo\Site\Sync($settings))->sync_current_site();

$asSuperUser(static function (): void {
    $api = \Piwik\Plugins\Goals\API::getInstance();
    $existing = array_column($api->getGoals(SITE_ID), 'name');
    foreach (GOALS as $name => [$attribute, $pattern, $type]) {
        if (! in_array($name, $existing, true)) {
            $api->addGoal(SITE_ID, $name, $attribute, $pattern, $type, false, 0, false);
        }
    }
});

$privacy = new PrivacyConfig();
$privacy->ipAnonymizerEnabled = true;
$privacy->ipAddressMaskLength = 2;

echo "\n== after\n";
state(new \WpMatomo\Settings(), $asSuperUser);
echo "\ndone\n";
