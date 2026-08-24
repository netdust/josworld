<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use WP_UnitTestCase;

/**
 * The case editor screen against real WordPress: admin list columns are
 * real hook wiring, the `condition` field option is this project's own
 * code (nothing upstream proves it), and a value entered into a hidden
 * conditional block must still round-trip on save. Brain Monkey can
 * assert none of this — the hooks and the wptests postmeta table have to
 * be real.
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
    // Admin columns
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
    // `condition` field option
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
        $this->assertSame('bool', $hasTakeawaysType);

        $this->assertArrayHasKey('has_interview', $schema);
        $hasInterviewType = is_array($schema['has_interview'])
            ? $schema['has_interview']['type']
            : $schema['has_interview'];
        $this->assertSame('bool', $hasInterviewType);
    }

    /**
     * THE LOAD-BEARING CASE: a value in a conditionally-hidden block must
     * round-trip regardless of the controlling toggle — a save handler that
     * strips "hidden" fields fails this.
     */
    public function test_a_value_in_a_hidden_conditional_block_still_round_trips_on_save(): void
    {
        $model = ntdst_data()->get('case');

        // has_takeaways deliberately omitted: the hidden block's inputs
        // were still submitted from the DOM.
        $created = $model->create([
            'title' => 'Hidden block round-trip case',
            'client' => 'Acme Corp',
            'takeaways' => [
                ['title' => 'Lesson one', 'body' => 'Detail that must survive'],
            ],
        ]);

        $this->assertNotWPError($created);

        $reread = $model->find($created->ID, 'any');
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
    // Tabs (field_groups + use_tabs)
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

        // use_tabs/field_groups never reach NTDST_Data_Model — the metabox
        // generator's private $registered_models is the only place the live
        // tab settings exist, hence reflection.
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
