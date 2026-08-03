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

    public function test_make_returns_a_fresh_instance_on_every_call(): void
    {
        $first = $this->container->make(ContainerTestService::class);
        $second = $this->container->make(ContainerTestService::class);

        $this->assertNotSame($first, $second);
        $this->assertInstanceOf(ContainerTestService::class, $first);
    }

    public function test_forget_causes_re_resolution_to_a_new_instance(): void
    {
        $this->container->set(ContainerTestService::class);
        $before = $this->container->get(ContainerTestService::class);

        $this->container->forget(ContainerTestService::class);
        $this->container->set(ContainerTestService::class);
        $after = $this->container->get(ContainerTestService::class);

        $this->assertNotSame($before, $after);
    }

    public function test_factory_closure_receives_the_container_itself(): void
    {
        $received = null;
        $this->container->set('tagged.service', function (NTDST_Container $c) use (&$received): ContainerTestService {
            $received = $c;
            $service = $c->make(ContainerTestService::class);
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

    public function test_make_rejects_unknown_constructor_parameters(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown parameter(s)');

        $this->container->make(ContainerTestService::class, ['typoedParam' => 1]);
    }
}
