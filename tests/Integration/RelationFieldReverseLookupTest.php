<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use NTDST_RelationField;
use NTDST_Theme;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Regression cover for the ntdst-core `NTDST_RelationField` reverse-lookup
 * fix (2026-08-03, found while building T06).
 *
 * WHY THIS TEST LIVES IN josworld AND TESTS ntdst-core: ntdst-core is a
 * shared framework vendored per-site with no test suite of its own. josworld
 * is the site that exposed the defect, so it carries the proof. When the fix
 * is propagated to the other sites, this file travels with it as the
 * acceptance test.
 *
 * THE TWO DEFECTS, both of which made findReferringPosts() return an empty
 * set — rendering an empty reverse metabox that is indistinguishable from
 * "this item genuinely has no referrers":
 *
 *   1. META PREFIX — present in EVERY deployed ntdst-core copy. The lookup
 *      queried `meta_key = <field_name>` with the raw name, but a model
 *      registered with a `meta_prefix` stores `<prefix><field>`. josworld's
 *      `case` model (meta_prefix `_jw_`) stores `_jw_team`, so a query for
 *      `team` matched nothing.
 *
 *   2. STORAGE FORMAT — a regression in the newer copies (josworld, daan,
 *      stride, edushare, sc1-stackwp, stride-output-reshape). A previous
 *      revision asserted "Data.php stores relation values as JSON arrays"
 *      and swapped a working serialized-pattern LIKE for JSON-shaped ones.
 *      That premise is false: relation values are PHP arrays passed to
 *      update_post_meta(), which WordPress serializes. Verified live:
 *      `_jw_team => a:2:{i:0;i:11;i:1;i:22;}`. The OLDER copies (acerta,
 *      ludoluykx, netdust, rossi) had this right.
 *
 * Each test below fails if its defect is reintroduced — verified by
 * mutation, independently, one at a time.
 *
 * These call the private method directly by reflection ON PURPOSE: the
 * public path renders a metabox (HTML in an admin screen), and asserting on
 * markup would couple this regression proof to presentation. The lookup is
 * the unit whose contract broke.
 */
final class RelationFieldReverseLookupTest extends WP_UnitTestCase
{
    /**
     * @return list<\WP_Post>
     */
    private function findReferring(int $postId, string $postType, string $field): array
    {
        $service = new NTDST_RelationField(ntdst_get(NTDST_Theme::class));
        $method = new ReflectionMethod($service, 'findReferringPosts');
        $method->setAccessible(true);

        /** @var list<\WP_Post> */
        return $method->invoke($service, $postId, $postType, $field);
    }

    private function makePost(string $postType = 'post'): int
    {
        $id = wp_insert_post([
            'post_type' => $postType,
            'post_title' => 'Fixture ' . $postType,
            'post_status' => 'publish',
        ]);

        return (int) $id;
    }

    private function publish(int|object $created): int
    {
        $id = is_int($created) ? $created : (int) $created->ID;
        wp_update_post(['ID' => $id, 'post_status' => 'publish']);

        return $id;
    }

    /**
     * DEFECT 1 — the prefixed case. This is josworld's real shape: a `case`
     * model with meta_prefix `_jw_` and a `team` relation.
     */
    public function test_a_prefixed_model_resolves_its_referring_posts(): void
    {
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/CaseService.php';
        new CaseService(ntdst_data());

        $member = $this->makePost();
        $model = ntdst_data()->get('case');

        $this->publish($model->create(['title' => 'Referring A', 'client' => 'A', 'team' => [$member]]));
        $this->publish($model->create(['title' => 'Referring B', 'client' => 'B', 'team' => [$member]]));
        $this->publish($model->create(['title' => 'Unrelated', 'client' => 'C', 'team' => []]));

        $titles = array_map(
            static fn (object $post): string => $post->post_title,
            $this->findReferring((int) $member, 'case', 'team'),
        );
        sort($titles);

        $this->assertSame(
            ['Referring A', 'Referring B'],
            $titles,
            'the lookup must find posts whose relation meta is stored under the '
            . "model's meta_prefix (_jw_team), not only under the bare field name",
        );
    }

    /**
     * BACK-COMPAT — a model with NO meta_prefix, which is how every other
     * ntdst site's models are registered. The fix must not regress them.
     */
    public function test_an_unprefixed_model_still_resolves(): void
    {
        ntdst_data()->register('legacy_item', [
            'label' => 'Legacy Item',
            'fields' => ['linked' => ['type' => 'relation', 'post_type' => 'post', 'multiple' => true]],
        ]);

        $target = $this->makePost();
        $this->publish(ntdst_data()->get('legacy_item')->create([
            'title' => 'Legacy One',
            'linked' => [$target],
        ]));

        $found = $this->findReferring((int) $target, 'legacy_item', 'linked');

        $this->assertCount(1, $found, 'an unprefixed model (every other ntdst site) must still resolve');
        $this->assertSame('Legacy One', $found[0]->post_title);
    }

    /**
     * DEFECT 2 — the serialized format WordPress actually writes. Reverting
     * to JSON-only LIKE patterns fails this.
     */
    public function test_php_serialized_relation_values_resolve(): void
    {
        ntdst_data()->register('serialized_item', [
            'label' => 'Serialized Item',
            'fields' => ['linked' => ['type' => 'relation', 'post_type' => 'post', 'multiple' => true]],
        ]);

        $target = $this->makePost();
        $host = $this->makePost('serialized_item');

        // Exactly what update_post_meta() on a PHP array produces.
        update_post_meta($host, 'linked', [$target, 999]);

        $this->assertCount(
            1,
            $this->findReferring((int) $target, 'serialized_item', 'linked'),
            'PHP-serialized relation values are what WordPress actually stores — '
            . 'a JSON-only LIKE never matches them',
        );
    }

    /**
     * The JSON shape stays supported too — the fix is additive, not a swap
     * back to the old behaviour.
     */
    public function test_json_shaped_relation_values_still_resolve(): void
    {
        ntdst_data()->register('json_item', [
            'label' => 'JSON Item',
            'fields' => ['linked' => ['type' => 'relation', 'post_type' => 'post', 'multiple' => true]],
        ]);

        $target = $this->makePost();
        $host = $this->makePost('json_item');
        update_post_meta($host, 'linked', '[' . $target . ']');

        $this->assertCount(
            1,
            $this->findReferring((int) $target, 'json_item', 'linked'),
            'a JSON-shaped relation value must still resolve',
        );
    }

    /**
     * The LIKE narrowing is deliberately loose, so the exact in_array()
     * verification is what prevents ID 6 matching inside ID 60. Without it
     * the serialized pattern `%i:6;%` would also hit `i:60;` in some shapes.
     */
    public function test_a_substring_id_is_not_a_false_positive(): void
    {
        ntdst_data()->register('precision_item', [
            'label' => 'Precision Item',
            'fields' => ['linked' => ['type' => 'relation', 'post_type' => 'post', 'multiple' => true]],
        ]);

        $target = $this->makePost();
        $host = $this->makePost('precision_item');

        // Link a DIFFERENT id that contains the target's digits as a prefix.
        update_post_meta($host, 'linked', [(int) ($target . '9')]);

        $this->assertSame(
            [],
            $this->findReferring((int) $target, 'precision_item', 'linked'),
            'an id whose digits appear inside another id must not match',
        );
    }
}
