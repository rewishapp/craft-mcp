<?php

namespace rewish\craft\mcp\support;

use craft\elements\User;
use rewish\craft\mcp\enums\Scope;

/**
 * Which scopes a user may hold a token for: the one rule behind the OAuth
 * consent screen and its re-checks, so what is offered and what is accepted
 * can never disagree.
 */
final class ScopePolicy
{
    /**
     * Full scope bypasses all read and write authorization and includes code
     * execution, so only Craft's real admin flag may grant it; the
     * craft-mcp:manageTokens permission alone is not enough.
     */
    public static function fullAllowed(?User $user): bool
    {
        return $user->admin ?? false;
    }

    /**
     * Readonly and Content for anyone with the token permission, Full for
     * admins, minus anything in the disabledScopes setting, which beats every
     * permission, admin included.
     *
     * @param list<string> $disabledScopes
     *
     * @return list<Scope>
     */
    public static function allowed(?User $user, array $disabledScopes): array
    {
        $scopes = [Scope::Readonly, Scope::Content];
        if (self::fullAllowed($user)) {
            $scopes[] = Scope::Full;
        }

        return array_values(array_filter(
            $scopes,
            static fn (Scope $scope): bool => !$scope->isDisabled($disabledScopes),
        ));
    }
}
