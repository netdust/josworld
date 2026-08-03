<?php

/**
 * Admin UI Service — enqueues the metabox field JS (relation autocomplete,
 * gallery picker, repeater add/remove/drag-sort) on josworld's own CPT edit
 * screens.
 *
 * WHY THIS SERVICE EXISTS (see task T04 / plan.md): ntdst-core's
 * NTDST_MetaboxGenerator::enqueue_metabox_scripts() only enqueues
 * `get_stylesheet_directory() . '/assets/dist/theme-services.js'` — a build
 * artefact from the ACTIVE THEME. josworld's theme ships no `assets/dist/`
 * at all, so relation/repeater/gallery fields render as inert markup today
 * regardless of which josworld post type is being edited. Owning the
 * enqueue here, in the plugin, makes the content model work independently
 * of the active theme's build tooling.
 *
 * Shape mirrors DAAN's AdminUIService (services/admin/AdminUIService.php):
 * a plain class resolved through the container (see josworld-core.php's
 * boot block), doing its work via a private init() hooking
 * admin_enqueue_scripts. Unlike DAAN's version, the constructor takes no
 * NTDST_Data_Manager — this service's scoping is a static, spec-derived
 * post-type list (see below), not a data-layer lookup, and phpstan level 6
 * (property.onlyWritten) is right to reject an unused dependency purely for
 * shape-parity with a sibling service.
 *
 * SCOPING DECISION (divergence from "a registered ntdst model" — see task
 * report DIVERGENCES): T05-T08 (the CaseService/TeamService/VacatureService/
 * ToolService content services) do not exist yet, so there is no live
 * registry of "post types with a registered ntdst model" this service could
 * key off today — querying one would scope this enqueue to nothing until
 * T08 lands, silently. The spec (FR-1..FR-4) already names the four post
 * type slugs this plugin owns, so POST_TYPES below is that explicit,
 * spec-derived list — the one basis that is real today and requires no
 * silent future dependency. T05-T08 register post types under these exact
 * slugs; if a future task renames one, this list is the other half of that
 * rename.
 *
 * A service file by nature both declares a symbol (the class below) and
 * executes a side effect (the ABSPATH guard) — exempt from the PSR-1
 * side-effects rule, same posture as josworld-core.php's own entrypoint.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\admin;

defined('ABSPATH') || exit;

final class AdminUIService
{
    /**
     * The josworld post-type slugs this service scopes the metabox field JS
     * to — spec-derived (FR-1..FR-4), not inferred from a runtime registry
     * that does not exist until T05-T08 land.
     *
     * @var list<string>
     */
    private const POST_TYPES = ['case', 'team', 'vacature', 'tool'];

    public function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueMetaboxFieldScripts']);
    }

    /**
     * Enqueue the metabox field JS on a josworld CPT edit screen only.
     *
     * Scoped to post.php/post-new.php (the same hooks ntdst-core's own
     * enqueue_metabox_scripts() checks) AND to POST_TYPES, so unrelated
     * admin screens (dashboard, plugins list, a native `post` edit screen)
     * never receive it.
     */
    public function enqueueMetaboxFieldScripts(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (! $screen || ! in_array($screen->post_type, self::POST_TYPES, true)) {
            return;
        }

        wp_enqueue_script(
            'josworld-metabox-fields',
            plugins_url('assets/js/metabox-fields.js', dirname(__DIR__, 2) . '/josworld-core.php'),
            ['jquery', 'jquery-ui-sortable'],
            null,
            true,
        );
    }
}
