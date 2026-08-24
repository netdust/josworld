<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use WP_UnitTestCase;

/**
 * The REAL body of ntdst_get_post_meta_from_cache() — the YOOtheme
 * bridge's one meta-read seam — against live WordPress. The unit tier
 * (FieldResolverTest) stubs this global on purpose, which is how its
 * pre-5.0 body shipped calling NTDST_Data_Manager::getPostMetaFromCache(),
 * a method NO core version ever had: green units, fatal on the first real
 * dynamic-field resolution.
 *
 * The contract FieldResolver::resolve() depends on: a map keyed by RAW
 * PREFIXED meta keys (`_jw_client`), scalar values as stored, array values
 * (repeaters) unserialized to real arrays — never a wrapping list, never
 * a formatted/unprefixed model row.
 */
final class CachedMetaAccessorTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2)
            . '/app/content/themes/josworld/services/yootheme/ntdst-cached-meta-accessor.php';
    }

    public function test_returns_raw_prefixed_scalar_meta(): void
    {
        $postId = (int) wp_insert_post([
            'post_type' => 'case',
            'post_title' => 'Accessor fixture',
            'post_status' => 'publish',
        ]);
        update_post_meta($postId, '_jw_client', 'Acme BV');

        $meta = \ntdst_get_post_meta_from_cache($postId);

        $this->assertSame('Acme BV', $meta['_jw_client'] ?? null);
    }

    public function test_returns_a_repeater_as_a_real_unserialized_array(): void
    {
        $rows = [
            ['figure' => '12', 'description' => 'nieuwe hires'],
            ['figure' => '3x', 'description' => 'meer instroom'],
        ];

        $postId = (int) wp_insert_post([
            'post_type' => 'case',
            'post_title' => 'Accessor repeater fixture',
            'post_status' => 'publish',
        ]);
        update_post_meta($postId, '_jw_impact', $rows);

        $meta = \ntdst_get_post_meta_from_cache($postId);

        $this->assertSame(
            $rows,
            $meta['_jw_impact'] ?? null,
            'a stored repeater must come back as the rows array — a serialized '
            . 'string here means FieldResolver::resolveRepeater() drops every row',
        );
    }

    public function test_a_post_with_no_meta_returns_an_array(): void
    {
        $postId = (int) wp_insert_post([
            'post_type' => 'case',
            'post_title' => 'Bare fixture',
            'post_status' => 'publish',
        ]);

        $this->assertSame([], \ntdst_get_post_meta_from_cache($postId));
    }
}
