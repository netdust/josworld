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
 * CB-4 / PostTypeRegistryConsistencyTest interaction: that test's forward
 * check (`test_no_ntdst_registered_post_type_is_missing_from_the_post_types_
 * constant`) filters candidates via `get_post_types(['public' => true])`.
 * Because `team` is registered with `public => false`, it does NOT appear
 * in that filtered list and the forward check cannot see it — a real gap
 * in the CB-4 net (a non-public josworld model could in principle drift out
 * of `AdminUIService::POST_TYPES` unnoticed by that specific assertion).
 * Per this task's brief that gap is named rather than silently patched by
 * weakening the check's filter to `[]`: `team` is still covered by every
 * OTHER assertion in that file (it is explicitly named in
 * KNOWN_REGISTERED_SLUGS and the plain isRegistered()/POST_TYPES-membership
 * checks), so the net loses only its `public=>true`-filtered forward leg
 * for this one model, not registry coverage entirely. Closing that filter
 * gap for non-public models in general is left to a follow-up review
 * finding rather than an on-the-fly change to a fixture this task does not
 * own.
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
 * REVERSE RELATIONSHIP WIRING (FR-7) — DIVERGENCE FROM THE HANDED-OVER
 * GROUND TRUTH, discovered during Step 2.5 grounding and reproduced against
 * the real wptests DB, not assumed:
 *
 * NTDST_RelationField DOES self-register on `after_setup_theme`
 * (ntdst-core/services/RelationField.php, verified live) and DOES iterate
 * every registered model's `relation` fields once `team` exists. But its
 * reverse-lookup (`findReferringPosts()`) cannot actually find josworld's
 * data, on TWO independent axes, both structural, both in ntdst-core (which
 * this task may not modify — shared with other sites):
 *
 *  1. PREFIX: `findReferringPosts()` queries `wp_postmeta` with
 *     `meta_key = $field_name` using the RAW, unprefixed field name
 *     ('team'). CaseService registers with `meta_prefix => '_jw_'`
 *     (FR-12/SC-5 — mandatory), so the value is actually stored under
 *     `_jw_team`. The framework's query structurally never matches.
 *  2. FORMAT: the framework's own docblock claims "Data.php stores
 *     relation values as JSON arrays ([6, 7]), not PHP serialized" and
 *     its SQL `LIKE` patterns are JSON-bracket-shaped. Verified against
 *     THIS project's actual `Data.php::updateMetaValue()` (a plain
 *     `update_post_meta()` call, no `json_encode` anywhere on the write
 *     path): the persisted value is standard PHP-serialized WP meta
 *     (`a:1:{i:0;i:4;}`), confirmed by a live round-trip during this
 *     task. The framework's LIKE patterns never match serialized data
 *     either, independently of (1).
 *
 * No sibling site (`rossi`, `sc1-stackwp`, `vad-vormingen`, `bavi`, `willy`,
 * `edushare`, `yves`, `vad-website` — all checked) has ever combined
 * `meta_prefix` with a `relation` field, so this is genuinely unexercised
 * framework territory josworld is first into, not a mistake on this
 * project's side.
 *
 * FIX, scoped to this file only (no ntdst-core edit): `add_meta_boxes` is
 * hooked here at priority 21 — one AFTER NTDST_RelationField's own
 * priority-20 registration — and RE-REGISTERS THE SAME metabox id
 * (`ntdst_reverse_case_team`) on the `team` screen with a josworld-owned
 * render callback that queries the ACTUAL stored shape (prefixed key,
 * `maybe_unserialize`). WordPress's `add_meta_box()` is keyed by id+screen
 * in the global `$wp_meta_boxes` array, so the later registration silently
 * replaces the framework's (which would otherwise always render "not
 * featured in any cases yet", the exact "mis-wired relation silently lists
 * nothing" failure mode this task warns about — proven, not hypothesised:
 * this is what the RED run of TeamServiceRegistrationTest showed before
 * this fix landed). This is the smallest correct fix that does not touch
 * shared framework code; the alternative (dropping `meta_prefix` on `case`)
 * would violate FR-12/SC-5, and JSON-encoding relation values ourselves
 * would diverge from every other `array`/`json` field's storage format in
 * this same model for no benefit (the read path already round-trips PHP-
 * serialized arrays correctly via `maybe_unserialize`).
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

        // Compatibility fix for NTDST_RelationField's reverse-metabox
        // lookup — see the class docblock's "REVERSE RELATIONSHIP WIRING"
        // section for why this is needed and why it is scoped to this file
        // rather than ntdst-core. Priority 21: one after RelationField's own
        // priority-20 `add_meta_boxes` registration, so this replaces it.
        add_action('add_meta_boxes', [$this, 'registerCorrectedReverseCaseMetabox'], 21);
    }

    /**
     * Replace NTDST_RelationField's reverse-relationship metabox on the
     * `team` edit screen with a josworld-aware version that queries the
     * ACTUAL stored shape (meta_prefix'd key, PHP-serialized value) rather
     * than the framework's unprefixed/JSON-shaped assumption. Same metabox
     * id as the framework registers (`ntdst_reverse_case_team`), so this is
     * a REPLACEMENT, not a duplicate — WordPress's add_meta_box() is keyed
     * by id+screen.
     */
    public function registerCorrectedReverseCaseMetabox(): void
    {
        if (! post_type_exists('case') || ! ntdst_data()->isRegistered('case')) {
            return;
        }

        add_meta_box(
            'ntdst_reverse_case_team',
            __('Cases', JOSWORLD_CORE_TEXTDOMAIN),
            [$this, 'renderReverseCaseMetabox'],
            'team',
            'side',
            'default',
        );
    }

    /**
     * Render the cases referring to the team member being edited.
     *
     * Mirrors NTDST_RelationField::renderReverseRelationshipMetabox()'s
     * markup and empty-state shape (kept consistent with the rest of the
     * admin), but queries `_jw_team` (the ACTUAL prefixed meta key
     * CaseService writes) and decodes with `maybe_unserialize()` (the
     * ACTUAL storage format), instead of the framework's unprefixed/
     * JSON-shaped query which never matches this project's data.
     */
    public function renderReverseCaseMetabox(\WP_Post $post): void
    {
        $referring = $this->findCasesReferringTo($post->ID);

        if (empty($referring)) {
            echo '<p class="ntdst-reverse-relations-empty" '
                . 'style="padding: 12px; color: #646970; font-style: italic; margin: 0;">'
                . esc_html__('Niet gekoppeld aan een case.', JOSWORLD_CORE_TEXTDOMAIN)
                . '</p>';
            return;
        }

        echo '<ul class="ntdst-reverse-relations-list" style="margin: 0; padding: 0;">';
        foreach ($referring as $case) {
            $editUrl = get_edit_post_link($case->ID);
            echo '<li style="margin: 0; padding: 8px 12px; border-bottom: 1px solid #f0f0f1;">';
            echo '<a href="' . esc_url((string) $editUrl) . '">' . esc_html($case->post_title) . '</a>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Find every published `case` whose `_jw_team` relation includes the
     * given team member id.
     *
     * Deliberately re-queries via get_post_meta() rather than raw SQL
     * pattern-matching (the framework's approach): the candidate set is
     * every published case, which is small by design (spec: 7 cases exist
     * today, SC-2), so a straight meta scan is simpler and correctness-safe
     * against the actual serialized storage format, without re-deriving
     * ntdst-core's LIKE-pattern narrowing for a data shape it does not
     * apply to.
     *
     * @return list<\WP_Post>
     */
    private function findCasesReferringTo(int $teamMemberId): array
    {
        // An admin-screen metabox render, not a hot path, and the candidate
        // set is every published `case` (spec: 7 exist today, SC-2) —
        // narrowing by meta_key existence is cheap at this scale; see the
        // docblock above for why a value-level LIKE scan (the framework's
        // approach) is deliberately not reproduced here.
        $teamMetaKey = JOSWORLD_CORE_META_PREFIX . 'team';

        $cases = get_posts([
            'post_type' => 'case',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_key' => $teamMetaKey, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);

        $matches = [];
        foreach ($cases as $case) {
            $raw = get_post_meta($case->ID, $teamMetaKey, true);
            $ids = is_array($raw) ? $raw : [];
            if (in_array($teamMemberId, array_map('intval', $ids), true)) {
                $matches[] = $case;
            }
        }

        return $matches;
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
