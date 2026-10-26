<?php

/**
 * Stand-ins for the two Matomo classes whose callbacks print the tracker: the wptests DB runs no
 * plugins. Each records that WordPress called it.
 *
 * phpcs:disable PSR1.Files.SideEffects, PSR1.Classes.ClassDeclaration.MultipleClasses
 */

declare(strict_types=1);

namespace WpMatomo;

if (!class_exists(TrackingCode::class, false)) {
    class TrackingCode
    {
        /** @var list<string> */
        public array $calls = [];

        public function add_javascript_code(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName
        {
            $this->calls[] = 'add_javascript_code';
        }

        public function add_noscript_code(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName
        {
            $this->calls[] = 'add_noscript_code';
        }
    }
}

if (!class_exists(AIBotTracking::class, false)) {
    class AIBotTracking
    {
        /** @var list<string> */
        public array $calls = [];

        public function do_ai_bot_tracking(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName
        {
            $this->calls[] = 'do_ai_bot_tracking';
        }
    }
}
