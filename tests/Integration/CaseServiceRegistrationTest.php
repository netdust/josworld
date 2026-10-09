<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use WP_UnitTestCase;

/**
 * The `case` model contract against real WordPress + the wptests DB — a
 * public post type with an archive, and every written meta key carries the
 * `_jw_` prefix. None of this is expressible in Brain Monkey.
 */
final class CaseServiceRegistrationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/CaseService.php';

        // Guarded the same way the entrypoint guards them — boot-order
        // independent.
        if (! defined('JOSWORLD_CORE_META_PREFIX')) {
            define('JOSWORLD_CORE_META_PREFIX', '_jw_');
        }
        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        new CaseService(ntdst_data());
    }

    public function test_registering_the_model_creates_a_public_case_post_type_with_an_archive(): void
    {
        $this->assertTrue(post_type_exists('case'), 'CaseService must register a `case` post type');

        $postType = get_post_type_object('case');
        $this->assertNotNull($postType);
        $this->assertTrue($postType->public, 'the case post type must be public');
        $this->assertTrue($postType->has_archive, 'the case post type must have an archive');
    }

    public function test_every_meta_key_written_by_this_model_starts_with_the_project_prefix(): void
    {
        $model = ntdst_data()->get('case');

        $created = $model->create([
            'title' => 'Full case',
            'client' => 'Acme Corp',
        ]);

        $this->assertNotWPError($created);

        // get_post_meta() with no key enumerates ALL meta rows for the post.
        $allMeta = get_post_meta($created->ID);
        $metaKeys = array_keys($allMeta);

        $this->assertNotEmpty($metaKeys, 'precondition: the full case must have written at least one meta row');

        foreach ($metaKeys as $metaKey) {
            $this->assertStringStartsWith(
                JOSWORLD_CORE_META_PREFIX,
                $metaKey,
                "meta key '{$metaKey}' does not carry the {$this->prefixLabel()} prefix",
            );
        }
    }

    private function prefixLabel(): string
    {
        return JOSWORLD_CORE_META_PREFIX;
    }
}
