<?php

/**
 * Vacature Service — registers the `vacature` post type (T07).
 *
 * SHAPE: mirrors CaseService/TeamService — a plain class, constructor takes
 * NTDST_Data_Manager, private init() delegating to registerDataModel(),
 * getFields()/getFieldGroups() as private helpers passed into ONE
 * ntdst_data()->register() call.
 *
 * FIELD MAP — FR-3/FR-9 verbatim: title (the post title, native), body (the
 * post content, native via `supports => ['editor']` — the design shows a
 * vacancy as a title + a block of copy, the same shape `case` already uses
 * for its own body-like fields, so no bespoke `body` meta field is invented
 * for something WP's own `post_content` column already models), and an
 * employment type constrained to a closed list ("Full-time"/"Freelance" per
 * the design).
 *
 * THE CLOSED LIST IS ENFORCED HERE, NOT BY THE FRAMEWORK (the crux of this
 * task): `select` in MetaboxGenerator.php only RENDERS a `<select>` from the
 * `options` map (MetaboxGenerator.php:686-697) — it does not reject a value
 * outside that map on save. A value posted directly, set via WP-CLI, or
 * written through the Data API bypasses the dropdown entirely and would
 * persist unless this project enforces the closed list itself. Data.php DOES
 * run a per-field `validate` callback on write (Data.php:105-117 collects
 * it, Data.php:422-426 calls it and collects a WP_Error unless it returns
 * `true` exactly) — confirmed by reading the framework, not assumed. So
 * `employment_type` carries a `validate` closure asserting membership in
 * EMPLOYMENT_TYPES; `create()`/`update()` return a WP_Error and NEVER call
 * wp_insert_post()/wp_update_post() when it fails (Data.php:452-455,
 * 508-511) — "rejected" concretely means: no WP_Post is created at all, no
 * meta row is ever written, the caller gets a WP_Error back. Verified both
 * ways per the task brief: a value FROM the list must still save and
 * re-read correctly (VacatureToolRegistrationTest::
 * test_a_valid_employment_type_saves_and_rereads_correctly) — a denial test
 * that also rejects good input would be worse than none.
 *
 * ARCHIVE (FR-3): `has_archive => true` — the vacancy LIST is a designed
 * surface (spec P3: "renders a list that has a designed 'no vacancies'
 * state"). An empty archive query is a normal, non-erroring WP_Query result
 * (0 posts, not an error) — no special handling is needed here for that;
 * rendering the empty state itself is builder-phase work, out of scope for
 * this task.
 *
 * META PREFIX (FR-12/SC-5): every meta key prefixed `_jw_` via
 * `meta_prefix`, same as CaseService/TeamService.
 *
 * i18n (FR-11): every editor-facing label goes through __() against
 * JOSWORLD_CORE_TEXTDOMAIN. Dutch labels are correct per FR-1.
 *
 * A service file by nature both declares a symbol (the class below) and
 * executes a side effect (the ABSPATH guard) — exempt from the PSR-1
 * side-effects rule, same posture as CaseService.php/TeamService.php.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\content;

use NTDST_Data_Manager;

defined('ABSPATH') || exit;

final class VacatureService
{
    /**
     * The closed employment-type list (design: "Full-time" and "Freelance").
     * A rule THIS project chose (FR-9) — not a framework primitive — so it
     * is enforced by a `validate` callback below, not merely offered as
     * `select` options.
     *
     * @var array<string, string>
     */
    // Key === label on purpose. The stored value is what the front end
    // RENDERS: the Jobs & stages archive binds this meta straight into the
    // vacancy row, and YOOtheme's binding exposes the stored value, not the
    // option label — so a `full_time` key printed literally as "full_time".
    // Making the key the label fixes that with no computed source field and
    // no new code path. The list stays closed; `validate` still enforces it.
    private const EMPLOYMENT_TYPES = [
        'Voltijds' => 'Voltijds',
        'Freelance' => 'Freelance',
    ];

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
            'employment_type' => [
                'type' => 'select',
                'label' => __('Type tewerkstelling', JOSWORLD_CORE_TEXTDOMAIN),
                'options' => self::EMPLOYMENT_TYPES,
                'required' => true,
                // The enforcement half of the closed list — see class
                // docblock. Must return exactly `true` to pass (Data.php:
                // 424); any other return becomes the WP_Error message.
                'validate' => static function (mixed $value) {
                    if (! array_key_exists($value, self::EMPLOYMENT_TYPES)) {
                        return sprintf(
                            /* translators: %s: the submitted (invalid) employment type value */
                            __('Ongeldig type tewerkstelling: %s', JOSWORLD_CORE_TEXTDOMAIN),
                            is_scalar($value) ? (string) $value : gettype($value),
                        );
                    }

                    return true;
                },
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
                'title' => __('Vacature-gegevens', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['employment_type'],
                'context' => 'normal',
                'priority' => 'high',
            ],
        ];
    }

    private function registerDataModel(): void
    {
        $this->dataManager->register('vacature', [
            'label' => __('Vacatures', JOSWORLD_CORE_TEXTDOMAIN),
            'labels' => [
                'name' => __('Vacatures', JOSWORLD_CORE_TEXTDOMAIN),
                'singular_name' => __('Vacature', JOSWORLD_CORE_TEXTDOMAIN),
                'add_new' => __('Nieuwe vacature', JOSWORLD_CORE_TEXTDOMAIN),
                'add_new_item' => __('Nieuwe vacature toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                'edit_item' => __('Vacature bewerken', JOSWORLD_CORE_TEXTDOMAIN),
                'view_item' => __('Vacature bekijken', JOSWORLD_CORE_TEXTDOMAIN),
                'search_items' => __('Vacatures zoeken', JOSWORLD_CORE_TEXTDOMAIN),
                'not_found' => __('Geen vacatures gevonden', JOSWORLD_CORE_TEXTDOMAIN),
                'not_found_in_trash' => __('Geen vacatures gevonden in prullenbak', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'has_archive' => true,
            'hierarchical' => false,
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-businessman',
            // `jobs-stages`, not `vacatures`: the archive IS the designed
            // "Jobs & stages" page (frame 625:12258), the nav and footer both
            // link to /jobs-stages/, and page 21 is an empty slug-holder the
            // archive shadows — the same arrangement `case` has with page 15
            // (`ons-werk`) and `team` with page 16. `rest_base` deliberately
            // stays `vacatures`: it is an API name, not a front-end URL, and
            // renaming it would break any consumer for no benefit.
            'rewrite' => ['slug' => 'jobs-stages'],
            'show_in_rest' => true,
            'rest_base' => 'vacatures',
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'fields' => $this->getFields(),
            'meta_prefix' => JOSWORLD_CORE_META_PREFIX,
            'use_tabs' => true,
            'tabs_context' => 'normal',
            'tabs_priority' => 'high',
            'metabox_title' => __('Vacature-gegevens', JOSWORLD_CORE_TEXTDOMAIN),
            'field_groups' => $this->getFieldGroups(),
        ]);
    }
}
