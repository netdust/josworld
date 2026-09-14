<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\admin\AdminAppService;
use josworld\services\admin\AdminUIService;
use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use WP_UnitTestCase;

/**
 * AdminUIService::POST_TYPES is a hand-maintained slug list; nothing else
 * forces it to match what the content services actually register, and a
 * typo'd slug silently stops the metabox field JS enqueueing on that
 * screen with no test failure anywhere. This file pins the list to the
 * real registry in BOTH directions.
 *
 * It no longer pins a translatable-post-type list: which types Polylang
 * translates is DB state now (the `polylang` option, converged by
 * `wp ntdst polylang languages`), not a constant this suite can read — see
 * docs/content-model.md § "The multilingual DB-state runbook".
 */
final class PostTypeRegistryConsistencyTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/admin/AdminAppService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/CaseService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/TeamService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/VacatureService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/ToolService.php';

        if (! defined('JOSWORLD_CORE_META_PREFIX')) {
            define('JOSWORLD_CORE_META_PREFIX', '_jw_');
        }
        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        new CaseService(ntdst_data());
        new TeamService(ntdst_data());
        new VacatureService(ntdst_data());
        new ToolService(ntdst_data());
    }

    public function test_post_types_constant_is_publicly_readable(): void
    {
        // A consumer can name the list without instantiating the service.
        $this->assertSame(['case', 'team', 'vacature', 'tool'], AdminUIService::POST_TYPES);
    }

    public function test_case_is_a_real_registered_content_model_and_is_named_in_post_types(): void
    {
        $this->assertContains(
            'case',
            AdminUIService::POST_TYPES,
            'case must remain listed in POST_TYPES while it is a live josworld content model',
        );

        $this->assertTrue(
            ntdst_data()->isRegistered('case'),
            'case must be a REALLY registered ntdst content model, not merely named in the static list — '
            . 'isRegistered() (unlike get()) never auto-creates a phantom model as a side effect',
        );
    }

    public function test_team_is_a_real_registered_content_model_and_is_named_in_post_types(): void
    {
        $this->assertContains(
            'team',
            AdminUIService::POST_TYPES,
            'team must remain listed in POST_TYPES while it is a live josworld content model',
        );

        $this->assertTrue(
            ntdst_data()->isRegistered('team'),
            'team must be a REALLY registered ntdst content model, not merely named in the static list — '
            . 'isRegistered() (unlike get()) never auto-creates a phantom model as a side effect',
        );
    }

    public function test_vacature_is_a_real_registered_content_model_and_is_named_in_post_types(): void
    {
        $this->assertContains(
            'vacature',
            AdminUIService::POST_TYPES,
            'vacature must remain listed in POST_TYPES while it is a live josworld content model',
        );

        $this->assertTrue(
            ntdst_data()->isRegistered('vacature'),
            'vacature must be a REALLY registered ntdst content model, not merely named in the static list — '
            . 'isRegistered() (unlike get()) never auto-creates a phantom model as a side effect',
        );
    }

    public function test_tool_is_a_real_registered_content_model_and_is_named_in_post_types(): void
    {
        $this->assertContains(
            'tool',
            AdminUIService::POST_TYPES,
            'tool must remain listed in POST_TYPES while it is a live josworld content model',
        );

        $this->assertTrue(
            ntdst_data()->isRegistered('tool'),
            'tool must be a REALLY registered ntdst content model, not merely named in the static list — '
            . 'isRegistered() (unlike get()) never auto-creates a phantom model as a side effect',
        );
    }

    /**
     * The POST_TYPES -> registry direction: every listed slug must be a
     * really-registered model.
     */
    public function test_every_registered_post_type_matches_admin_ui_service_post_types(): void
    {
        $registeredSlugs = array_values(array_filter(
            AdminUIService::POST_TYPES,
            static fn (string $slug): bool => ntdst_data()->isRegistered($slug),
        ));

        $this->assertSame(
            AdminUIService::POST_TYPES,
            $registeredSlugs,
            'every slug in AdminUIService::POST_TYPES must be a REALLY registered ntdst content model — '
            . 'a typo or an unregistered slug here means the metabox field JS silently never enqueues (CB-4)',
        );
    }

    /**
     * The FORWARD direction: registry -> POST_TYPES. The other direction
     * cannot see a slug nobody expected — a plural typo registers fine
     * while the singular stays honestly unlisted. Scoped by the `_jw_`
     * meta prefix: isRegistered() alone sweeps in sibling suites' fixture
     * models (shared process), and WP's `public` flag would let a
     * non-public model escape the net.
     */
    public function test_no_ntdst_registered_post_type_is_missing_from_the_post_types_constant(): void
    {
        $ours = array_filter(
            get_post_types([], 'names'),
            static function (string $slug): bool {
                if (! ntdst_data()->isRegistered($slug)) {
                    return false;
                }

                return ntdst_data()->get($slug)->getMetaPrefix() === JOSWORLD_CORE_META_PREFIX;
            },
        );

        $this->assertNotEmpty(
            $ours,
            'precondition: at least one josworld model must be registered, or this '
            . 'assertion passes vacuously over an empty set',
        );

        foreach ($ours as $slug) {
            $this->assertContains(
                $slug,
                AdminUIService::POST_TYPES,
                "post type '{$slug}' is registered through ntdst_data() but is NOT in "
                . 'AdminUIService::POST_TYPES — either the slug is a typo, or POST_TYPES '
                . 'needs it. Left as-is, the metabox field JS silently never enqueues on '
                . "the '{$slug}' edit screen (CB-4).",
            );
        }
    }

    /**
     * A `_jw_`-prefixed, NON-PUBLIC fixture absent from POST_TYPES: a
     * public=>true-filtered candidate set misses it, the prefix-scoped set
     * sees it — proving the forward check's scoping is load-bearing.
     */
    public function test_public_true_filter_misses_a_non_public_model_that_prefix_scoping_catches(): void
    {
        register_post_type('jw_fixture_hidden', [
            'public' => false,
            'show_ui' => true,
        ]);
        ntdst_data()->register('jw_fixture_hidden', [
            'label' => 'Fixture (non-public, not in POST_TYPES)',
            'public' => false,
            'meta_prefix' => JOSWORLD_CORE_META_PREFIX,
            'auto_metabox' => false,
        ]);

        // The public=>true-filtered candidate set cannot even see the fixture.
        $oldForwardCheckCandidates = array_filter(
            get_post_types(['public' => true], 'names'),
            static function (string $slug): bool {
                if (! ntdst_data()->isRegistered($slug)) {
                    return false;
                }

                return ntdst_data()->get($slug)->getMetaPrefix() === JOSWORLD_CORE_META_PREFIX;
            },
        );
        $this->assertNotContains(
            'jw_fixture_hidden',
            $oldForwardCheckCandidates,
            'MUTATION PROOF (gap side): a public=>true-filtered candidate set must NOT '
            . 'contain a non-public model — this is the exact CB-B-4 blind spot',
        );

        // The prefix-scoped set does see it — the precondition for the real
        // forward check to fail on it.
        $prefixScopedCandidates = array_filter(
            get_post_types([], 'names'),
            static function (string $slug): bool {
                if (! ntdst_data()->isRegistered($slug)) {
                    return false;
                }

                return ntdst_data()->get($slug)->getMetaPrefix() === JOSWORLD_CORE_META_PREFIX;
            },
        );
        $this->assertContains(
            'jw_fixture_hidden',
            $prefixScopedCandidates,
            'MUTATION PROOF (fix side): a `_jw_`-prefix-scoped candidate set must see a '
            . 'non-public josworld model that public=>true filtering hides',
        );
        $this->assertNotContains(
            'jw_fixture_hidden',
            AdminUIService::POST_TYPES,
            'precondition: the fixture must be absent from POST_TYPES for this to be a real gap',
        );
    }

    /**
     * AdminAppService::POST_TYPES is a FOURTH copy of the same slugs — a
     * map this time, because the admin menu needs a label per entry. Its
     * failure mode is the quietest yet: a drifted key produces a submenu
     * entry pointing at `edit.php?post_type=<typo>`, which renders an
     * "Invalid post type" screen, while the real content type keeps a
     * top-level menu ntdst-baseline never removed. Nothing else fails.
     *
     * Pinned to POST_TYPES by equality — one pinned list, one transitively
     * pinned copy. The translatable-post-type list used to be a third; it is
     * Polylang DB state now, and nothing here can reach it.
     */
    public function test_the_admin_app_menu_list_matches_the_admin_ui_list(): void
    {
        $appTypes = array_keys(AdminAppService::POST_TYPES);
        $adminUi = AdminUIService::POST_TYPES;

        sort($appTypes);
        sort($adminUi);

        $this->assertSame(
            $adminUi,
            $appTypes,
            'AdminAppService::POST_TYPES has drifted from AdminUIService::POST_TYPES. '
            . 'A drifted key does NOT fatal — it adds a Josworld submenu entry that '
            . 'renders "Invalid post type", and leaves the real content type holding the '
            . 'top-level menu ntdst-baseline was supposed to remove. Keep the slugs identical.',
        );
    }

    /** Every label the menu shows must be a non-empty string. */
    public function test_every_admin_app_post_type_carries_a_menu_label(): void
    {
        foreach (AdminAppService::POST_TYPES as $slug => $label) {
            $this->assertNotSame('', trim($label), "menu label for `{$slug}` must not be empty");
        }
    }
}
