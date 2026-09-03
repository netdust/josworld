<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use NTDST_RelationField;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Regression cover for two NTDST_RelationField::findReferringPosts()
 * defects, both silently yielding an empty reverse metabox: (1) the lookup
 * used the bare field name while prefixed models store `<prefix><field>`;
 * (2) the LIKE patterns matched JSON only, but WP stores relation values
 * PHP-serialized. Lives here because ntdst-core has no suite of its own.
 * Reflection into the private lookup is deliberate — asserting on metabox
 * markup would couple the proof to presentation.
 */
final class RelationFieldReverseLookupTest extends WP_UnitTestCase
{
    /**
     * @return list<\WP_Post>
     */
    private function findReferring(int $postId, string $postType, string $field): array
    {
        $service = new NTDST_RelationField();
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
     * Defect 1, josworld's real shape: a `case` model with meta_prefix
     * `_jw_`. `case` no longer declares a `team` relation field (the card
     * shrink, cluster B) — the lookup itself only cares about the raw,
     * prefixed postmeta row, so the fixture writes it directly rather than
     * through the model's (now narrower) schema.
     */
    public function test_a_prefixed_model_resolves_its_referring_posts(): void
    {
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/CaseService.php';
        new CaseService(ntdst_data());

        $member = $this->makePost();
        $model = ntdst_data()->get('case');

        $referringA = $this->publish($model->create(['title' => 'Referring A', 'client' => 'A']));
        $referringB = $this->publish($model->create(['title' => 'Referring B', 'client' => 'B']));
        $unrelated = $this->publish($model->create(['title' => 'Unrelated', 'client' => 'C']));

        update_post_meta($referringA, '_jw_team', [$member]);
        update_post_meta($referringB, '_jw_team', [$member]);
        update_post_meta($unrelated, '_jw_team', []);

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
     * Back-compat: a model with NO meta_prefix (every other ntdst site)
     * must not regress.
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
     * Defect 2: the serialized format WordPress actually writes. Reverting
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
     * The JSON shape stays supported — the fix is additive, not a swap.
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
     * The LIKE narrowing is loose; the exact in_array() verification is
     * what stops ID 6 matching inside ID 60.
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
