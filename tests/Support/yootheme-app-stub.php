<?php

/**
 * `YOOtheme\app()` presence stub for the unit tier — its OWN file, required
 * LAZILY from inside a test method rather than at file-load time.
 *
 * WHY A SEPARATE, LAZILY-REQUIRED FILE (T07):
 *
 * FR-7's entire contract is that `YOOtheme\app` is ABSENT — the mandatory
 * `function_exists('YOOtheme\app')` guard (golden path
 * `ntdst-patterns/golden-paths/yootheme-integration.md:66, :186`) is what
 * keeps a site whose licensed parent theme is missing from fataling. PHP
 * cannot undeclare a function, so declaring this stub anywhere that runs at
 * test-DISCOVERY time (the top of `SourcesServiceTest.php`, or the unit
 * bootstrap) would make the function exist for EVERY test in the run and
 * silently delete that denial path — the FR-7 tests would keep passing while
 * asserting the opposite branch.
 *
 * Requiring it from inside the two positive FR-1 tests keeps "absent" the
 * default state every other test observes, and
 * `SourcesServiceTest::test_the_yootheme_app_function_is_genuinely_absent_at_
 * this_tier()` asserts that precondition explicitly rather than relying on
 * ordering luck.
 *
 * Same posture, and the same class of constraint, as T05's
 * `services/yootheme/ntdst-cached-meta-accessor.php` — see that file's
 * docblock.
 *
 * This is a TEST-ONLY symbol: it stands in for YOOtheme's real
 * `YOOtheme\app()` service-locator (declared by the gitignored parent theme,
 * `.gitignore:138`), whose RETURN VALUE the bridge never uses — the bridge
 * only ever asks `function_exists()` on it as a "is YOOtheme loaded" probe.
 *
 * A stub file by nature declares a symbol and executes a side effect (the
 * function_exists guard).
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace YOOtheme;

if (! function_exists('YOOtheme\app')) {
    /**
     * @param  string|null  $id
     * @return mixed
     */
    function app($id = null)
    {
        return $id;
    }
}
