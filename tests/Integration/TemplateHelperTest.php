<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use WP_UnitTestCase;

/**
 * Pins the theme's template-helper seam to the ntdst-core 5.0 contract:
 * data reaches the template as `$args` ONLY (no loose-variable extraction
 * any more), directories registered through NTDST_Template_Loader::addPath()
 * resolve, and an unresolvable slug fails CLOSED to '' instead of rendering
 * an error div. The helper had no test before this migration; the pre-5.0
 * body called NTDST_Response::addPath(), a method 5.0 removed.
 *
 * One class-static template dir: NTDST_Template_Loader's registry AND its
 * locate() cache are process-static, so a per-test dir leaves later tests
 * resolving to a torn-down path.
 */
final class TemplateHelperTest extends WP_UnitTestCase
{
    private static string $templateDir;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once __DIR__ . '/../../app/content/themes/josworld/helpers/templates.php';

        self::$templateDir = get_temp_dir() . 'jw-template-helper-' . uniqid();
        mkdir(self::$templateDir . '/partials', 0777, true);

        file_put_contents(
            self::$templateDir . '/partials/greeting.php',
            // Reads $args ONLY — the loose-variable form this asserts against
            // would read WP query vars since 5.0, never this data.
            '<p>Hallo <?php echo esc_html($args[\'name\'] ?? \'niemand\'); ?></p>'
        );
        file_put_contents(
            self::$templateDir . '/partials/greeting-formal.php',
            '<p>Dag <?php echo esc_html($args[\'name\'] ?? \'\'); ?></p>'
        );

        \NTDST_Template_Loader::addPath(self::$templateDir);
    }

    public static function tearDownAfterClass(): void
    {
        unlink(self::$templateDir . '/partials/greeting.php');
        unlink(self::$templateDir . '/partials/greeting-formal.php');
        rmdir(self::$templateDir . '/partials');
        rmdir(self::$templateDir);

        parent::tearDownAfterClass();
    }

    public function test_renders_registered_template_with_data_as_args(): void
    {
        $html = josworld_template_html('partials/greeting', null, ['name' => 'Jos']);

        $this->assertSame('<p>Hallo Jos</p>', $html);
    }

    public function test_name_variant_is_appended_to_the_slug(): void
    {
        $html = josworld_template_html('partials/greeting', 'formal', ['name' => 'Jos']);

        $this->assertSame('<p>Dag Jos</p>', $html);
    }

    public function test_unresolvable_slug_fails_closed_to_empty_string(): void
    {
        $this->assertSame('', josworld_template_html('partials/does-not-exist'));
    }

    public function test_template_part_echoes_the_same_render(): void
    {
        ob_start();
        josworld_template_part('partials/greeting', null, ['name' => 'Thijs']);
        $echoed = ob_get_clean();

        $this->assertSame('<p>Hallo Thijs</p>', $echoed);
    }
}
