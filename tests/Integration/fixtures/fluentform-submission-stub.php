<?php

/**
 * Stand-in for FluentForm's submission model: the wptests DB runs no plugins. Like the real model,
 * `find()` answers a row object whose `status` attribute holds the stored column, or null.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace FluentForm\App\Models;

if (!class_exists(Submission::class, false)) {
    class Submission
    {
        /** @var array<int, string> id => status */
        public static array $rows = [];

        public static function find(int $id): ?object
        {
            return isset(self::$rows[$id]) ? (object) ['id' => $id, 'status' => self::$rows[$id]] : null;
        }
    }
}
