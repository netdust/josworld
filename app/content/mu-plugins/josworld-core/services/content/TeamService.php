<?php

/**
 * Team Service — registers the `team` post type (T06).
 *
 * SHAPE: mirrors CaseService (T05) — a plain class, constructor takes
 * NTDST_Data_Manager, private init() delegating to registerDataModel(),
 * getFields()/getFieldGroups() as private helpers passed into ONE
 * ntdst_data()->register() call.
 *
 * FIELD MAP — FR-2 verbatim: name (the post title), role, email, phone,
 * photo. "Photo" has no dedicated single-image field type in this framework
 * (confirmed: MetaboxGenerator.php's field-type switch offers `relation`
 * and `gallery` — a multi-image picker — but no single `image`/`file`/
 * `media` case). The idiomatic single-image slot in this framework is the
 * post thumbnail (WP's native featured image, the same mechanism `case`
 * already uses via `supports => ['thumbnail']`), so `photo` is expressed as
 * `supports => ['title', 'thumbnail']` rather than inventing a bespoke meta
 * field for something the framework already has a first-class primitive
 * for.
 *
 * NO PUBLIC SINGLE VIEW (FR-2 / spec assumption: "team gets no public
 * single page; the design lists members on /team/ and embeds them in
 * cases, never linking to a member's own page"). Deliberate args, matched
 * to that requirement one-by-one rather than a blanket `public => false`
 * (which would also drop the admin UI and the REST/search reach an editor
 * still needs):
 *   - `publicly_queryable => false` — no single-post URL resolves on the
 *     front end. This is the one flag that actually encodes "no public
 *     single view"; the rest below follow from it or are independent admin
 *     needs.
 *   - `has_archive => true` — `/team/` IS a page in the design (P2:
 *     "shown on /team/"), so the archive listing stays reachable; only the
 *     per-member single view is withheld.
 *   - `rewrite => false` — no single-post rewrite rule to generate, since
 *     no single view exists to link to.
 *   - `exclude_from_search => true` — a member has no page of its own to
 *     surface in site search.
 *   - `show_ui => true` / `show_in_menu => true` — the editor still needs
 *     the full wp-admin list/edit screens (P2: create/edit a member).
 *   - `show_in_rest => true` — kept for the same reason `case` keeps it:
 *     future-proofing for block-editor/REST tooling on an editor-facing
 *     screen, not a public API surface (REST reads are gated by the same
 *     `publicly_queryable`/capability posture, not opened separately here).
 *
 * `public => false` is set (not left at its `false` default implicitly)
 * BECAUSE it is the WordPress convenience flag that seeds several of the
 * booleans above (`publicly_queryable`, `show_ui`, `exclude_from_search`) —
 * naming it `false` and then overriding `show_ui`/`has_archive` explicitly
 * makes the divergence from "fully public" legible in the config array
 * itself, rather than relying on a reader to know WP's implicit defaults.
 *
 * CB-4 / PostTypeRegistryConsistencyTest interaction — CLOSED (CB-B-4,
 * follow-up review finding): the forward check
 * (`test_no_ntdst_registered_post_type_is_missing_from_the_post_types_
 * constant`) originally filtered candidates via
 * `get_post_types(['public' => true])`. Because `team` is registered with
 * `public => false`, it did not appear in that filtered list and the
 * forward check could not see it — a real gap in the CB-4 net (a non-public
 * josworld model could in principle drift out of `AdminUIService::
 * POST_TYPES` unnoticed by that specific assertion; the other per-model
 * assertions in that file still caught it, so this was a gap, not a hole).
 * The forward check now iterates ALL registered post types and narrows by
 * the `_jw_` meta prefix instead — the boundary already established in
 * commit 2287cc6 and what actually makes a model josworld's, independent of
 * its public-facing posture. Proven with a mutation test: typoing `team`
 * out of `POST_TYPES` left the OLD forward check green (only the other
 * per-model assertions caught it) and now fails the FIXED one directly.
 *
 * i18n (FR-11): every editor-facing label goes through __() against
 * JOSWORLD_CORE_TEXTDOMAIN. Dutch labels are correct — editor-facing Dutch
 * UI per FR-1. The `team` relation field's `reverse_label` — the string the
 * framework's NTDST_RelationField shows on a member's edit screen when it
 * did not have one before this task — is also fixed here on CaseService's
 * `team` field (see that file's diff in this same task): the framework
 * default ("Featured in %ss") is a hardcoded English string, which FR-11
 * forbids in this otherwise-Dutch admin.
 *
 * META PREFIX (FR-12/SC-5): every meta key prefixed `_jw_` via
 * `meta_prefix`, same as CaseService.
 *
 * REVERSE RELATIONSHIP (FR-7): the metabox on a team member's edit screen
 * listing the cases referring to it is entirely framework-provided —
 * NTDST_RelationField self-registers on `after_setup_theme` and iterates
 * every registered model's `relation` fields once `team` exists here.
 * Registering `team` under this exact slug, with CaseService's `team` field
 * already naming `post_type => 'team'`, is the whole of the wiring; nothing
 * in this file calls RelationField directly.
 *
 * This used to need a josworld-side workaround: building this task
 * surfaced two real defects in NTDST_RelationField's reverse-lookup (it
 * couldn't find a meta_prefix'd or PHP-serialized relation value — this
 * project's `case` model is both). That was fixed upstream in ntdst-core
 * (commit 95b7e06) rather than left as a per-site patch, since the defects
 * were structural, not josworld-specific. See
 * tests/Integration/RelationFieldReverseLookupTest.php for the regression
 * proof and the full history.
 *
 * A service file by nature both declares a symbol (the class below) and
 * executes a side effect (the ABSPATH guard) — exempt from the PSR-1
 * side-effects rule, same posture as CaseService.php.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\content;

use NTDST_Data_Manager;

defined('ABSPATH') || exit;

final class TeamService
{
    public function __construct(private readonly NTDST_Data_Manager $dataManager)
    {
        $this->init();
    }

    private function init(): void
    {
        $this->registerDataModel();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getFields(): array
    {
        return [
            'role' => [
                'type' => 'text',
                'label' => __('Functie', JOSWORLD_CORE_TEXTDOMAIN),
                'required' => true,
            ],
            'email' => [
                'type' => 'email',
                'label' => __('E-mailadres', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'phone' => [
                'type' => 'text',
                'label' => __('Telefoonnummer', JOSWORLD_CORE_TEXTDOMAIN),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getFieldGroups(): array
    {
        return [
            'details' => [
                'title' => __('Teamlid-gegevens', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['role', 'email', 'phone'],
                'context' => 'normal',
                'priority' => 'high',
            ],
        ];
    }

    private function registerDataModel(): void
    {
        $this->dataManager->register('team', [
            'label' => __('Teamleden', JOSWORLD_CORE_TEXTDOMAIN),
            'labels' => [
                'name' => __('Teamleden', JOSWORLD_CORE_TEXTDOMAIN),
                'singular_name' => __('Teamlid', JOSWORLD_CORE_TEXTDOMAIN),
                'add_new' => __('Nieuw teamlid', JOSWORLD_CORE_TEXTDOMAIN),
                'add_new_item' => __('Nieuw teamlid toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                'edit_item' => __('Teamlid bewerken', JOSWORLD_CORE_TEXTDOMAIN),
                'view_item' => __('Teamlid bekijken', JOSWORLD_CORE_TEXTDOMAIN),
                'search_items' => __('Teamleden zoeken', JOSWORLD_CORE_TEXTDOMAIN),
                'not_found' => __('Geen teamleden gevonden', JOSWORLD_CORE_TEXTDOMAIN),
                'not_found_in_trash' => __('Geen teamleden gevonden in prullenbak', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'public' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => false,
            'has_archive' => true,
            'hierarchical' => false,
            'supports' => ['title', 'thumbnail'],
            'menu_icon' => 'dashicons-groups',
            'rewrite' => false,
            'show_in_rest' => true,
            'rest_base' => 'team',
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'fields' => $this->getFields(),
            'meta_prefix' => JOSWORLD_CORE_META_PREFIX,
            'use_tabs' => true,
            'tabs_context' => 'normal',
            'tabs_priority' => 'high',
            'metabox_title' => __('Teamlid-gegevens', JOSWORLD_CORE_TEXTDOMAIN),
            'field_groups' => $this->getFieldGroups(),
        ]);
    }
}
