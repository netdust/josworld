<?php

/**
 * Case Service — registers the `case` post type (T05).
 *
 * The site's primary sales asset (spec P1) and the largest template in the
 * Figma file: 7 cases already designed, 42 `Case tile` instances across the
 * file. This is the FIRST content model josworld-core registers; T06-T08
 * (team, vacature+tool, the `dienst` taxonomy on `case`) follow the same
 * shape.
 *
 * SHAPE: mirrors DAAN's DiscographyService
 * (~/Sites/daan/web/app/mu-plugins/daan-core/services/musician/DiscographyService.php) —
 * a plain class, constructor takes NTDST_Data_Manager, does its work via a
 * private init() delegating to registerDataModel(), with getFields()/
 * getFieldGroups() as private helpers passed into ONE ntdst_data()->register()
 * call. It is NOT a full port: DAAN's service also owns admin columns/filters/
 * schema.org output that belong to later josworld tasks (T11 for admin
 * columns), so this file registers the model only.
 *
 * FIELD MAP — FR-6 verbatim: client name, tagline, "Wat we hebben gedaan",
 * doelgroep, a REPEATING impact set (figure + description), uitdaging
 * (title + body), resultaat (title + body), OPTIONAL takeaways (title + body,
 * repeating — the design shows "Geen take-aways momenteel" as the empty
 * state), a testimonial (quote/name/role), and OPTIONAL interview blocks
 * (repeating). FR-7: a `relation` field to `team` (top-level, not nested in
 * the impact repeater — MetaboxGenerator's repeater sub_fields support only
 * text/textarea/select/number/date/url, confirmed at
 * ntdst-core/api/MetaboxGenerator.php:1071-1074 — a nested `relation` type
 * does not exist, and the design shows team credits at the case level anyway,
 * not per impact row).
 *
 * "title + body" pairs (uitdaging, resultaat) have no dedicated `group`/
 * `object` field type in this framework (confirmed: zero hits for
 * `'type' => 'group'` across ntdst-core/api/*.php), so each pair is two flat
 * top-level fields (`..._title` / `..._body`) rather than an invented nested
 * shape. takeaways and interview are each modelled as ONE-FIELD-PER-ENTRY
 * repeaters (title + body sub_fields) because both are documented as
 * "blocks" (plural, optional) in the spec and T11's field_groups task treats
 * them as a single hideable unit each — a repeater is the only field type
 * that is both multi-row and (via 0 min_rows) genuinely absent when unused.
 *
 * ABSENT-NOT-EMPTY-STRINGED (spec acceptance criterion): NTDST_Data_Model's
 * extractMetaData() only prefixes+writes keys actually present in the data
 * array passed to create()/update() (Data.php:1401-1415) — a key never
 * submitted is never written to wp_postmeta at all. A case saved with no
 * takeaways/interview therefore has NO `_jw_takeaways` / `_jw_interview` row
 * in the database; formatMeta() only fills in a per-type default (`[]` for a
 * repeater) when READING for display, which is a display-time concern, not a
 * storage one. This service does not need special-case handling for that —
 * it falls out of the ORM contract as long as CaseService never forces those
 * keys into the write payload, which it does not (it declares the schema,
 * it does not call create()/update() itself).
 *
 * META PREFIX (FR-12 / SC-5): every meta key this model writes is prefixed
 * `_jw_` via `meta_prefix` — a first-class register() arg (Data.php:52,71,
 * 1541) — rather than baked into each field key by hand.
 *
 * i18n (FR-11): every editor-facing label goes through __()/_x() against the
 * JOSWORLD_CORE_TEXTDOMAIN constant. Labels are Dutch (correct — this is
 * editor-facing Dutch UI per FR-1), never hardcoded outside a translation
 * call.
 *
 * A service file by nature both declares a symbol (the class below) and
 * executes a side effect (the ABSPATH guard) — exempt from the PSR-1
 * side-effects rule, same posture as AdminUIService.php.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\content;

use NTDST_Data_Manager;

defined('ABSPATH') || exit;

final class CaseService
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
            // CLIENT / INTRO
            'client' => [
                'type' => 'text',
                'label' => __('Klant', JOSWORLD_CORE_TEXTDOMAIN),
                'required' => true,
            ],
            'tagline' => [
                'type' => 'text',
                'label' => __('Tagline', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'wat_we_hebben_gedaan' => [
                'type' => 'textarea',
                'label' => __('Wat we hebben gedaan', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'doelgroep' => [
                'type' => 'textarea',
                'label' => __('Doelgroep', JOSWORLD_CORE_TEXTDOMAIN),
            ],

            // IMPACT — repeating figure + description (e.g. "+7 mio")
            'impact' => [
                'type' => 'repeater',
                'label' => __('Impact', JOSWORLD_CORE_TEXTDOMAIN),
                'button_text' => __('Impactcijfer toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                'sub_fields' => [
                    'figure' => [
                        'type' => 'text',
                        'label' => __('Cijfer', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                    'description' => [
                        'type' => 'text',
                        'label' => __('Omschrijving', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                ],
            ],

            // UITDAGING (title + body — no group/object field type exists,
            // confirmed against ntdst-core/api/MetaboxGenerator.php)
            'uitdaging_title' => [
                'type' => 'text',
                'label' => __('Uitdaging — titel', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'uitdaging_body' => [
                'type' => 'textarea',
                'label' => __('Uitdaging — tekst', JOSWORLD_CORE_TEXTDOMAIN),
            ],

            // RESULTAAT (title + body)
            'resultaat_title' => [
                'type' => 'text',
                'label' => __('Resultaat — titel', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'resultaat_body' => [
                'type' => 'textarea',
                'label' => __('Resultaat — tekst', JOSWORLD_CORE_TEXTDOMAIN),
            ],

            // TAKEAWAYS — OPTIONAL, repeating (title + body). Absent from
            // storage entirely when the editor adds none (see class docblock).
            'takeaways' => [
                'type' => 'repeater',
                'label' => __('Take-aways', JOSWORLD_CORE_TEXTDOMAIN),
                'button_text' => __('Take-away toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                'sub_fields' => [
                    'title' => [
                        'type' => 'text',
                        'label' => __('Titel', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                    'body' => [
                        'type' => 'textarea',
                        'label' => __('Tekst', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                ],
            ],

            // TESTIMONIAL (quote / name / role)
            'testimonial_quote' => [
                'type' => 'textarea',
                'label' => __('Testimonial — quote', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'testimonial_name' => [
                'type' => 'text',
                'label' => __('Testimonial — naam', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'testimonial_role' => [
                'type' => 'text',
                'label' => __('Testimonial — functie', JOSWORLD_CORE_TEXTDOMAIN),
            ],

            // INTERVIEW — OPTIONAL, repeating (title + body) blocks
            'interview' => [
                'type' => 'repeater',
                'label' => __('Interview', JOSWORLD_CORE_TEXTDOMAIN),
                'button_text' => __('Interviewblok toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                'sub_fields' => [
                    'title' => [
                        'type' => 'text',
                        'label' => __('Titel', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                    'body' => [
                        'type' => 'textarea',
                        'label' => __('Tekst', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                ],
            ],

            // TEAM — relation to `team` (FR-7); top-level, not per impact row.
            'team' => [
                'type' => 'relation',
                'label' => __('Team', JOSWORLD_CORE_TEXTDOMAIN),
                'post_type' => 'team',
                'multiple' => true,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getFieldGroups(): array
    {
        return [
            'intro' => [
                'title' => __('Klant & intro', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['client', 'tagline', 'wat_we_hebben_gedaan', 'doelgroep'],
                'context' => 'normal',
                'priority' => 'high',
            ],
            'impact' => [
                'title' => __('Impact', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['impact'],
                'context' => 'normal',
                'priority' => 'high',
            ],
            'narrative' => [
                'title' => __('Uitdaging & resultaat', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['uitdaging_title', 'uitdaging_body', 'resultaat_title', 'resultaat_body'],
                'context' => 'normal',
                'priority' => 'default',
            ],
            'takeaways' => [
                'title' => __('Take-aways', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['takeaways'],
                'context' => 'normal',
                'priority' => 'default',
            ],
            'testimonial' => [
                'title' => __('Testimonial', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['testimonial_quote', 'testimonial_name', 'testimonial_role'],
                'context' => 'normal',
                'priority' => 'default',
            ],
            'interview' => [
                'title' => __('Interview', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['interview'],
                'context' => 'normal',
                'priority' => 'default',
            ],
            'team' => [
                'title' => __('Team', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['team'],
                'context' => 'side',
                'priority' => 'default',
            ],
        ];
    }

    private function registerDataModel(): void
    {
        $this->dataManager->register('case', [
            'label' => __('Cases', JOSWORLD_CORE_TEXTDOMAIN),
            'labels' => [
                'name' => __('Cases', JOSWORLD_CORE_TEXTDOMAIN),
                'singular_name' => __('Case', JOSWORLD_CORE_TEXTDOMAIN),
                'add_new' => __('Nieuwe case', JOSWORLD_CORE_TEXTDOMAIN),
                'add_new_item' => __('Nieuwe case toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                'edit_item' => __('Case bewerken', JOSWORLD_CORE_TEXTDOMAIN),
                'view_item' => __('Case bekijken', JOSWORLD_CORE_TEXTDOMAIN),
                'search_items' => __('Cases zoeken', JOSWORLD_CORE_TEXTDOMAIN),
                'not_found' => __('Geen cases gevonden', JOSWORLD_CORE_TEXTDOMAIN),
                'not_found_in_trash' => __('Geen cases gevonden in prullenbak', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'has_archive' => true,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_icon' => 'dashicons-portfolio',
            'rewrite' => ['slug' => 'ons-werk'],
            'show_in_rest' => true,
            'rest_base' => 'cases',
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'fields' => $this->getFields(),
            'meta_prefix' => JOSWORLD_CORE_META_PREFIX,
            'use_tabs' => true,
            'tabs_context' => 'normal',
            'tabs_priority' => 'high',
            'metabox_title' => __('Case-gegevens', JOSWORLD_CORE_TEXTDOMAIN),
            'field_groups' => $this->getFieldGroups(),
        ]);
    }
}
