<?php

/**
 * ToolGrantsMatomo — keeps the Matomo tracker off a visitor's personal link.
 *
 * The token is in the link's URL; Matomo would record it. The plugin prints
 * its tracker from `WpMatomo\TrackingCode` callbacks on `wp_head`/`wp_footer`
 * and sends the URL from `WpMatomo\AIBotTracking::do_ai_bot_tracking` on
 * `wp_footer`. Its `matomo_tracking_code_*` filters run only when the stored
 * code is regenerated, so the callbacks themselves are taken off the hooks.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\site;

use ntdst\baseline\services\grants\LinkRoute;

defined('ABSPATH') || exit;

final class ToolGrantsMatomo
{
    private const HOOKS = ['wp_head', 'wp_footer'];

    public function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        add_action('wp_head', [$this, 'dropTracker'], 0);
    }

    public function dropTracker(): void
    {
        if (! class_exists(LinkRoute::class) || ! LinkRoute::isGrantRequest()) {
            return;
        }

        foreach (self::HOOKS as $hook) {
            foreach ($GLOBALS['wp_filter'][$hook]->callbacks ?? [] as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if (self::isTracker($callback['function'])) {
                        remove_action($hook, $callback['function'], $priority);
                    }
                }
            }
        }
    }

    private static function isTracker(mixed $function): bool
    {
        if (! is_array($function) || ! is_object($function[0] ?? null)) {
            return false;
        }

        [$object, $method] = $function;

        // Named as strings: the plugin may be absent.
        return is_a($object, 'WpMatomo\TrackingCode')
            || (is_a($object, 'WpMatomo\AIBotTracking') && $method === 'do_ai_bot_tracking');
    }
}
