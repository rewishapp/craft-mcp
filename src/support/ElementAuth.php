<?php

namespace rewish\craft\mcp\support;

use craft\base\ElementInterface;
use craft\elements\User;
use craft\services\Elements;
use Mcp\Exception\ToolCallException;

/**
 * Craft element canSave / canDelete for the token’s acting user.
 * Stdio (no CallContext user) skips checks — local trust, same as entry writes.
 */
final class ElementAuth
{
    public static function assertCanSave(ElementInterface $element): void
    {
        self::assert($element, 'save', static fn ($elements, $user) => $elements->canSave($element, $user));
    }

    public static function assertCanDelete(ElementInterface $element): void
    {
        self::assert($element, 'delete', static fn ($elements, $user) => $elements->canDelete($element, $user));
    }

    /**
     * @param callable(Elements, User): bool $check
     */
    private static function assert(ElementInterface $element, string $action, callable $check): void
    {
        $user = self::user();
        if ($user === null) {
            return;
        }

        $elements = \Craft::$app->getElements();

        try {
            $ok = (bool) $check($elements, $user);
        } catch (\Throwable $e) {
            throw new ToolCallException('Craft permission check failed: '.$e->getMessage(), 0, $e);
        }

        if ($ok) {
            return;
        }

        $label = $element->title ?: (string) ($element->id ?? 'new');

        throw new ToolCallException(
            "This token’s user “{$user->username}” cannot {$action} “{$label}”."
            .' Ask an admin to widen that user’s volume permissions, or mint a token for a user who has them.'
        );
    }

    private static function user(): ?User
    {
        if (CallContext::$userId === null) {
            return null;
        }

        $user = \Craft::$app->getUsers()->getUserById(CallContext::$userId);

        return $user instanceof User ? $user : null;
    }
}
