<?php

/**
 * Josworld Core — business-logic plugin. Business logic lives here, not in
 * the theme.
 *
 * LOAD ORDER: included by josworld-coreloader.php, which sorts
 * alphabetically BEFORE ntdst-coreloader.php — the framework is NOT yet
 * defined at include time, so all framework-dependent wiring defers to
 * `plugins_loaded` (every mu-plugin is included by then). ntdst-core is
 * shared with other sites and stays untouched.
 *
 * @package josworld
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

defined('ABSPATH') || exit;

// Shared meta-key prefix (no meta key may lack it) and textdomain; guarded
// against double inclusion.
defined('JOSWORLD_CORE_META_PREFIX') || define('JOSWORLD_CORE_META_PREFIX', '_jw_');
defined('JOSWORLD_CORE_TEXTDOMAIN') || define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');

// The ntdst-baseline yootheme module publishes every model to the builder's
// Dynamic Content picker; polylang owns the multilingual layer (shared slugs,
// layout copy on a "+" translation, the `wp ntdst polylang` commands).
// Assignment, not union: both keys already exist (off).
add_filter('ntdst/baseline/modules', static function (array $modules): array {
    $modules['yootheme'] = true;
    $modules['polylang'] = true;
    $modules['duplicate'] = true;
    $modules['grants'] = true;

    return $modules;
});

// Each ntdst route answers per language (the grant link: /toegang/, /en/access/, /fr/acces/).
add_filter('ntdst/baseline/polylang/config', static function (array $config): array {
    $config['route_languages'] = true;

    return $config;
});

// A tool's file or app opens only at a visitor's personal link; the download form hands it out.
// `render` resolves ToolAppService on the request that opens a link, not when the config is first read.
add_filter('ntdst/baseline/grants/config', static function (array $config): array {
    $config['types'] = ['tool' => [
        'file_fields' => ['file'],
        'render'      => static fn (\WP_Post $tool, \WP_Post $grant): string|false|null
            => ntdst_get(\josworld\services\content\ToolAppService::class)->renderForGrant($tool, $grant),
    ]];
    $config['base'] = ['nl' => 'toegang', 'en' => 'access', 'fr' => 'acces'];
    $config['store'] = defined('PRIVATE_FILES_DIR') ? PRIVATE_FILES_DIR : null;
    $config['labels'] = [
        'download'       => 'Download de tool',
        'open'           => 'Open de tool',
        'dead_title'     => 'Deze link werkt niet meer',
        'dead_body'      => 'De link die je volgde is niet meer geldig. Vraag de tool gerust opnieuw aan.',
        'dead_back'      => 'Naar de tool',
        'panel_title'    => 'Persoonlijke links',
        'panel_empty'    => 'Er is nog geen persoonlijke link uitgedeeld.',
        'panel_failed'   => 'De link kon niet aangepast worden. Probeer het opnieuw.',
        'col_address'    => 'E-mailadres',
        'col_language'   => 'Taal',
        'col_created'    => 'Aangemaakt',
        'col_opened'     => 'Laatst geopend',
        'col_opens'      => 'Keer geopend',
        'col_status'     => 'Status',
        'status_active'  => 'Actief',
        'status_revoked' => 'Ingetrokken',
        'revoke'         => 'Intrekken',
        'allow_again'    => 'Opnieuw toelaten',
        'mail_subject'   => 'Je tool staat klaar. Aan jou nu.',
        'mail_body'      => '<p>Hey,</p>'
            . '<p>Bedankt om onze tool te downloaden. Hopelijk helpt die je een stap vooruit.</p>'
            . '<p>Link naar de tool: <a href="{link}">{title}</a></p>'
            . '<p>Hou de tool en je persoonlijke link graag voor jezelf. Iemand anders die er iets aan heeft? '
            . 'Stuur die gerust naar onze website om zelf een exemplaar aan te vragen.</p>'
            . '<p>Al getest? We horen graag wat je ervan vindt. Ideeën voor andere tools? Een vraag? '
            . 'Of gewoon eens kennismaken? Bel ons gerust of stuur een mailtje naar '
            . '<a href="mailto:jos@jos.agency">jos@jos.agency</a>.</p>'
            . '<p>Veel plezier ermee!</p><p>Jos</p>',
    ];
    $config['labels_i18n'] = [
        'en' => [
            'download'     => 'Download the tool',
            'open'         => 'Open the tool',
            'dead_title'   => 'This link no longer works',
            'dead_body'    => 'The link you followed is no longer valid. Feel free to request the tool again.',
            'dead_back'    => 'Go to the tool',
            'mail_subject' => 'Your tool is ready. Over to you.',
            'mail_body'    => '<p>Hi,</p>'
                . '<p>Thanks for downloading our tool. We hope it helps you take a step forward.</p>'
                . '<p>Link to the tool: <a href="{link}">{title}</a></p>'
                . '<p>Please keep the tool and your personal link to yourself. Know someone else who could use it? '
                . 'Send them to our website to request their own copy.</p>'
                . '<p>Tried it yet? We would love to hear what you think. Ideas for other tools? A question? '
                . 'Or just want to get acquainted? Give us a call or drop a line to '
                . '<a href="mailto:jos@jos.agency">jos@jos.agency</a>.</p>'
                . '<p>Enjoy!</p><p>Jos</p>',
        ],
        'fr' => [
            'download'     => 'Télécharger l’outil',
            'open'         => 'Ouvrir l’outil',
            'dead_title'   => 'Ce lien ne fonctionne plus',
            'dead_body'    => 'Le lien que vous avez suivi n’est plus valable. N’hésitez pas à redemander l’outil.',
            'dead_back'    => 'Vers l’outil',
            'mail_subject' => 'Votre outil est prêt. À vous de jouer.',
            'mail_body'    => '<p>Bonjour,</p>'
                . '<p>Merci d’avoir téléchargé notre outil. Nous espérons qu’il vous aidera à avancer.</p>'
                . '<p>Lien vers l’outil : <a href="{link}">{title}</a></p>'
                . '<p>Gardez l’outil et votre lien personnel pour vous. Quelqu’un d’autre pourrait en profiter ? '
                . 'Invitez-le à demander son propre exemplaire sur notre site.</p>'
                . '<p>Déjà testé ? Votre avis nous intéresse. Des idées pour d’autres outils ? Une question ? '
                . 'Ou simplement envie de faire connaissance ? Appelez-nous ou écrivez à '
                . '<a href="mailto:jos@jos.agency">jos@jos.agency</a>.</p>'
                . '<p>Bon usage !</p><p>Jos</p>',
        ],
    ];

    return $config;
});

// Duplicate pages, posts and CPTs: the admin is Dutch, baseline's defaults are English.
add_filter('ntdst/baseline/duplicate/config', static function (array $config): array {
    $config['suffix'] = '(kopie)';
    $config['labels'] = ['duplicate' => 'Dupliceren'];

    return $config;
});

add_action('plugins_loaded', function (): void {
    // Fail quiet if ntdst-core didn't load (framework missing/disabled) —
    // no fatal, no partial registration.
    if (!function_exists('ntdst_get')) {
        return;
    }

    /**
     * Boot josworld-core's services. No PSR-4 autoloader in a project-core:
     * each service file is required explicitly, then resolved through the
     * DI container.
     *
     * ORDERING CONTRACT — every ntdst_get() call below MUST have a matching
     * require_once above it: ntdst_get() only resolves a class PHP already
     * loaded, so a missing require_once fatals on a real request while the
     * unit suite stays green (unit tests require their own service files
     * directly and can never observe a missing line here). A structural
     * unit test guards this.
     *
     * Boot order: content services first, then admin-only (inside the
     * is_admin() branch only — never booted on a front-end request).
     */
    $dir = __DIR__ . '/services';

    // Content services:
    require_once $dir . '/content/CaseService.php';
    require_once $dir . '/content/TeamService.php';
    require_once $dir . '/content/VacatureService.php';
    require_once $dir . '/content/ToolService.php';
    require_once $dir . '/content/ToolLinkPage.php';
    require_once $dir . '/content/ToolAppService.php';

    // Admin-only services. ActivitySource first: both repositories implement
    // it, and AdminAppService type-hints it in its constructor.
    require_once $dir . '/admin/AdminUIService.php';
    require_once $dir . '/admin/ActivitySource.php';
    require_once $dir . '/admin/BookingRepository.php';
    require_once $dir . '/admin/SubmissionRepository.php';
    require_once $dir . '/admin/AdminAppService.php';
    require_once $dir . '/admin/AdminChromeService.php';

    // Form service:
    require_once $dir . '/forms/CrmContactLanguageService.php';
    require_once $dir . '/forms/CrmDownloadedToolService.php';
    require_once $dir . '/site/SiteMetaService.php';
    require_once $dir . '/site/LegacyRedirectService.php';
    require_once $dir . '/site/HeroImageService.php';
    require_once $dir . '/site/MailLayoutService.php';
    require_once $dir . '/site/ToolGrantsMatomo.php';

    // Forms: the tool download confirmation.
    require_once $dir . '/forms/DownloadFormService.php';

    unset($dir); // no longer needed after the require_once list above

    add_action('after_setup_theme', function (): void {
        // Content services — resolved unconditionally (front + admin):
        ntdst_get(\josworld\services\content\CaseService::class);
        ntdst_get(\josworld\services\content\TeamService::class);
        ntdst_get(\josworld\services\content\VacatureService::class);
        ntdst_get(\josworld\services\content\ToolService::class);

        // Admin-only services — only in wp-admin. Never move a service out
        // of this branch without also moving its require_once with it.
        if (is_admin()) {
            ntdst_get(\josworld\services\admin\AdminUIService::class);
            // Declares the Josworld app to ntdst-baseline's admin_ui module.
            // Must resolve before `admin_menu` (priority 9) reads the filter.
            ntdst_get(\josworld\services\admin\AdminAppService::class);
            // The CPT list/edit screens wear the dashboard's chrome.
            ntdst_get(\josworld\services\admin\AdminChromeService::class);
        }

        // Form service — resolved unconditionally: the FluentCRM/Polylang
        // absence guards live INSIDE the service, as below.
        ntdst_get(\josworld\services\forms\CrmContactLanguageService::class);
        ntdst_get(\josworld\services\forms\CrmDownloadedToolService::class);

        // josworld's values for ntdst-baseline and the front-end tidying; must be attached
        // before baseline boots its modules (both hang on after_setup_theme:5).
        ntdst_get(\josworld\services\site\SiteMetaService::class);
        ntdst_get(\josworld\services\site\LegacyRedirectService::class);
        ntdst_get(\josworld\services\site\HeroImageService::class);

        // Every outgoing mail in the JOS layout — mail is sent from front end, admin and cron alike.
        ntdst_get(\josworld\services\site\MailLayoutService::class);

        // No analytics on a personal link: its URL carries the token.
        ntdst_get(\josworld\services\site\ToolGrantsMatomo::class);

        // A personal link's page wears the tool page's header section.
        ntdst_get(\josworld\services\content\ToolLinkPage::class);

        // The download form's confirmation runs on a front-end submission.
        ntdst_get(\josworld\services\forms\DownloadFormService::class);
    }, 5);
}, 5);
