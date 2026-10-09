<?php

/**
 * Tool Service — registers the `tool` post type.
 *
 * The model is its card: `subtitle` and the downloadable `file`, the
 * fields another page needs to show a tool. The rest is built in the
 * YOOtheme builder.
 *
 * A tool is a downloadable file OR a link to an external page (`delivery`,
 * default `file` so tools saved before the choice stay as they were); the
 * editor shows only the field of the chosen kind. `targetUrl()` is the one
 * place that turns a tool into where the visitor goes — the download form
 * and the builder's `target` field both ask it.
 *
 * The file is the framework's native `file` type: the media picker, one
 * attachment id stored and re-read as an int. A standard WP media attachment,
 * no bespoke upload handler for this project to build and secure. The link is
 * http(s) only: anything else is no target.
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
        add_action('after_setup_theme', [$this, 'attachTargetField'], 20);
    }

    /** Where the visitor goes for this tool: its file's URL or its link; '' when it has neither. */
    public static function targetUrl(int $toolId): string
    {
        $model = ntdst_data()->get('tool');

        if ($model->getMeta($toolId, 'delivery', 'file') === 'url') {
            return esc_url_raw((string) $model->getMeta($toolId, 'url', ''), ['http', 'https']);
        }

        $fileId = absint($model->getMeta($toolId, 'file', 0));
        $url = $fileId > 0 ? wp_get_attachment_url($fileId) : false;

        return is_string($url) ? $url : '';
    }

    /** The builder binds a button to `target` instead of choosing between file.url and url. */
    public function attachTargetField(): void
    {
        if (! function_exists('YOOtheme\\app')) {
            return;
        }

        $field = [
            'type' => 'String',
            'metadata' => ['label' => __('Doel (bestand of link)', JOSWORLD_CORE_TEXTDOMAIN)],
            'extensions' => ['call' => __NAMESPACE__ . '\\resolve_tool_target'],
        ];

        // The licensed parent theme is not installed in the gate.
        \YOOtheme\Event::on( // @phpstan-ignore class.notFound
            'source.init',
            static fn ($source) => $source->objectType('Tool', ['fields' => ['target' => $field]]),
            -5
        );
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

            "delivery" => [
                "type" => "select",
                "label" => __("Wat is deze tool?", JOSWORLD_CORE_TEXTDOMAIN),
                "options" => [
                    "file" => __("Een bestand om te downloaden", JOSWORLD_CORE_TEXTDOMAIN),
                    "url" => __("Een link naar een pagina", JOSWORLD_CORE_TEXTDOMAIN),
                ],
                "default" => "file",
            ],

            // Optional: no `required`, so a tool with no file or link still saves.
            "file" => [
                "type" => "file",
                "label" => __("Bestand", JOSWORLD_CORE_TEXTDOMAIN),
                "condition" => ["delivery" => "file"],
            ],

            "url" => [
                "type" => "url",
                "label" => __("Link", JOSWORLD_CORE_TEXTDOMAIN),
                "description" => __("Een volledige link, beginnend met https://", JOSWORLD_CORE_TEXTDOMAIN),
                "condition" => ["delivery" => "url"],
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

/** YOOtheme calls this by name for the tool `target` field. */
function resolve_tool_target(mixed $post): ?string
{
    $toolId = is_object($post) && isset($post->ID) ? (int) $post->ID : 0;
    $url = $toolId > 0 ? ToolService::targetUrl($toolId) : '';

    return $url !== '' ? $url : null;
}
