<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use WP_UnitTestCase;

/**
 * Cross-cutting model conventions swept across EVERY registered josworld
 * model at once — the registry is enumerated, never hardcoded, so a fifth
 * model is in scope the moment it registers with the `_jw_` meta_prefix:
 *  1. every meta key on every registered model matches `^_jw_`;
 *  2. a static scan finds zero direct register_post_type()/
 *     register_taxonomy() calls in josworld-core — registration converges
 *     on ntdst_data()->register() (taxonomies via its `taxonomies` key);
 *  3. the framework's nonce + edit_post guards actually REJECT an
 *     unauthorised metabox POST through the real, un-mocked save_post
 *     chain.
 * `dienst` is a taxonomy, not a Data model — it fails registry membership,
 * not a special case here.
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
     * Fixture models must not leak into the process-lifetime
     * NTDST_Data_Manager::$models registry — sibling files' registry-scoped
     * sweeps would fail on them.
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
     * The registry-derived model set — a hardcoded slug list would
     * reproduce the exact escape this file exists to prevent.
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
     * The enumeration must discriminate on meta_prefix — a foreign-prefixed
     * model stays out of the sweep.
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
     * A new model registering with the `_jw_` prefix is swept in with no
     * edit to this file.
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
            . 'real registration converges on ntdst_data()->register(), taxonomies included '
            . 'via its `taxonomies` config key. Found: '
            . implode(', ', $violations),
        );
    }

    /**
     * A bare direct register_post_type() call must be flagged.
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
     * A commented-out/docblock-quoted call must NOT be flagged — the real
     * tree documents this invariant in prose, and a naive scan would flag
     * that prose as a violation.
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
     * A call named inside a string literal is not an invocation and must
     * not be flagged.
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
     * Token-based scan: comments and string literals never tokenize as a
     * T_STRING call token, so neither trap above fools it.
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

            // A call: next non-whitespace token is `(`, and no preceding
            // `->`/`::` (that would be a differently-named method, not the
            // global WP function).
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
    // Clause 3: nonce + current_user_can('edit_post') actually REJECT an
    // unauthorised metabox POST.
    // ------------------------------------------------------------------

    /**
     * The real save_post -> save_metabox_data() chain with NO nonce in
     * $_POST: the write must be rejected.
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
     * Nonce key present but failing wp_verify_nonce(): rejected.
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
     * Valid nonce, but the current user (a subscriber) lacks edit_post on
     * this post: the capability guard must reject on its own.
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

        // Post-scoped since 5.0 (MetaboxGenerator::nonce_action()): a nonce
        // minted for one post no longer authorizes a save on another. This
        // must be the VALID action, or the assertion below would pass on
        // nonce failure instead of the capability guard it probes.
        $nonceAction = 'ntdst_save_case_' . $postId;
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
     * The ALLOWED counterpart: without a successful save the denial tests
     * cannot distinguish "correctly rejected" from "save path broken
     * outright".
     */
    public function test_metabox_save_persists_when_nonce_and_capability_are_both_valid(): void
    {
        $editor = $this->create_user_with_role('editor');
        wp_set_current_user($editor);

        $postId = $this->create_draft_case_post();

        // Post-scoped since 5.0 (MetaboxGenerator::nonce_action()).
        $nonceAction = 'ntdst_save_case_' . $postId;
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
     * wp_insert_user() rather than the factory: the factory's return type
     * is invisible to PHPStan (factory.php is not in scanFiles).
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
     * Same factory-typing reason as create_user_with_role().
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
        // 'any': the fixture is a draft and find() defaults publish-only —
        // publish-only would make this denial assertion pass vacuously.
        $persisted = $model->find($postId, 'any');

        $clientValue = is_wp_error($persisted) ? null : ($persisted->fields['client'] ?? null);

        $this->assertTrue(empty($clientValue), $message);
    }
}
