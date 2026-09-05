<?php

/**
 * Tool Service — registers the `tool` post type.
 *
 * The model is its card: `subtitle` and the downloadable `file`, the
 * fields another page needs to show a tool. The rest is built in the
 * YOOtheme builder.
 *
 * The downloadable file is the framework's native `file` type: the
 * media picker, one attachment id stored and re-read as an int. A
 * standard WP media attachment, no bespoke upload handler for this
 * project to build and secure.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\content;

use NTDST_Data_Manager;

defined("ABSPATH") || exit();

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
            "subtitle" => [
                "type" => "text",
                "label" => __("Subtitel", JOSWORLD_CORE_TEXTDOMAIN),
            ],

            // DOWNLOADABLE FILE (see class docblock). Optional: no
            // `required`, so a tool with no file still saves.
            "file" => [
                "type" => "file",
                "label" => __("Bestand", JOSWORLD_CORE_TEXTDOMAIN),
            ],
        ];
    }

    private function registerDataModel(): void
    {
        $this->dataManager->register("tool", [
            "label" => __("Tools", JOSWORLD_CORE_TEXTDOMAIN),
            "labels" => [
                "name" => __("Tools", JOSWORLD_CORE_TEXTDOMAIN),
                "singular_name" => __("Tool", JOSWORLD_CORE_TEXTDOMAIN),
                "add_new" => __("Nieuwe tool", JOSWORLD_CORE_TEXTDOMAIN),
                "add_new_item" => __("Nieuwe tool toevoegen", JOSWORLD_CORE_TEXTDOMAIN),
                "edit_item" => __("Tool bewerken", JOSWORLD_CORE_TEXTDOMAIN),
                "view_item" => __("Tool bekijken", JOSWORLD_CORE_TEXTDOMAIN),
                "search_items" => __("Tools zoeken", JOSWORLD_CORE_TEXTDOMAIN),
                "not_found" => __("Geen tools gevonden", JOSWORLD_CORE_TEXTDOMAIN),
                "not_found_in_trash" => __(
                    "Geen tools gevonden in prullenbak",
                    JOSWORLD_CORE_TEXTDOMAIN,
                ),
            ],
            "public" => true,
            "publicly_queryable" => true,
            "show_ui" => true,
            "show_in_menu" => true,
            "query_var" => true,
            "has_archive" => "gratis-aanbod",   // the listing at the design URL; singles stay under /tools/
            "hierarchical" => false,
            "supports" => ["title", "editor", "thumbnail"],
            "menu_icon" => "dashicons-download",
            "rewrite" => ["slug" => "tools"],
            "show_in_rest" => true,
            "rest_base" => "tools",
            "capability_type" => "post",
            "map_meta_cap" => true,
            "fields" => $this->getFields(),
            "meta_prefix" => JOSWORLD_CORE_META_PREFIX,
            "metabox_title" => __("Tool-gegevens", JOSWORLD_CORE_TEXTDOMAIN),
        ]);
    }
}
