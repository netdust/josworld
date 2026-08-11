<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use WP_UnitTestCase;

/**
 * Integration tier: T10 — the two cross-cutting model conventions asserted
 * across EVERY josworld model AT ONCE (FR-12, SC-5), plus the AF-1 denial
 * edge for the metabox save guard.
 *
 * THE PROBLEM THIS SIBLING-SITE AUDIT NAMES: a per-model test
 * (CaseServiceRegistrationTest's "every meta key starts with _jw_"
 * assertion, for example) proves the convention for THAT model only. A
 * fifth model added later — by a task that never touches
 * CaseServiceRegistrationTest.php — silently escapes every existing check.
 * This file iterates the REAL registry (never a hardcoded model list) so a
 * new model is automatically in scope the moment it registers with a
 * `meta_prefix`, with no test file anywhere needing an edit.
 *
 * THREE CLAUSES, in the order tasks.md states them:
 *
 * 1) Every meta key on every registered josworld model matches `^_jw_`.
 *    Scoped by the SAME boundary PostTypeRegistryConsistencyTest already
 *    established: "a model this project owns" = registered through
 *    ntdst_data() AND carrying JOSWORLD_CORE_META_PREFIX as its meta_prefix
 *    (commit 2287cc6). `dienst` is deliberately excluded — it is a
 *    TAXONOMY (CaseService: `ntdst_get(\NTDST_Theme::class)->taxonomy(...)`),
 *    not a Data model; it carries no meta_prefix and
 *    `ntdst_data()->isRegistered('dienst')` is false, so it is out of scope
 *    by the same registry-membership test every model must pass to be
 *    counted at all — not a special case carved out by this file.
 *
 * 2) A static scan of the josworld-core plugin directory finds ZERO direct
 *    `register_post_type()` / `register_taxonomy()` calls. Real
 *    registration converges on `ntdst_data()->register()` (which calls
 *    register_post_type() itself, inside ntdst-core, never inside
 *    josworld-core) and `NTDST_Theme::taxonomy()`. Comments/docblocks
 *    explaining this invariant must NOT be mistaken for violations — this
 *    file's own strip-comments step is proven against a violating AND a
 *    commented-only fixture before being trusted on the real tree, the same
 *    self-proving pattern JosworldCoreBootBlockTest and
 *    MultilingualServiceAbsenceTest already established for this project.
 *
 * 3) The framework's nonce + current_user_can('edit_post') guards in
 *    NTDST_MetaboxGenerator::save_metabox_data() actually REJECT an
 *    unauthorised metabox POST — the AF-1 "denied" edge. Not "the guard is
 *    present in the source" (a source-scan cannot prove a runtime
 *    rejection) but that persisted meta is UNCHANGED after the real
 *    `save_post` hook fires with (a) no nonce, (b) a forged nonce, and
 *    (c) a valid nonce from a user who lacks `edit_post` on that post. The
 *    seam is un-mocked: registering CaseService wires the REAL
 *    NTDST_MetaboxGenerator singleton onto the REAL `save_post` action
 *    (Data.php:1547-1552 auto-registers metaboxes on register()), so
 *    `wp_update_post()` below drives the actual guarded save path, not a
 *    stand-in for it.
 */
final class ModelConventionsTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

    /**
     * Fixture post types registered by the mutation-proof tests below
     * (clause 1) must NOT leak into the shared, process-lifetime
     * `NTDST_Data_Manager::$models` static registry, or a sibling test
     * file's OWN registry-scoped assertion (e.g.
     * PostTypeRegistryConsistencyTest's forward CB-4 check, which is
     * scoped by the very same `_jw_` meta prefix this file's fixtures
     * deliberately use) would start failing for a reason that has nothing
     * to do with its own contract — the exact test-pollution hazard
     * PostTypeRegistryConsistencyTest's own docblock already names for
     * DataLayerRoundTripTest's `gate_item` fixture.
     */
    protected function tearDown(): void
    {
        foreach (['jw_fx_other_prefix', 'jw_fx_new_model'] as $fixtureSlug) {
            if (post_type_exists($fixtureSlug)) {
                unregister_post_type($fixtureSlug);
            }

            $this->forget_registered_model($fixtureSlug);
        }

        parent::tearDown();
    }

    private function forget_registered_model(string $slug): void
    {
        $reflection = new \ReflectionClass(\NTDST_Data_Manager::class);
        if (! $reflection->hasProperty('models')) {
            return;
        }

        $modelsProp = $reflection->getProperty('models');
        $modelsProp->setAccessible(true);
        $models = $modelsProp->getValue();

        if (isset($models[$slug])) {
            unset($models[$slug]);
            $modelsProp->setValue(null, $models);
        }
    }

    // ------------------------------------------------------------------
    // Clause 1: every meta key on every registered josworld model matches
    // ^_jw_ — asserted by ENUMERATING THE REGISTRY, not a hardcoded list.
    // ------------------------------------------------------------------

    /**
     * The registry-derived model set. A fifth model that registers with
     * JOSWORLD_CORE_META_PREFIX is automatically included here the moment
     * it exists — this is the whole point of the sibling-site audit named
     * in tasks.md, and the reason a hardcoded
     * ['case','team','vacature','tool'] loop would reproduce the exact bug
     * this task exists to prevent.
     *
     * @return list<string>
     */
    private function josworld_owned_model_slugs(): array
    {
        return array_values(array_filter(
            get_post_types([], 'names'),
            static function (string $slug): bool {
                if (! ntdst_data()->isRegistered($slug)) {
                    return false;
                }

                return ntdst_data()->get($slug)->getMetaPrefix() === JOSWORLD_CORE_META_PREFIX;
            },
        ));
    }

    public function test_every_meta_key_on_every_registered_josworld_model_carries_the_jw_prefix(): void
    {
        $slugs = $this->josworld_owned_model_slugs();

        $this->assertNotEmpty(
            $slugs,
            'precondition: at least one josworld model must be registered, or the sweep below '
            . 'passes vacuously over an empty set',
        );

        foreach ($slugs as $slug) {
            $model = ntdst_data()->get($slug);
            $fields = $model->getSchema();

            $this->assertNotEmpty(
                $fields,
                "precondition: model '{$slug}' must declare at least one field for this sweep "
                . 'to assert anything about its meta keys',
            );

            $reflection = new \ReflectionClass($model);
            $prefixMethod = $reflection->getMethod('prefixMetaKey');
            $prefixMethod->setAccessible(true);

            foreach (array_keys($fields) as $fieldName) {
                $metaKey = $prefixMethod->invoke($model, $fieldName);

                $this->assertMatchesRegularExpression(
                    '/^_jw_/',
                    $metaKey,
                    "model '{$slug}' field '{$fieldName}' persists as meta key '{$metaKey}', "
                    . 'which does not carry the required `_jw_` prefix (FR-12/SC-5)',
                );
            }
        }
    }

    /**
     * Mutation proof: a model registered WITHOUT the `_jw_` meta_prefix (or
     * with a different prefix) must be REJECTED by the enumeration itself —
     * proving the registry filter that scopes clause 1 is behavioral, not
     * accidentally permissive. If this filter ever degraded to "every
     * registered post type" (dropping the meta_prefix check), a
     * third-party or differently-prefixed model would silently enter the
     * sweep and could pass or fail for reasons that have nothing to do
     * with josworld's own convention.
     */
    public function test_a_model_registered_with_a_different_meta_prefix_is_excluded_from_the_sweep(): void
    {
        register_post_type('jw_fx_other_prefix', ['public' => true, 'show_ui' => true]);
        ntdst_data()->register('jw_fx_other_prefix', [
            'label' => 'Fixture (foreign prefix)',
            'public' => true,
            'fields' => ['some_field' => 'text'],
            'meta_prefix' => '_other_',
            'auto_metabox' => false,
        ]);

        $slugs = $this->josworld_owned_model_slugs();

        $this->assertNotContains(
            'jw_fx_other_prefix',
            $slugs,
            'MUTATION PROOF: a model registered under a DIFFERENT meta_prefix must not be '
            . "swept into josworld's own `_jw_` convention check — the registry filter must "
            . 'discriminate on meta_prefix, not merely on "is it registered somewhere"',
        );
    }

    /**
     * Mutation proof (positive direction): a NEW model that registers with
     * JOSWORLD_CORE_META_PREFIX after setUp() — i.e. the "fifth model
     * added later" tasks.md warns about — is automatically picked up by
     * the same enumeration with NO edit to this test file. This is the
     * actual guarantee the sibling-site audit exists to provide.
     */
    public function test_a_new_model_registered_with_the_jw_prefix_is_automatically_swept_in(): void
    {
        register_post_type('jw_fx_new_model', ['public' => true, 'show_ui' => true]);
        ntdst_data()->register('jw_fx_new_model', [
            'label' => 'Fixture (future fifth model)',
            'public' => true,
            'fields' => ['some_field' => 'text'],
            'meta_prefix' => JOSWORLD_CORE_META_PREFIX,
            'auto_metabox' => false,
        ]);

        $slugs = $this->josworld_owned_model_slugs();

        $this->assertContains(
            'jw_fx_new_model',
            $slugs,
            'a model registered with JOSWORLD_CORE_META_PREFIX must be swept in automatically — '
            . 'no hardcoded model list may gate this enumeration',
        );
    }

    // ------------------------------------------------------------------
    // Clause 2: static scan — zero direct register_post_type()/
    // register_taxonomy() calls anywhere in josworld-core.
    // ------------------------------------------------------------------

    private const PLUGIN_DIR = __DIR__ . '/../../app/content/mu-plugins/josworld-core';

    public function test_no_file_in_josworld_core_calls_register_post_type_or_register_taxonomy_directly(): void
    {
        $this->assertDirectoryExists(self::PLUGIN_DIR);

        $violations = [];

        foreach ($this->php_files_in(self::PLUGIN_DIR) as $file) {
            $source = (string) file_get_contents($file);
            $calls = $this->find_direct_registration_calls($source);

            foreach ($calls as $call) {
                $violations[] = basename($file) . ': ' . $call;
            }
        }

        $this->assertSame(
            [],
            $violations,
            'josworld-core must never call register_post_type()/register_taxonomy() directly — '
            . 'real registration converges on ntdst_data()->register() and '
            . 'NTDST_Theme::taxonomy() (FR-12/SC-5 architecture invariant). Found: '
            . implode(', ', $violations),
        );
    }

    /**
     * Mutation proof (violating case): a fixture calling
     * register_post_type() directly, with no guard, no comment trick, must
     * be flagged.
     */
    public function test_a_direct_register_post_type_call_in_a_fixture_is_flagged(): void
    {
        $fixture = <<<'PHP'
            <?php
            function bad(): void {
                register_post_type('rogue', ['public' => true]);
            }
            PHP;

        $calls = $this->find_direct_registration_calls($fixture);

        $this->assertContains('register_post_type', $calls);
    }

    public function test_a_direct_register_taxonomy_call_in_a_fixture_is_flagged(): void
    {
        $fixture = <<<'PHP'
            <?php
            function bad(): void {
                register_taxonomy('rogue_tax', 'case', []);
            }
            PHP;

        $calls = $this->find_direct_registration_calls($fixture);

        $this->assertContains('register_taxonomy', $calls);
    }

    /**
     * Mutation proof (the exact trap named in the task): a
     * COMMENTED-OUT register_post_type() call — e.g. explanatory docblock
     * prose like the real CaseService.php ships today
     * ("no direct register_taxonomy() call anywhere in josworld-core") —
     * must NOT be flagged. A naive substring/regex scan that does not
     * strip comments first would treat this docblock line as a live
     * violation and the real-file assertion above could never pass while
     * the invariant is honestly documented.
     */
    public function test_a_commented_out_registration_call_is_not_flagged(): void
    {
        $fixture = <<<'PHP'
            <?php
            /**
             * No direct register_taxonomy() call anywhere in this file —
             * // register_post_type('example', []);
             * converges on ntdst_data()->register() instead.
             */
            function good(): void {
                // register_taxonomy('example_tax', 'case', []);
            }
            PHP;

        $calls = $this->find_direct_registration_calls($fixture);

        $this->assertSame(
            [],
            $calls,
            'a commented-out / docblock-quoted register_post_type()/register_taxonomy() call '
            . 'must not be flagged as a violation',
        );
    }

    /**
     * Mutation proof (the inverse trap): a call embedded inside a STRING
     * LITERAL (e.g. an error message quoting the forbidden call) must also
     * not be flagged as a real call — only an actual invocation counts.
     */
    public function test_a_call_named_inside_a_string_literal_is_not_flagged(): void
    {
        $fixture = <<<'PHP'
            <?php
            function good(): void {
                throw new \RuntimeException('do not call register_post_type() here');
            }
            PHP;

        $calls = $this->find_direct_registration_calls($fixture);

        $this->assertSame([], $calls);
    }

    /**
     * @return list<string>
     */
    private function php_files_in(string $dir): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $files[] = $fileInfo->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Token-based scan (not a naive regex/substring match) for a REAL
     * function-call invocation of register_post_type()/register_taxonomy()
     * — comments and string literals are excluded because token_get_all()
     * classifies them as T_COMMENT/T_DOC_COMMENT/T_CONSTANT_ENCAPSED_STRING,
     * never as a T_STRING call token, so this scanner cannot be fooled by
     * either the documentation trap or the string-literal trap proven
     * above.
     *
     * @return list<string>
     */
    private function find_direct_registration_calls(string $source): array
    {
        $tokens = token_get_all($source);
        $targets = ['register_post_type', 'register_taxonomy'];
        $found = [];

        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }

            [$id, $text] = $token;

            if ($id !== T_STRING || ! in_array($text, $targets, true)) {
                continue;
            }

            // Only count it as a CALL if the next non-whitespace token is
            // an opening paren, and the token immediately before it is NOT
            // part of a method-call chain (`->register_post_type(`) or a
            // namespaced/static reference we don't care about here — the
            // targets are global WP functions, so a preceding `->` or
            // `::` would mean this is a differently-named method, not the
            // global function.
            $prev = $tokens[$index - 1] ?? null;
            if (is_array($prev) && in_array($prev[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                continue;
            }

            $next = $tokens[$index + 1] ?? null;
            $isCall = (is_string($next) && $next === '(')
                || (is_array($next) && $next[0] === T_WHITESPACE
                    && ($tokens[$index + 2] ?? null) === '(');

            if ($isCall) {
                $found[] = $text;
            }
        }

        return array_values(array_unique($found));
    }

    // ------------------------------------------------------------------
    // Clause 3: the AF-1 denial edge — nonce + current_user_can('edit_post')
    // actually REJECT an unauthorised metabox POST.
    // ------------------------------------------------------------------

    /**
     * Drives the REAL, un-mocked save_post -> NTDST_MetaboxGenerator::
     * save_metabox_data() chain (wired automatically when CaseService
     * registers — Data.php:1547-1552) with NO nonce in $_POST at all.
     * The framework's `if (!isset($_POST[$nonce_name])) return;` guard
     * must reject the write: the field must remain unset after the save.
     */
    public function test_metabox_save_is_rejected_when_no_nonce_is_present_in_the_post(): void
    {
        $editor = $this->create_user_with_role('editor');
        wp_set_current_user($editor);

        $postId = $this->create_draft_case_post();

        $_POST = [
            'ntdst_fields' => ['client' => 'Attacker-supplied client name'],
            // Deliberately NO 'ntdst_case_nonce' key at all.
        ];

        do_action('save_post', $postId, get_post($postId));

        $_POST = [];

        $this->assertNothingPersistedForClientField(
            $postId,
            'AF-1 denial: a save_post fire with NO nonce present must be rejected — the '
            . "attacker-supplied 'client' value must never reach persisted meta",
        );
    }

    /**
     * Same chain, this time with a nonce KEY present but holding a value
     * that does not verify (a forged/expired/wrong-action nonce). The
     * `wp_verify_nonce()` guard must reject the write.
     */
    public function test_metabox_save_is_rejected_when_the_nonce_does_not_verify(): void
    {
        $editor = $this->create_user_with_role('editor');
        wp_set_current_user($editor);

        $postId = $this->create_draft_case_post();

        $_POST = [
            'ntdst_case_nonce' => 'this-is-not-a-valid-nonce',
            'ntdst_fields' => ['client' => 'Forged-nonce client name'],
        ];

        do_action('save_post', $postId, get_post($postId));

        $_POST = [];

        $this->assertNothingPersistedForClientField(
            $postId,
            'AF-1 denial: a save_post fire with a nonce that FAILS wp_verify_nonce() must be '
            . "rejected — the forged 'client' value must never reach persisted meta",
        );
    }

    /**
     * Same chain, this time with a VALID nonce (so the nonce guard alone
     * would pass) but the current user lacks `edit_post` capability on
     * this specific post — a subscriber. The
     * `current_user_can('edit_post', $post_id)` guard must reject the
     * write on its own, independent of the nonce guard.
     */
    public function test_metabox_save_is_rejected_when_the_current_user_cannot_edit_the_post(): void
    {
        $owner = $this->create_user_with_role('editor');
        $postId = $this->create_draft_case_post($owner);

        $subscriber = $this->create_user_with_role('subscriber');
        wp_set_current_user($subscriber);

        $this->assertFalse(
            current_user_can('edit_post', $postId),
            'precondition: a subscriber must genuinely lack edit_post capability on this post, '
            . 'or the guard below would pass for the wrong reason',
        );

        $nonceAction = 'ntdst_save_case';
        $_POST = [
            'ntdst_case_nonce' => wp_create_nonce($nonceAction),
            'ntdst_fields' => ['client' => 'Subscriber-supplied client name'],
        ];

        do_action('save_post', $postId, get_post($postId));

        $_POST = [];

        $this->assertNothingPersistedForClientField(
            $postId,
            "AF-1 denial: current_user_can('edit_post') must reject the write for a subscriber "
            . 'with a genuinely VALID nonce — the capability guard must fire independently of '
            . 'the nonce guard',
        );
    }

    /**
     * The ALLOWED counterpart (not the point of this test file, but
     * necessary to prove the denial tests above fail for the RIGHT reason
     * — i.e. that this harness is capable of a successful save at all,
     * rather than the ORM/metabox chain being broken in a way that makes
     * every save silently no-op regardless of nonce/capability). A
     * denial test suite with no matching allow-path proof cannot
     * distinguish "correctly rejected" from "everything here is broken".
     */
    public function test_metabox_save_persists_when_nonce_and_capability_are_both_valid(): void
    {
        $editor = $this->create_user_with_role('editor');
        wp_set_current_user($editor);

        $postId = $this->create_draft_case_post();

        $nonceAction = 'ntdst_save_case';
        $_POST = [
            'ntdst_case_nonce' => wp_create_nonce($nonceAction),
            'ntdst_fields' => ['client' => 'Legitimately saved client name'],
        ];

        do_action('save_post', $postId, get_post($postId));

        $_POST = [];

        $model = ntdst_data()->get('case');
        // 'any': the fixture is a DRAFT and find() now defaults publish-only.
        $persisted = $model->find($postId, 'any');

        $this->assertFalse(is_wp_error($persisted), 'precondition: the case must be findable after saving');
        $this->assertSame(
            'Legitimately saved client name',
            $persisted->fields['client'] ?? null,
            'precondition for the denial tests above: a genuinely valid nonce + capability '
            . 'must actually persist the field — otherwise the denial tests could be passing '
            . 'because the save path is broken outright, not because the guards fired',
        );
    }

    /**
     * Creates a real WP user with the given role via wp_insert_user()
     * (rather than self::factory()->user->create(), whose
     * WP_UnitTest_Factory_For_User return type PHPStan cannot resolve —
     * factory.php is not in phpstan.neon's scanFiles) and returns the
     * user ID.
     */
    private function create_user_with_role(string $role): int
    {
        $userId = wp_insert_user([
            'user_login' => 'jw-fixture-' . $role . '-' . wp_generate_password(8, false),
            'user_pass' => wp_generate_password(),
            'role' => $role,
        ]);

        $this->assertIsInt($userId, "precondition: creating a fixture {$role} user must succeed");

        return $userId;
    }

    /**
     * Creates a real draft `case` post via wp_insert_post() (same
     * factory-typing reason as create_user_with_role() above) and returns
     * the post ID.
     */
    private function create_draft_case_post(?int $authorId = null): int
    {
        $postId = wp_insert_post([
            'post_type' => 'case',
            'post_status' => 'draft',
            'post_title' => 'Fixture case for AF-1 denial test',
            'post_author' => $authorId ?? 0,
        ], true);

        $this->assertIsInt($postId, 'precondition: creating the fixture case post must succeed');

        return $postId;
    }

    /**
     * Asserts the `client` field is absent/empty on the persisted `case`
     * model after a save_post fire that should have been denied.
     */
    private function assertNothingPersistedForClientField(int $postId, string $message): void
    {
        $model = ntdst_data()->get('case');
        // 'any': the fixture is a draft; publish-only would 404 the row and
        // make this denial assertion pass vacuously instead of proving the
        // field itself stayed empty.
        $persisted = $model->find($postId, 'any');

        $clientValue = is_wp_error($persisted) ? null : ($persisted->fields['client'] ?? null);

        $this->assertTrue(empty($clientValue), $message);
    }
}
