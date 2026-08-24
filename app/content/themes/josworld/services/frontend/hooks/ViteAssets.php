<?php

declare(strict_types=1);

namespace josworld\services\frontend\hooks;

use NTDST_Theme;

/**
 * Vite JS pipeline: dev-server fork in WP_DEBUG, manifest-driven enqueue
 * in production, and the inline `window.josworldConfig` payload.
 *
 * JAVASCRIPT ONLY. This theme is a YOOtheme child: all styling lives in
 * `less/theme.josworld.less`, compiled by the builder. Do not reintroduce
 * a CSS entry here — two style systems in one theme is the cascade
 * conflict this split exists to avoid.
 *
 * Degrades without a build: WP_DEBUG with no manifest forks to the Vite
 * dev server; production with no manifest ships without theme JS. Neither
 * fatals.
 */
final class ViteAssets
{
    private const HANDLE = 'josworld-main';

    private NTDST_Theme $theme;

    public function bind(NTDST_Theme $theme): void
    {
        $this->theme = $theme;

        $theme->on('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        if ($this->isViteDev()) {
            $this->enqueueDevServer();
        } else {
            $this->enqueueBuiltAssets();
        }

        // No restNonce: its only reader was the ntdstAPI client, deleted
        // with the 5.0 command dispatcher. A future REST caller depends on
        // wp-api-fetch, which carries the wp_rest nonce itself.
        wp_add_inline_script(self::HANDLE, 'window.josworldConfig = ' . wp_json_encode([
            'debug'     => defined('WP_DEBUG') && WP_DEBUG,
            'strings'   => [
                'saving'  => __('Opslaan...', 'josworld'),
                'saved'   => __('Opgeslagen', 'josworld'),
                'error'   => __('Er is een fout opgetreden', 'josworld'),
                'confirm' => __('Weet je het zeker?', 'josworld'),
            ],
        ]) . ';', 'before');
    }

    private function enqueueDevServer(): void
    {
        // Dummy handle so wp_add_inline_script has something to attach to.
        wp_register_script(self::HANDLE, false);
        wp_enqueue_script(self::HANDLE);

        $this->theme->on('wp_head', static function (): void {
            echo '<script type="module" src="http://localhost:5173/@vite/client"></script>';
            echo '<script type="module" src="http://localhost:5173/main.js"></script>';
        }, 1);
    }

    private function enqueueBuiltAssets(): void
    {
        $manifest = $this->getManifest();
        if (!isset($manifest['main.js'])) {
            return;
        }

        $entry = $manifest['main.js'];

        wp_enqueue_script(
            self::HANDLE,
            JOSWORLD_URI . '/dist/' . $entry['file'],
            [],
            JOSWORLD_VERSION,
            true
        );

        $this->theme->filter('script_loader_tag', static function (string $tag, string $handle): string {
            if ($handle === self::HANDLE) {
                return str_replace(' src', ' type="module" src', $tag);
            }
            return $tag;
        }, 10, 2);
    }

    private function isViteDev(): bool
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return false;
        }

        // In production the manifest exists; its absence in dev means Vite is serving.
        $manifest_path = JOSWORLD_DIR . '/dist/.vite/manifest.json';
        return !file_exists($manifest_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function getManifest(): array
    {
        static $manifest = null;

        if ($manifest === null) {
            $manifest_path = JOSWORLD_DIR . '/dist/.vite/manifest.json';
            $manifest = file_exists($manifest_path)
                ? (json_decode(file_get_contents($manifest_path), true) ?: [])
                : [];
        }

        return $manifest;
    }
}
