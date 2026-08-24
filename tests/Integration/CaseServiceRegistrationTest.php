<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use WP_UnitTestCase;

/**
 * The `case` model contract against real WordPress + the wptests DB —
 * every builder template reads these exact keys: a public post type with
 * an archive, repeater rows re-read in order, omitted optional blocks
 * leave NO raw meta row (not an empty string), and every written meta key
 * carries the `_jw_` prefix. None of this is expressible in Brain Monkey.
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

    public function test_a_case_saved_with_three_impact_rows_rereads_three_rows_in_order(): void
    {
        $model = ntdst_data()->get('case');

        $created = $model->create([
            'title' => 'Case with three impact rows',
            'client' => 'Acme Corp',
            'impact' => [
                ['figure' => '+7 mio', 'description' => 'reach'],
                ['figure' => '+42%', 'description' => 'conversion'],
                ['figure' => '3x', 'description' => 'ROI'],
            ],
        ]);

        $this->assertNotWPError($created);

        $reread = $model->find($created->ID, 'any');
        $this->assertNotWPError($reread);

        $impact = $reread->fields['impact'] ?? null;
        $this->assertIsArray($impact);
        $this->assertCount(3, $impact, 'a 3-row impact set must re-read as exactly 3 rows');
        $this->assertSame('+7 mio', $impact[0]['figure']);
        $this->assertSame('reach', $impact[0]['description']);
        $this->assertSame('+42%', $impact[1]['figure']);
        $this->assertSame('conversion', $impact[1]['description']);
        $this->assertSame('3x', $impact[2]['figure']);
        $this->assertSame('ROI', $impact[2]['description']);
    }

    public function test_a_case_saved_with_no_takeaways_and_no_interview_has_no_raw_meta_row_for_either(): void
    {
        $model = ntdst_data()->get('case');

        $created = $model->create([
            'title' => 'Case with no optional blocks',
            'client' => 'Acme Corp',
        ]);

        $this->assertNotWPError($created);

        // Raw postmeta, not the formatted read path: formatMeta() fills `[]`
        // at read time; the criterion is that no row was ever WRITTEN.
        $this->assertFalse(
            metadata_exists('post', $created->ID, JOSWORLD_CORE_META_PREFIX . 'takeaways'),
            'no `_jw_takeaways` meta row may exist when takeaways were never submitted',
        );
        $this->assertFalse(
            metadata_exists('post', $created->ID, JOSWORLD_CORE_META_PREFIX . 'interview'),
            'no `_jw_interview` meta row may exist when interview blocks were never submitted',
        );

        // Reading back resolves the schema-driven default, not an empty string.
        $reread = $model->find($created->ID, 'any');
        $this->assertNotWPError($reread);
        $this->assertSame([], $reread->fields['takeaways']);
        $this->assertSame([], $reread->fields['interview']);
    }

    public function test_every_meta_key_written_by_this_model_starts_with_the_project_prefix(): void
    {
        $model = ntdst_data()->get('case');

        $created = $model->create([
            'title' => 'Full case',
            'client' => 'Acme Corp',
            'tagline' => 'We deliver',
            'what_we_did' => 'Built a thing',
            'target_audience' => 'Enterprises',
            'impact' => [['figure' => '+7 mio', 'description' => 'reach']],
            'challenge_title' => 'The challenge',
            'challenge_body' => 'It was hard',
            'result_title' => 'The result',
            'result_body' => 'It worked',
            'takeaways' => [['title' => 'Lesson one', 'body' => 'Detail']],
            'testimonial_quote' => 'Great work',
            'testimonial_name' => 'Jane Doe',
            'testimonial_role' => 'CEO',
            'interview' => [['title' => 'Q1', 'body' => 'A1']],
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
