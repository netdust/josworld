<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use Brain\Monkey\Functions;

final class GreetingRenderer
{
    public function render(string $name): string
    {
        return esc_html('Hello, ' . $name);
    }
}

/**
 * Proves Brain Monkey actually intercepts WordPress functions at the
 * unit tier — i.e. code that calls WP functions is testable with no
 * WordPress bootstrap at all.
 */
final class BrainMonkeyFunctionsTest extends TestCase
{
    public function test_stubbed_wp_function_is_intercepted_by_brain_monkey(): void
    {
        Functions\when('esc_html')->returnArg();

        $output = (new GreetingRenderer())->render('World');

        $this->assertSame('Hello, World', $output);
    }

    public function test_expected_wp_function_call_is_verified_with_exact_argument(): void
    {
        Functions\expect('esc_html')
            ->once()
            ->with('Hello, Tester')
            ->andReturn('Hello, Tester');

        $output = (new GreetingRenderer())->render('Tester');

        $this->assertSame('Hello, Tester', $output);
    }
}
