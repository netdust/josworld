<?php

/**
 * A `WP_Post` stand-in for the unit tier, which loads no WordPress. Required
 * lazily from a test's setUp(), the same posture as the retired yootheme-app
 * stub: a file that declares a symbol and aliases it is a side-effect file.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace NtdstTests\Support;

final class WpPostStub
{
    public string $post_type = 'tool';
    public string $post_status = 'publish';
    public string $post_title = 'Checklist';
}

if (! class_exists('WP_Post')) {
    class_alias(WpPostStub::class, 'WP_Post');
}
