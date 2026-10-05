<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use WP_UnitTestCase;

/**
 * The case editor screen against real WordPress: admin list columns are
 * real hook wiring, and the model registers a single untabbed metabox now
 * that it carries only its card field. Brain Monkey can assert none of
 * this — the hooks and the metabox generator's registry have to be real.
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

    public function test_case_admin_list_adds_client_and_diensten_columns_but_not_impact_count(): void
    {
        $columns = apply_filters('manage_case_posts_columns', [
            'cb' => '<input type="checkbox">',
            'title' => 'Title',
            'date' => 'Date',
        ]);

        $this->assertArrayHasKey('jw_client', $columns, 'expected a client column on the case admin list');
        $this->assertArrayHasKey('jw_diensten', $columns, 'expected a diensten column on the case admin list');
        $this->assertArrayNotHasKey(
            'jw_impact_count',
            $columns,
            'the impact field is gone — no impact-count column may remain on the case admin list',
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
    // Tabs (removed — the card is one field, one flat metabox)
    // ------------------------------------------------------------------

    public function test_case_registers_a_single_untabbed_metabox(): void
    {
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

        $this->assertFalse(
            $config['use_tabs'] ?? false,
            'the case card is a single field — it must register one untabbed metabox',
        );

        $this->assertEmpty(
            $config['field_groups'] ?? [],
            'a card with no tabs must declare no field_groups',
        );

        $this->assertSame(
            'side',
            $config['metabox_context'] ?? 'normal',
            'the card is one field — it belongs in the side panel, beside publish and the taxonomy',
        );
    }
}
