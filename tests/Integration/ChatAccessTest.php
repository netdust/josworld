<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\site\ChatAccess;
use WP_UnitTestCase;

/**
 * Only the chat machine user may hold an application password, and the `chat` role is the
 * exact limit of what it can do: the connector's key reaches all of /wp/v2 the account can.
 */
final class ChatAccessTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The suite runs without HTTPS, where WordPress offers no application passwords at all.
        add_filter('wp_is_application_passwords_available', '__return_true');
    }

    public function testTheChatRoleHoldsExactlyTheAllowList(): void
    {
        $role = get_role(ChatAccess::ROLE);
        $this->assertNotNull($role);

        $held = array_keys(array_filter($role->capabilities));
        sort($held);
        $want = ChatAccess::CAPS;
        sort($want);

        $this->assertSame($want, $held);
        $this->assertFalse($role->has_cap('unfiltered_html'));
        $this->assertFalse($role->has_cap('publish_pages'));
    }

    public function testADriftedRoleIsResetToTheAllowList(): void
    {
        get_role(ChatAccess::ROLE)->add_cap('unfiltered_html');
        get_role(ChatAccess::ROLE)->add_cap('delete_pages');

        ntdst_get(ChatAccess::class)->ensureRole();

        $this->assertFalse(get_role(ChatAccess::ROLE)->has_cap('unfiltered_html'));
        $this->assertFalse(get_role(ChatAccess::ROLE)->has_cap('delete_pages'));
    }

    public function testTheChatUserMayHoldAKey(): void
    {
        $chat = self::factory()->user->create_and_get(['role' => ChatAccess::ROLE]);

        $this->assertTrue(user_can($chat, 'ntdst_chat'));
        $this->assertTrue(wp_is_application_passwords_available_for_user($chat));
    }

    public function testAnAdministratorOrEditorMayNot(): void
    {
        foreach (['administrator', 'editor'] as $roleName) {
            $user = self::factory()->user->create_and_get(['role' => $roleName]);

            $this->assertFalse(user_can($user, 'ntdst_chat'), $roleName);
            $this->assertFalse(wp_is_application_passwords_available_for_user($user), $roleName);
        }
    }

    public function testAnAdministratorWithTheChatRoleAddedMayNot(): void
    {
        $user = self::factory()->user->create_and_get(['role' => 'administrator']);
        $user->add_role(ChatAccess::ROLE);

        $this->assertTrue(user_can($user, 'ntdst_chat'));
        $this->assertFalse(wp_is_application_passwords_available_for_user($user));
    }

    public function testWithoutTheChatRoleNobodyMay(): void
    {
        $chat = self::factory()->user->create_and_get(['role' => ChatAccess::ROLE]);
        $admin = self::factory()->user->create_and_get(['role' => 'administrator']);
        remove_role(ChatAccess::ROLE);
        $chat = get_userdata($chat->ID);

        $this->assertFalse(wp_is_application_passwords_available_for_user($chat));
        $this->assertFalse(wp_is_application_passwords_available_for_user($admin));

        ntdst_get(ChatAccess::class)->ensureRole();
    }

    // The OAuth plugin mints its session key directly, past the availability check above.
    public function testAKeyMintedDirectlyForAnAdministratorDoesNotSurvive(): void
    {
        $admin = self::factory()->user->create(['role' => 'administrator']);

        \WP_Application_Passwords::create_new_application_password($admin, ['name' => 'MCP OAuth – Claude']);

        $this->assertSame([], \WP_Application_Passwords::get_user_application_passwords($admin));
    }

    public function testAKeyMintedDirectlyForTheChatUserStays(): void
    {
        $chat = self::factory()->user->create(['role' => ChatAccess::ROLE]);

        \WP_Application_Passwords::create_new_application_password($chat, ['name' => 'MCP OAuth – Claude']);

        $this->assertCount(1, \WP_Application_Passwords::get_user_application_passwords($chat));
    }
}
