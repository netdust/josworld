<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use NTDST_Container;
use RuntimeException;

/** Zero-dependency fixture for resolution tests. */
final class ContainerTestService
{
    public string $tag = 'default';
}

/**
 * Behavioral contract of NTDST_Container (ntdst-core), exercised with
 * no WordPress and no database — the example unit for the PHP unit tier.
 *
 * 5.0 trimmed the surface to set()/get()/has(): make(), call(), forget(),
 * flush() and keys() left the class. Fresh-instance and eviction semantics
 * live in set() now — rebinding an ID clears that ID's resolved cache.
 */
final class ContainerTest extends TestCase
{
    private NTDST_Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new NTDST_Container();
    }

    public function test_get_caches_the_resolved_instance_as_a_singleton(): void
    {
        $this->container->set(ContainerTestService::class);

        $first = $this->container->get(ContainerTestService::class);
        $second = $this->container->get(ContainerTestService::class);

        $this->assertSame($first, $second);
    }

    public function test_rebinding_clears_the_resolved_cache(): void
    {
        $this->container->set(ContainerTestService::class);
        $before = $this->container->get(ContainerTestService::class);

        $this->container->set(ContainerTestService::class);
        $after = $this->container->get(ContainerTestService::class);

        $this->assertNotSame($before, $after);
        $this->assertInstanceOf(ContainerTestService::class, $after);
    }

    public function test_get_autowires_an_unregistered_class_that_exists(): void
    {
        $resolved = $this->container->get(ContainerTestService::class);

        $this->assertInstanceOf(ContainerTestService::class, $resolved);
        $this->assertTrue($this->container->has(ContainerTestService::class));
    }

    public function test_factory_closure_receives_the_container_itself(): void
    {
        $received = null;
        $this->container->set('tagged.service', function (NTDST_Container $c) use (&$received): ContainerTestService {
            $received = $c;
            $service = new ContainerTestService();
            $service->tag = 'built-by-factory';

            return $service;
        });

        $resolved = $this->container->get('tagged.service');

        $this->assertSame($this->container, $received);
        $this->assertInstanceOf(ContainerTestService::class, $resolved);
        $this->assertSame('built-by-factory', $resolved->tag);
    }

    public function test_get_throws_for_an_unknown_service_id(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Service totally.unknown not found');

        $this->container->get('totally.unknown');
    }

    public function test_has_answers_false_for_an_unknown_id(): void
    {
        $this->assertFalse($this->container->has('totally.unknown'));
    }
}
