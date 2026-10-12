<?php

/**
 * Case Service — registers the `case` post type, its `dienst` taxonomy,
 * and the case admin list columns.
 *
 * Meta keys are ALWAYS ENGLISH — a durable storage/API contract the
 * YOOtheme templates read directly. __() labels are ALWAYS DUTCH admin UI.
 * Never conflate the two.
 *
 * The model is its card: the fields a grid or CTA on another page needs
 * to show a case (client + the tile's hover image). The narrative (uitdaging, resultaat, testimonial, team…)
 * is built in the YOOtheme builder now, not stored here.
 *
 * The card's label row is one builder field, `labels`: the `domain` term as a
 * pill link, then the `dienst` terms as plain text.
 *
 * The `dienst` taxonomy is declared WITH the model via register()'s
 * `taxonomies` key — no direct register_taxonomy() call anywhere in
 * josworld-core (it converges on NTDST_Data_Manager::registerTaxonomy()).
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\content;

use NTDST_Data_Manager;

defined('ABSPATH') || exit;

final class CaseService
{
    /** At most one domain, three diensten and one doelgroep per case (client spec). */
    public const LIMITS = ['domain' => 1, 'dienst' => 3, 'doelgroep' => 1];

    /** Editors tick terms from the list; only administrators add or change the list itself. */
    private const PICK_FROM_THE_LIST = [
        'manage_terms' => 'manage_options',
        'edit_terms' => 'manage_options',
        'delete_terms' => 'manage_options',
        'assign_terms' => 'edit_posts',
    ];

    public function __construct(private readonly NTDST_Data_Manager $dataManager)
    {
        $this->init();
    }

    private function init(): void
    {
        $this->registerDataModel();
        $this->registerAdminColumns();
        add_action('after_setup_theme', [$this, 'attachLabelsField'], 20);
        add_action('pre_get_posts', [$this, 'showWholeTermOnOnePage']);
        add_action('set_object_terms', [$this, 'clampTerms'], 10, 5);
        add_filter('request', [$this, 'resolveOnsWerkSlug']);
        // After baseline's shared-slug filter (Polylang), so a domain's slug stays the overview's.
        add_filter('wp_unique_post_slug', [$this, 'keepDomainSlugsFree'], 20, 4);
        add_filter('pre_insert_term', [$this, 'refuseTheSlugOfACase'], 10, 3);
    }

    /**
     * The case's own terms obey the limits whatever wrote them (classic screen, REST, CLI): the first ones win.
     *
     * @param array<int, int|string> $ttIds
     */
    public function clampTerms(int $objectId, mixed $terms, array $ttIds, string $taxonomy, bool $append): void
    {
        $limit = self::LIMITS[$taxonomy] ?? null;
        if ($limit === null || get_post_type($objectId) !== 'case') {
            return;
        }

        $given = array_map('intval', $ttIds);
        // On append WordPress hands over only the NEW terms: the case's earlier ones come first.
        $earlier = $append
            ? array_diff(array_map('intval', wp_get_object_terms($objectId, $taxonomy, ['fields' => 'tt_ids'])), $given)
            : [];
        $ordered = array_values(array_unique([...$earlier, ...$given]));
        if (count($ordered) <= $limit) {
            return;
        }

        $keep = [];
        foreach (array_slice($ordered, 0, $limit) as $ttId) {
            $term = get_term_by('term_taxonomy_id', $ttId, $taxonomy);
            if ($term instanceof \WP_Term) {
                $keep[] = $term->term_id;
            }
        }

        remove_action('set_object_terms', [$this, 'clampTerms'], 10);
        $result = wp_set_object_terms($objectId, $keep, $taxonomy);
        add_action('set_object_terms', [$this, 'clampTerms'], 10, 5);

        if (is_wp_error($result)) {
            wp_trigger_error(
                __METHOD__,
                "Could not keep case {$objectId} within its {$taxonomy} limit: " . $result->get_error_message()
            );
        }
    }

    /**
     * A domain overview and a case share /ons-werk/<slug>: a domain term wins its slug, any other slug is a case.
     *
     * @param array<string, mixed> $vars
     * @return array<string, mixed>
     */
    public function resolveOnsWerkSlug(array $vars): array
    {
        $slug = (string) ($vars['domain'] ?? $vars['case'] ?? '');
        if ($slug === '') {
            return $vars;
        }

        if (get_term_by('slug', $slug, 'domain')) {
            unset($vars['case'], $vars['name'], $vars['post_type']);
            $vars['domain'] = $slug;

            return $vars;
        }

        if (isset($vars['domain'])) {
            unset($vars['domain']);
            $vars = [...$vars, 'post_type' => 'case', 'case' => $slug, 'name' => $slug];
        }

        return $vars;
    }

    /** A case never takes the slug of a domain: that URL belongs to the overview. */
    public function keepDomainSlugsFree(string $slug, int $postId, string $status, string $type): string
    {
        if ($type !== 'case') {
            return $slug;
        }

        $candidate = $slug;
        for ($n = 2; $this->slugIsTaken($candidate, $postId, $candidate !== $slug); $n++) {
            $candidate = $slug . '-' . $n;
        }

        return $candidate;
    }

    /** A domain owns its slug; a suffixed candidate must also be free among the cases (core never saw it). */
    private function slugIsTaken(string $slug, int $postId, bool $checkCases): bool
    {
        if (get_term_by('slug', $slug, 'domain')) {
            return true;
        }

        return $checkCases && $this->caseExists($slug, $postId);
    }

    private function caseExists(string $slug, int $exceptId = 0): bool
    {
        return get_posts([
            'name' => $slug,
            'post_type' => 'case',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'post__not_in' => [$exceptId],
            'numberposts' => 1,
            'fields' => 'ids',
            'suppress_filters' => true,
        ]) !== [];
    }

    /**
     * A domain whose slug is a case's would take that case's URL: the term wins /ons-werk/<slug>.
     *
     * @param array<string, mixed> $args
     */
    public function refuseTheSlugOfACase(mixed $term, string $taxonomy, array $args = []): mixed
    {
        if ($taxonomy !== 'domain' || is_wp_error($term)) {
            return $term;
        }

        $slug = sanitize_title((string) ($args['slug'] ?? $term));
        if ($slug === '' || ! $this->caseExists($slug)) {
            return $term;
        }

        return new \WP_Error(
            'jw_domain_slug_is_a_case',
            sprintf(
                /* translators: %s: the slug */
                __('Er bestaat al een case op /ons-werk/%s/: kies een andere naam.', JOSWORLD_CORE_TEXTDOMAIN),
                $slug
            )
        );
    }

    /** A `domain` / `dienst` archive is Ons werk filtered: the builder template lists the main query, all of it. */
    public function showWholeTermOnOnePage(\WP_Query $query): void
    {
        if ($query->is_main_query() && ! is_admin() && $query->is_tax(['domain', 'dienst'])) {
            $query->set('posts_per_page', -1);
        }
    }

    public function attachLabelsField(): void
    {
        if (! function_exists('YOOtheme\\app')) {
            return;
        }

        $field = [
            'type' => 'String',
            'metadata' => ['label' => __('Labels (domein + diensten)', JOSWORLD_CORE_TEXTDOMAIN)],
            'extensions' => ['call' => __NAMESPACE__ . '\\resolve_case_labels'],
        ];

        // The licensed parent theme is not installed in the gate.
        \YOOtheme\Event::on( // @phpstan-ignore class.notFound
            'source.init',
            static fn ($source) => $source->objectType('Case', ['fields' => ['labels' => $field]]),
            -5
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getFields(): array
    {
        return [
            'client' => [
                'type' => 'text',
                'label' => __('Klant', JOSWORLD_CORE_TEXTDOMAIN),
                'required' => true,
            ],
            'hover_image' => [
                'type' => 'image',
                'label' => __('Hover-afbeelding', JOSWORLD_CORE_TEXTDOMAIN),
                'description' => __('Tweede beeld dat een case-tegel toont bij hover.', JOSWORLD_CORE_TEXTDOMAIN),
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
            'description' => __(
                'Ons werk: campagnes, content en strategie voor overheden, social profit en ondernemers.',
                JOSWORLD_CORE_TEXTDOMAIN
            ),
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
            'metabox_title' => __('Case-gegevens', JOSWORLD_CORE_TEXTDOMAIN),
            'metabox_context' => 'side',
            // `dienst`, case-only, declared with the model — see class
            // docblock (no direct register_taxonomy() call).
            'taxonomies' => [
                'domain' => [
                    'hierarchical' => true,
                    'capabilities' => self::PICK_FROM_THE_LIST,
                    'labels' => [
                        'name' => __('Domeinen', JOSWORLD_CORE_TEXTDOMAIN),
                        'singular_name' => __('Domein', JOSWORLD_CORE_TEXTDOMAIN),
                        'search_items' => __('Domeinen zoeken', JOSWORLD_CORE_TEXTDOMAIN),
                        'all_items' => __('Alle domeinen', JOSWORLD_CORE_TEXTDOMAIN),
                        'edit_item' => __('Domein bewerken', JOSWORLD_CORE_TEXTDOMAIN),
                        'update_item' => __('Domein bijwerken', JOSWORLD_CORE_TEXTDOMAIN),
                        'add_new_item' => __('Nieuw domein toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                        'new_item_name' => __('Nieuwe domeinnaam', JOSWORLD_CORE_TEXTDOMAIN),
                        'menu_name' => __('Domeinen', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                    'show_admin_column' => true,
                    'show_in_rest' => true,
                    'rewrite' => ['slug' => 'ons-werk', 'with_front' => false],
                ],
                'dienst' => [
                    'hierarchical' => true,
                    'capabilities' => self::PICK_FROM_THE_LIST,
                    'labels' => [
                        'name' => __('Diensten', JOSWORLD_CORE_TEXTDOMAIN),
                        'singular_name' => __('Dienst', JOSWORLD_CORE_TEXTDOMAIN),
                        'search_items' => __('Diensten zoeken', JOSWORLD_CORE_TEXTDOMAIN),
                        'all_items' => __('Alle diensten', JOSWORLD_CORE_TEXTDOMAIN),
                        'edit_item' => __('Dienst bewerken', JOSWORLD_CORE_TEXTDOMAIN),
                        'update_item' => __('Dienst bijwerken', JOSWORLD_CORE_TEXTDOMAIN),
                        'add_new_item' => __('Nieuwe dienst toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                        'new_item_name' => __('Nieuwe dienstnaam', JOSWORLD_CORE_TEXTDOMAIN),
                        'menu_name' => __('Diensten', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                    'show_admin_column' => true,
                    'show_in_rest' => true,
                    'rewrite' => ['slug' => 'dienst'],
                ],
                // "Voor publieke sector" or "Voor social profit & ondernemers": one per case, no overview of its own.
                'doelgroep' => [
                    'hierarchical' => true,
                    'capabilities' => self::PICK_FROM_THE_LIST,
                    'labels' => [
                        'name' => __('Doelgroepen', JOSWORLD_CORE_TEXTDOMAIN),
                        'singular_name' => __('Doelgroep', JOSWORLD_CORE_TEXTDOMAIN),
                        'search_items' => __('Doelgroepen zoeken', JOSWORLD_CORE_TEXTDOMAIN),
                        'all_items' => __('Alle doelgroepen', JOSWORLD_CORE_TEXTDOMAIN),
                        'edit_item' => __('Doelgroep bewerken', JOSWORLD_CORE_TEXTDOMAIN),
                        'update_item' => __('Doelgroep bijwerken', JOSWORLD_CORE_TEXTDOMAIN),
                        'add_new_item' => __('Nieuwe doelgroep toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                        'new_item_name' => __('Nieuwe doelgroepnaam', JOSWORLD_CORE_TEXTDOMAIN),
                        'menu_name' => __('Doelgroepen', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                    'public' => true,
                    'publicly_queryable' => false,
                    'query_var' => false,
                    'show_admin_column' => true,
                    'show_in_rest' => true,
                    'rewrite' => false,
                ],
            ],
        ]);
    }

    /**
     * Columns read through the model's getMeta() (status 'any' — the admin
     * list shows drafts too), which resolves the `_jw_` prefix; raw
     * get_post_meta() would silently read the wrong (unprefixed) key. The
     * `jw_` column-key prefix keeps the keys from colliding with a native
     * WP column or another plugin's.
     */
    private function registerAdminColumns(): void
    {
        add_filter('manage_case_posts_columns', [$this, 'addAdminColumns']);
        add_action('manage_case_posts_custom_column', [$this, 'renderAdminColumn'], 10, 2);
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addAdminColumns(array $columns): array
    {
        $newColumns = [];

        foreach ($columns as $key => $label) {
            $newColumns[$key] = $label;

            if ($key === 'title') {
                $newColumns['jw_client'] = __('Klant', JOSWORLD_CORE_TEXTDOMAIN);
                $newColumns['jw_diensten'] = __('Diensten', JOSWORLD_CORE_TEXTDOMAIN);
            }
        }

        return $newColumns;
    }

    public function renderAdminColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'jw_client':
                $client = $this->dataManager->get('case')->getMeta($postId, 'client', '', 'any');
                echo $client !== '' ? esc_html((string) $client) : '<span style="color:#999">—</span>';
                break;

            case 'jw_diensten':
                $terms = get_the_terms($postId, 'dienst');
                if (! is_array($terms) || $terms === []) {
                    echo '<span style="color:#999">—</span>';
                    break;
                }
                echo esc_html(implode(', ', array_map(static fn ($term) => $term->name, $terms)));
                break;
        }
    }
}

/**
 * YOOtheme calls this by name for the case `labels` field: the domain as a
 * pill link, then the diensten as plain text ("A, B & C").
 */
function resolve_case_labels(mixed $post): ?string
{
    $postId = is_object($post) && isset($post->ID) ? (int) $post->ID : 0;
    $domain = $postId > 0 ? get_the_terms($postId, 'domain') : false;
    $diensten = $postId > 0 ? get_the_terms($postId, 'dienst') : false;
    $html = '';

    $domainLink = is_array($domain) && $domain !== [] ? get_term_link($domain[0]) : null;
    if (is_string($domainLink)) {
        $html .= sprintf('<a rel="tag" href="%s">%s</a>', esc_url($domainLink), esc_html($domain[0]->name));
    }

    if (is_array($diensten) && $diensten !== []) {
        $shown = array_slice($diensten, 0, CaseService::LIMITS['dienst']);
        $names = array_map(static fn ($term) => esc_html($term->name), $shown);
        $last = array_pop($names);
        $text = $names === [] ? $last : implode(', ', $names) . ' &amp; ' . $last;
        $html .= '<span class="jw-diensten">' . $text . '</span>';
    }

    return $html !== '' ? $html : null;
}
