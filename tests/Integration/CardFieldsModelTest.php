<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use WP_UnitTestCase;

/**
 * Cluster B's behaviour RED (specs/cpt-builder-singles): a model is its
 * card. Each model declares exactly the fields another page needs to show
 * it; everything narrative lives in the builder.
 *
 * @group cluster-red
 */
final class CardFieldsModelTest extends WP_UnitTestCase
{
    /** @var array<string, list<string>> */
    private const CARD_FIELDS = [
        'case' => ['client'],
        'tool' => ['subtitle', 'file'],
        'team' => ['role', 'bio', 'email', 'phone'],
        'vacature' => ['employment_type'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['CaseService', 'ToolService', 'TeamService', 'VacatureService'] as $service) {
            require_once __DIR__ . "/../../app/content/mu-plugins/josworld-core/services/content/{$service}.php";
        }

        if (! defined('JOSWORLD_CORE_META_PREFIX')) {
            define('JOSWORLD_CORE_META_PREFIX', '_jw_');
        }
        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        new CaseService(ntdst_data());
        new ToolService(ntdst_data());
        new TeamService(ntdst_data());
        new VacatureService(ntdst_data());
    }

    public function test_each_model_declares_exactly_its_card_fields(): void
    {
        foreach (self::CARD_FIELDS as $type => $expected) {
            $this->assertSame(
                $expected,
                array_keys(ntdst_data()->get($type)->getSchema()),
                "{$type} must declare exactly its card fields",
            );
        }
    }

    public function test_no_model_declares_a_relation(): void
    {
        $relations = [];

        foreach (array_keys(self::CARD_FIELDS) as $type) {
            foreach (ntdst_data()->get($type)->getSchema() as $name => $config) {
                if (($config['type'] ?? '') === 'relation') {
                    $relations[] = [$type, $name];
                }
            }
        }

        $this->assertSame(
            [],
            $relations,
            'every relation moved into the builder — a grid of manually selected posts',
        );
    }
}
