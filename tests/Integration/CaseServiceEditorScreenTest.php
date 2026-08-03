<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use WP_UnitTestCase;

/**
 * Integration tier: proves T11's CONTRACT against real WordPress — the risk
 * here lives entirely in real filter/hook registration (admin columns are
 * WP hook wiring Brain Monkey cannot meaningfully assert) and real postmeta
 * round-tripping (a hidden block that silently drops data on save is a
 * storage-layer bug, not a JS bug).
 *
 * THE CONTRACT (tasks.md T11 Integration test line):
 *  1. A `case` registers admin list columns showing client + diensten +
 *     impact-row count (DAAN's registerAdminColumns() pattern).
 *  2. `takeaways` and `interview` carry a `condition` field option keyed to
 *     a controlling boolean field (has_takeaways / has_interview) — this is
 *     OUR code (0 hits for `condition` anywhere in ntdst-core), so nothing
 *     upstream proves it exists or is shaped correctly.
 *  3. A value entered into a hidden/conditional block (takeaways, with
 *     has_takeaways left off) still round-trips on save — disclosure must
 *     never silently drop data. This is the load-bearing assertion: it is
 *     the one a naive "hide with CSS display:none, strip empty fields on
 *     submit" implementation would fail.
 *
 * WHY integration, not unit: admin columns require real
 * `manage_{type}_posts_columns` / `manage_{type}_posts_custom_column` hook
 * firing through WordPress's real admin-list-table machinery, and the
 * round-trip claim requires the real wptests postmeta table — a Brain
 * Monkey stub would let the assertion pass regardless of whether the
 * hooks are wired to the real post type at all.
 */
final class CaseServiceEditorScreenTest extends WP_UnitTestCase
{
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

    // ------------------------------------------------------------------
    // Admin columns (DAAN's registerAdminColumns() pattern)
    // ------------------------------------------------------------------

    public function test_case_admin_list_adds_client_diensten_and_impact_count_columns(): void
    {
        $columns = apply_filters('manage_case_posts_columns', [
            'cb' => '<input type="checkbox">',
            'title' => 'Title',
            'date' => 'Date',
        ]);

        $this->assertArrayHasKey('jw_client', $columns, 'expected a client column on the case admin list');
        $this->assertArrayHasKey('jw_diensten', $columns, 'expected a diensten column on the case admin list');
        $this->assertArrayHasKey(
            'jw_impact_count',
            $columns,
            'expected an impact-row-count column on the case admin list',
        );
    }

    public function test_client_column_renders_the_case_client(): void
    {
        $model = ntdst_data()->get('case');
        $created = $model->create([
            'title' => 'Client column case',
            'client' => 'Acme Corp',
        ]);
        $this->assertNotWPError($created);

        ob_start();
        do_action('manage_case_posts_custom_column', 'jw_client', $created->ID);
        $output = ob_get_clean();

        $this->assertStringContainsString('Acme Corp', $output);
    }

    public function test_impact_count_column_renders_the_row_count(): void
    {
        $model = ntdst_data()->get('case');
        $created = $model->create([
            'title' => 'Impact column case',
            'client' => 'Acme Corp',
            'impact' => [
                ['figure' => '+7 mio', 'description' => 'reach'],
                ['figure' => '+42%', 'description' => 'conversion'],
            ],
        ]);
        $this->assertNotWPError($created);

        ob_start();
        do_action('manage_case_posts_custom_column', 'jw_impact_count', $created->ID);
        $output = ob_get_clean();

        $this->assertStringContainsString(
            '2',
            $output,
            'expected the rendered column to show the impact row count',
        );
    }

    public function test_diensten_column_renders_a_dash_when_no_terms_assigned(): void
    {
        $model = ntdst_data()->get('case');
        $created = $model->create([
            'title' => 'No diensten case',
            'client' => 'Acme Corp',
        ]);
        $this->assertNotWPError($created);

        ob_start();
        do_action('manage_case_posts_custom_column', 'jw_diensten', $created->ID);
        $output = ob_get_clean();

        $this->assertNotSame(
            '',
            trim($output),
            'the diensten column must render SOMETHING (a placeholder) rather than nothing '
            . 'when no terms are assigned',
        );
    }

    // ------------------------------------------------------------------
    // `condition` field option (T11's own code — nothing upstream proves it)
    // ------------------------------------------------------------------

    public function test_takeaways_and_interview_carry_a_condition_tied_to_a_controlling_boolean(): void
    {
        $model = ntdst_data()->get('case');
        $schema = $model->getSchema();

        $this->assertArrayHasKey('takeaways', $schema);
        $this->assertArrayHasKey(
            'condition',
            $schema['takeaways'],
            'takeaways must carry a condition option (T11 acceptance criteria)',
        );
        $this->assertSame(['has_takeaways' => true], $schema['takeaways']['condition']);

        $this->assertArrayHasKey('interview', $schema);
        $this->assertArrayHasKey(
            'condition',
            $schema['interview'],
            'interview must carry a condition option (T11 acceptance criteria)',
        );
        $this->assertSame(['has_interview' => true], $schema['interview']['condition']);

        // The controlling fields themselves must exist and be boolean.
        $this->assertArrayHasKey('has_takeaways', $schema);
        $hasTakeawaysType = is_array($schema['has_takeaways'])
            ? $schema['has_takeaways']['type']
            : $schema['has_takeaways'];
        $this->assertSame('boolean', $hasTakeawaysType);

        $this->assertArrayHasKey('has_interview', $schema);
        $hasInterviewType = is_array($schema['has_interview'])
            ? $schema['has_interview']['type']
            : $schema['has_interview'];
        $this->assertSame('boolean', $hasInterviewType);
    }

    /**
     * THE LOAD-BEARING ASSERTION. A value entered into takeaways (a
     * conditionally-hidden block) round-trips on save regardless of
     * whether the controlling toggle (has_takeaways) was ever set — the
     * ORM/save path must never treat "hidden" as "must be stripped".
     * A naive CSS-hide-and-strip-on-submit implementation, or a save
     * handler that special-cased conditional fields, would fail this.
     */
    public function test_a_value_in_a_hidden_conditional_block_still_round_trips_on_save(): void
    {
        $model = ntdst_data()->get('case');

        // has_takeaways intentionally OMITTED — this is what "the block was
        // hidden, but the editor's browser still submitted its inputs
        // because they were never removed from the DOM" looks like at the
        // save boundary.
        $created = $model->create([
            'title' => 'Hidden block round-trip case',
            'client' => 'Acme Corp',
            'takeaways' => [
                ['title' => 'Lesson one', 'body' => 'Detail that must survive'],
            ],
        ]);

        $this->assertNotWPError($created);

        $reread = $model->find($created->ID, true);
        $this->assertNotWPError($reread);

        $takeaways = $reread->fields['takeaways'] ?? null;
        $this->assertIsArray($takeaways);
        $this->assertCount(
            1,
            $takeaways,
            'a value entered into a conditionally-hidden block must not be silently dropped on save',
        );
        $this->assertSame('Lesson one', $takeaways[0]['title']);
        $this->assertSame('Detail that must survive', $takeaways[0]['body']);
    }

    // ------------------------------------------------------------------
    // Tabs (field_groups + use_tabs) — already wired by T05; this asserts
    // the T11 acceptance criterion holds, not that T11 built it.
    // ------------------------------------------------------------------

    public function test_case_registers_grouped_field_tabs_not_one_flat_wall(): void
    {
        $model = ntdst_data()->get('case');
        $schema = $model->getSchema();

        // Multiple distinct fields must exist so a "flat wall" is even
        // possible, then we assert the model opted into tabs at all.
        $this->assertGreaterThan(
            5,
            count($schema),
            'precondition: the case model has enough fields for tabs to matter',
        );

        // The assertion that actually enforces the T11 criterion.
        //
        // This method previously stopped at the field-count precondition
        // above, while its own comment claimed to assert the tabs criterion.
        // It did not: flipping `use_tabs` to false in CaseService left this
        // whole file green (7/7) — proven by mutation, which is how the gap
        // was found at the Cluster C review gate.
        //
        // `use_tabs`/`field_groups` are NOT on NTDST_Data_Model — getSchema()
        // cannot see them. NTDST_Data_Manager::register() forwards the whole
        // config to the metabox generator (Data.php:1546-1552), which retains
        // it in a private `$registered_models` (MetaboxGenerator.php:19,83).
        // That retained config is the only place the live tab settings exist,
        // so reflection is how this is read — the same pattern this cluster
        // already uses to reach framework internals.
        $generator = ntdst_metabox();
        $property = new \ReflectionProperty($generator, 'registered_models');
        $property->setAccessible(true);
        /** @var array<string, array<string, mixed>> $registered */
        $registered = $property->getValue($generator);

        $this->assertArrayHasKey(
            'case',
            $registered,
            'precondition: the case model must be registered with the metabox generator, '
            . 'or the tab settings below are read from an empty set',
        );

        $config = $registered['case'];

        $this->assertTrue(
            $config['use_tabs'] ?? false,
            'the case edit screen must opt into tabs — without use_tabs the editor gets '
            . 'one flat wall of every field, which is the T11 acceptance criterion this '
            . 'test exists to enforce',
        );

        $this->assertNotEmpty(
            $config['field_groups'] ?? [],
            'use_tabs alone renders nothing useful — field_groups must define the groups, '
            . 'or the screen falls back to a single untabbed block',
        );

        // Groups must actually cover the schema, not just exist: a single
        // group containing everything is a flat wall wearing a tab.
        $this->assertGreaterThan(
            1,
            count($config['field_groups']),
            'more than one field group is required for tabs to group anything — one group '
            . 'is a flat wall with a label on it',
        );
    }
}
