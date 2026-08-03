<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\admin\AdminUIService;
use josworld\services\content\CaseService;
use WP_UnitTestCase;

/**
 * Integration tier: closes Cluster A review finding CB-4.
 *
 * THE PROBLEM CB-4 NAMED: `AdminUIService::POST_TYPES` is a hand-maintained
 * list (`['case', 'team', 'vacature', 'tool']`). Nothing forced it to match
 * the CPT slugs the content services actually register — a future typo
 * (e.g. T07 registering `vacatures`, plural) would silently stop enqueueing
 * the metabox field JS on that screen with NO test failure anywhere,
 * because AdminUIServiceEnqueueTest registers its own fixture post types
 * rather than depending on the real services.
 *
 * WHY NOT A FULL EQUALITY CHECK TODAY: only `case` (T05) is registered as a
 * real josworld content service right now — `team`/`vacature`/`tool` land in
 * T06-T08. A `assertSame(POST_TYPES, registeredSlugs)` assertion today would
 * either force this test to special-case the three not-yet-built slugs
 * (weakening the check to fit an incomplete world) or fail permanently until
 * T08 lands (blocking every task in between on a check this task cannot
 * honestly satisfy). Per the T05 brief: assert what is honestly checkable
 * NOW, structured so the final assertion is a one-line change later.
 *
 * THE HONEST CHECK TODAY: `case` is a member of POST_TYPES AND is a really
 * registered ntdst content model (via NTDST_Data_Manager::isRegistered(),
 * which does NOT auto-create a phantom model as a side effect — unlike
 * get()). This naturally STRENGTHENS as T06-T08 land: each future task's own
 * test file registers its service (the same pattern CaseServiceRegistrationTest
 * establishes for `case`), and this file's TODO below marks exactly where to
 * widen ONE_TRUE_REGISTERED_SLUGS to make the check a full equality assertion
 * — the shape of the check never changes, only the fixture list grows.
 */
final class PostTypeRegistryConsistencyTest extends WP_UnitTestCase
{
    /**
     * TODO(T06): add 'team' once TeamService registers it.
     * TODO(T07): add 'vacature' and 'tool' once VacatureService/ToolService
     * register them. Once all four are present, replace
     * test_every_post_type_pending_registration_is_named_honestly() below
     * with a single `assertSame(AdminUIService::POST_TYPES,
     * $registeredSlugs)` — the one-line strengthening this test is
     * structured for.
     *
     * @var list<string>
     */
    private const KNOWN_REGISTERED_SLUGS = ['case'];

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/CaseService.php';

        if (! defined('JOSWORLD_CORE_META_PREFIX')) {
            define('JOSWORLD_CORE_META_PREFIX', '_jw_');
        }
        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        new CaseService(ntdst_data());
    }

    public function test_post_types_constant_is_publicly_readable(): void
    {
        // The read-access half of CB-4: a consuming test/reviewer can name
        // the list without instantiating the service or duplicating it.
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

    public function test_every_post_type_pending_registration_is_named_honestly(): void
    {
        // The three slugs T06-T08 have not landed yet. This assertion is the
        // deliberately narrow half of CB-4: it does NOT claim they are
        // registered (they are not), it only proves the KNOWN_REGISTERED_SLUGS
        // fixture above — the thing this file widens per T06-T08 — has not
        // silently drifted out of sync with POST_TYPES by gaining an entry
        // POST_TYPES itself no longer carries.
        foreach (self::KNOWN_REGISTERED_SLUGS as $slug) {
            $this->assertContains(
                $slug,
                AdminUIService::POST_TYPES,
                "'{$slug}' is claimed as a known-registered slug but is missing from POST_TYPES",
            );
        }

        $pending = array_diff(AdminUIService::POST_TYPES, self::KNOWN_REGISTERED_SLUGS);
        $this->assertSame(
            ['team', 'vacature', 'tool'],
            array_values($pending),
            'once T06-T08 register a pending slug, move it into KNOWN_REGISTERED_SLUGS above — '
            . 'this assertion is the tripwire that keeps that migration honest',
        );

        foreach ($pending as $slug) {
            $this->assertFalse(
                ntdst_data()->isRegistered($slug),
                "'{$slug}' is registered but still listed as pending in KNOWN_REGISTERED_SLUGS — "
                . 'move it up once its service lands (this is the one-line strengthening point)',
            );
        }
    }
}
