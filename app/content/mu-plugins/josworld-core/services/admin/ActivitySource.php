<?php

/**
 * A read-only source of recent activity for the dashboard's panels.
 *
 * Exists so the renderer depends on a contract rather than on two concrete,
 * `final` repositories: the panels need "give me the latest rows" and nothing
 * else, and a test can supply that without either repository being opened up
 * for subclassing. Each implementation decides its own row shape; the panel
 * that renders it knows which keys it asked for.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\admin;

defined('ABSPATH') || exit;

interface ActivitySource
{
    /**
     * The most recent rows, newest first, already capped and escaped-safe
     * (plain strings — escaping happens at output).
     *
     * Returns an empty list rather than throwing when the source is
     * unavailable or the user may not read it.
     *
     * @return list<array<string, mixed>>
     */
    public function latest(): array;
}
