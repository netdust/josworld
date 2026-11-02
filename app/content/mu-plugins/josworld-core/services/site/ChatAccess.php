<?php

declare(strict_types=1);

namespace josworld\services\site;

defined('ABSPATH') || exit;

/**
 * ChatAccess — the chat machine user's limits. The connector's application password reaches
 * all of /wp/v2 the account can, so the `chat` role is the boundary: an exact allow-list,
 * reset on every environment where it drifts, and a key only for an account whose sole role is
 * `chat` (an administrator with `chat` added would otherwise hold an admin key).
 */
// phpcs:disable PSR1.Files.SideEffects
final class ChatAccess
{
    public const ROLE = 'chat';

    public const CAPS = [
        'read',
        'edit_posts',
        'edit_pages',
        'edit_others_posts',
        'edit_others_pages',
        'edit_published_posts',
        'edit_published_pages',
    ];

    public function __construct()
    {
        add_action('init', [$this, 'ensureRole']);
        // After baseline's capability check (priority 10), never instead of it.
        add_filter('wp_is_application_passwords_available_for_user', [$this, 'onlyTheChatUser'], 20, 2);
    }

    public function ensureRole(): void
    {
        $role = get_role(self::ROLE);
        $held = $role === null ? null : array_keys(array_filter($role->capabilities));

        if ($held !== null && $this->sameCaps($held)) {
            return;
        }

        remove_role(self::ROLE);
        add_role(self::ROLE, 'Chat', array_fill_keys(self::CAPS, true));
    }

    public function onlyTheChatUser(bool $available, \WP_User $user): bool
    {
        return $available && array_values($user->roles) === [self::ROLE];
    }

    /** @param array<int, string> $held */
    private function sameCaps(array $held): bool
    {
        sort($held);
        $want = self::CAPS;
        sort($want);

        return $held === $want;
    }
}
