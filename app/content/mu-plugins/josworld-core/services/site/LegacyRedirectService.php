<?php

/**
 * LegacyRedirectService — the old josworld.org addresses, permanently redirected to their place on jos.agency.
 *
 * Only on the old domain's host names: when josworld.org is served by this site, every request is run through
 * the rules of the redirect list (tab Regels, in its priority order) at `init`, before WordPress routes it. The
 * destinations are fixed addresses on jos.agency, from the generated table or a pattern; nothing from the request
 * is ever put into the Location header. An address nothing matches is left to WordPress: a real 404.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\site;

defined('ABSPATH') || exit;

final class LegacyRedirectService
{
    private const HOSTS = ['josworld.org', 'www.josworld.org'];
    private const HOME = 'https://jos.agency/';
    private const WORK = 'https://jos.agency/ons-werk/';
    private const BLOG = 'https://jos.agency/blog-nieuws/';

    /** Pattern rules after the exact list, in the list's priority order (50-100). */
    private const CATCH_ALLS = [
        '#^/(?:work|projecten)(?:/.*)?$#' => self::WORK,
        '#^/blog(?:/.*)?$#' => self::BLOG,
        '#^/[12][0-9]{3}(?:/[0-9]{1,2})?(?:/.*)?$#' => self::BLOG,
        '#^/blog-[^/]*(?:/.*)?$#' => self::BLOG,
        '#^/(?:tag|author)(?:/.*)?$#' => self::BLOG,
        '#^/category/geen-categorie(?:/.*)?$#' => self::BLOG,
        '#^/category(?:/.*)?$#' => self::WORK,
    ];

    /** @var array{exact: array<string, string>, ids: array<int, string>} */
    private array $table;

    /** @param array{exact: array<string, string>, ids: array<int, string>}|null $table the generated list unless given */
    public function __construct(?array $table = null)
    {
        $this->table = $table ?? require dirname(__DIR__, 2) . '/config/legacy-redirects.php';

        if (PHP_SAPI !== 'cli') {
            add_action('init', [$this, 'handle'], 0);
        }
    }

    public function handle(): void
    {
        // The request is only compared and parsed against fixed tables, never printed.
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $host = wp_unslash((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $uri = wp_unslash((string) ($_SERVER['REQUEST_URI'] ?? '/'));
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (! $this->handlesHost($host)) {
            return;
        }

        $target = $this->resolve($uri);
        if ($target !== null) {
            header('Location: ' . $target, true, 301);
            exit;
        }
    }

    public function handlesHost(string $host): bool
    {
        return in_array(strtolower((string) preg_replace('/:\d+$/', '', $host)), self::HOSTS, true);
    }

    public function resolve(string $requestUri): ?string
    {
        $path = rawurldecode((string) parse_url($requestUri, PHP_URL_PATH));
        parse_str((string) parse_url($requestUri, PHP_URL_QUERY), $query);

        if (preg_match('#^/(?:fr|en)(?:/.*)?$#i', $path) === 1) {
            return self::HOME;
        }
        if (in_array(strtolower((string) ($query['lang'] ?? '')), ['fr', 'en'], true)) {
            return self::HOME;
        }

        $id = $query['p'] ?? $query['page_id'] ?? null;
        if ($id !== null) {
            return $this->table['ids'][(int) $id] ?? null;
        }

        $key = strtolower(rtrim($path, '/'));
        if (isset($this->table['exact'][$key])) {
            return $this->table['exact'][$key];
        }

        foreach (self::CATCH_ALLS as $pattern => $target) {
            if (preg_match($pattern, $key) === 1) {
                return $target;
            }
        }

        return null;
    }
}
