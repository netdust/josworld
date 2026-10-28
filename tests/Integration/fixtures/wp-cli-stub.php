<?php

/**
 * A stand-in for WP_CLI's output calls, so baseline's `wp ntdst grants gate-files` runs in the
 * test process. It records what the command printed.
 *
 * phpcs:disable PSR1.Files.SideEffects, PSR1.Classes.ClassDeclaration.MissingNamespace
 */

declare(strict_types=1);

if (!class_exists('WP_CLI', false)) {
    class WP_CLI // phpcs:ignore Squiz.Classes.ValidClassName
    {
        /** @var list<string> */
        public static array $lines = [];

        public static function line(string $message): void
        {
            self::$lines[] = $message;
        }

        public static function warning(string $message): void
        {
            self::$lines[] = 'Warning: ' . $message;
        }

        public static function success(string $message): void
        {
            self::$lines[] = 'Success: ' . $message;
        }
    }
}
