<?php

/**
 * Tool Service — registers the `tool` post type (T07).
 *
 * SHAPE: mirrors CaseService/TeamService/VacatureService — a plain class,
 * constructor takes NTDST_Data_Manager, private init() delegating to
 * registerDataModel(), getFields()/getFieldGroups() as private helpers
 * passed into ONE ntdst_data()->register() call.
 *
 * FIELD MAP — FR-4/FR-8 verbatim: title (native), subtitle, a benefit list,
 * a downloadable media file, and a relation to other tools ("Gratis
 * aanbod" — the design shows a tool page listing other tools).
 *
 * BENEFIT LIST: this framework has no dedicated "list of strings" field
 * type (confirmed against MetaboxGenerator.php's field-type switch —
 * `array`/`json` renders a raw JSON textarea, which is not an editor-facing
 * list control). `repeater` is the project's own established idiom for a
 * list (CaseService's impact/takeaways/interview all use it), so `benefits`
 * is a one-field-per-entry repeater (`benefit` sub_field, text), matching
 * that convention rather than inventing a new list mechanism.
 *
 * DOWNLOADABLE FILE: this framework's field-type switch offers `relation`
 * (post/attachment picker) and `gallery` (a MULTI-image picker whose
 * rendering is image-specific — it calls wp_get_attachment_image_url() and
 * shows alt-text indicators, MetaboxGenerator.php:876-911 — wrong fit for
 * an arbitrary downloadable file such as a PDF). No single-file/media field
 * type exists (confirmed: zero hits for a `file`/`media`/`attachment` case
 * in the field-type switch). `relation` is generic — it stores post IDs via
 * the same relation sanitizer/render path regardless of post type — so a
 * downloadable file is modelled as a `relation` field scoped to
 * `post_type => 'attachment'` with `multiple => false`: one attachment post
 * id, the idiomatic WP shape for "one uploaded file", using the framework's
 * existing generic post-picker rather than a bespoke upload field this
 * project would have to build and secure itself (the spec's own security
 * note: "the tool download is a standard WP media attachment", no upload
 * handler of this project's own). Optional — a tool with no file still
 * saves (task brief; proven by ToolRegistrationTest::
 * test_a_tool_saved_with_no_file_still_saves), because this field carries
 * no `required` flag and the Data API only writes keys actually submitted
 * (Data.php's extractMetaData(), same ABSENT-NOT-EMPTY-STRINGED contract
 * CaseService's optional blocks already rely on).
 *
 * RELATION TO OTHER TOOLS (FR-8): `post_type => 'tool'` is deliberately
 * self-referential — a tool CAN relate to itself at the framework/schema
 * level (nothing in `relation`'s sanitizer/renderer excludes the current
 * post id from its own picker results). This project does not add a
 * same-post exclusion here: the design shows a tool page linking to OTHER
 * tools as a curated editorial choice, not a set the framework must
 * enforce structurally, and no acceptance criterion asks for it — adding
 * self-exclusion logic (and a test proving it) would be scope this task's
 * contract does not request. Named as a deliberate non-decision, not an
 * oversight.
 *
 * META PREFIX (FR-12/SC-5): every meta key prefixed `_jw_` via
 * `meta_prefix`, same as the sibling services.
 *
 * i18n (FR-11): every editor-facing label goes through __() against
 * JOSWORLD_CORE_TEXTDOMAIN. Dutch labels are correct per FR-1.
 *
 * A service file by nature both declares a symbol (the class below) and
 * executes a side effect (the ABSPATH guard) — exempt from the PSR-1
 * side-effects rule, same posture as the sibling content services.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\content;

use NTDST_Data_Manager;

defined('ABSPATH') || exit;

final class ToolService
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
            'subtitle' => [
                'type' => 'text',
                'label' => __('Subtitel', JOSWORLD_CORE_TEXTDOMAIN),
            ],

            // BENEFIT LIST — one-field-per-entry repeater, this project's
            // established list idiom (see class docblock).
            'benefits' => [
                'type' => 'repeater',
                'label' => __('Voordelen', JOSWORLD_CORE_TEXTDOMAIN),
                'button_text' => __('Voordeel toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                'sub_fields' => [
                    'benefit' => [
                        'type' => 'text',
                        'label' => __('Voordeel', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                ],
            ],

            // DOWNLOADABLE FILE — a `relation` scoped to `attachment`,
            // single-select (see class docblock). Optional: no `required`,
            // so a tool with no file still saves.
            'file' => [
                'type' => 'relation',
                'label' => __('Bestand', JOSWORLD_CORE_TEXTDOMAIN),
                'post_type' => 'attachment',
                'multiple' => false,
            ],

            // RELATION TO OTHER TOOLS (FR-8) — self-referential by design
            // (see class docblock).
            'related_tools' => [
                'type' => 'relation',
                'label' => __('Gerelateerde tools', JOSWORLD_CORE_TEXTDOMAIN),
                'post_type' => 'tool',
                'multiple' => true,
                'reverse_label' => __('Gerelateerde tools', JOSWORLD_CORE_TEXTDOMAIN),
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
                'title' => __('Tool-gegevens', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['subtitle', 'benefits', 'file'],
                'context' => 'normal',
                'priority' => 'high',
            ],
            'related' => [
                'title' => __('Gerelateerde tools', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['related_tools'],
                'context' => 'side',
                'priority' => 'default',
            ],
        ];
    }

    private function registerDataModel(): void
    {
        $this->dataManager->register('tool', [
            'label' => __('Tools', JOSWORLD_CORE_TEXTDOMAIN),
            'labels' => [
                'name' => __('Tools', JOSWORLD_CORE_TEXTDOMAIN),
                'singular_name' => __('Tool', JOSWORLD_CORE_TEXTDOMAIN),
                'add_new' => __('Nieuwe tool', JOSWORLD_CORE_TEXTDOMAIN),
                'add_new_item' => __('Nieuwe tool toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                'edit_item' => __('Tool bewerken', JOSWORLD_CORE_TEXTDOMAIN),
                'view_item' => __('Tool bekijken', JOSWORLD_CORE_TEXTDOMAIN),
                'search_items' => __('Tools zoeken', JOSWORLD_CORE_TEXTDOMAIN),
                'not_found' => __('Geen tools gevonden', JOSWORLD_CORE_TEXTDOMAIN),
                'not_found_in_trash' => __('Geen tools gevonden in prullenbak', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'has_archive' => true,
            'hierarchical' => false,
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-download',
            'rewrite' => ['slug' => 'tools'],
            'show_in_rest' => true,
            'rest_base' => 'tools',
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'fields' => $this->getFields(),
            'meta_prefix' => JOSWORLD_CORE_META_PREFIX,
            'use_tabs' => true,
            'tabs_context' => 'normal',
            'tabs_priority' => 'high',
            'metabox_title' => __('Tool-gegevens', JOSWORLD_CORE_TEXTDOMAIN),
            'field_groups' => $this->getFieldGroups(),
        ]);
    }
}
