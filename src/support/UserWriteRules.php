<?php

namespace rewish\craft\mcp\support;

use Mcp\Exception\ToolCallException;

/**
 * The rules every user write answers to, on every transport and scope.
 *
 * They are not left to Craft's permissions: user writes need a full-scope
 * token, and full scope skips permission lookups by design, so nothing else
 * would stop a token from turning a colleague into an admin or locking out
 * its own owner. Each refusal says where the change can be made instead.
 *
 * - No write sets the admin flag: the tools have no such parameter.
 * - No write touches an admin account.
 * - No write touches the caller's own account.
 * - No write sets a password or changes an existing account's email: either
 *   one hands the account to whoever asked.
 */
final class UserWriteRules
{
    /** Parameter names no user write may take. */
    public const FORBIDDEN_PARAMETERS = ['admin', 'password', 'newpassword', 'unverifiedemail'];

    public static function assertMayChange(string $username, bool $admin, ?int $targetId, ?int $callerId): void
    {
        if ($admin) {
            throw new ToolCallException("User '{$username}' is an admin. The MCP server never changes an admin account; an admin can do it in the control panel.");
        }

        if ($callerId !== null && $targetId === $callerId) {
            throw new ToolCallException("User '{$username}' is your own account. The MCP server never changes the account it acts as; do it in the control panel under My Account.");
        }
    }

    /**
     * Craft caps accounts by edition (Solo 1, Team 5) and refuses a save over
     * the cap without setting an error, so the save alone could only report a
     * failure with no reason.
     *
     * @param null|int $maxUsers Craft's cap for this edition, null for none
     */
    public static function assertRoomForUser(string $edition, ?int $maxUsers, int $userCount): void
    {
        if ($maxUsers === null || $userCount < $maxUsers) {
            return;
        }

        throw new ToolCallException(
            "Craft {$edition} allows {$maxUsers} user account".($maxUsers === 1 ? '' : 's')
            ." and this install has {$userCount}. A new account needs a higher Craft edition.",
        );
    }

    /**
     * Craft keeps user groups to its Pro edition and throws a bare edition
     * error otherwise; this says so before anything is saved.
     */
    public static function assertGroupsAvailable(string $edition, bool $isPro): void
    {
        if ($isPro) {
            return;
        }

        throw new ToolCallException("User groups need Craft Pro; this install runs Craft {$edition}. Leave groups out to create or update the account without them.");
    }
}
