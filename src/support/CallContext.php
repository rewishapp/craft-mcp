<?php

namespace rewish\craft\mcp\support;

/**
 * Who is calling the current MCP tool. Stdio is unscoped local trust.
 * HTTP fills this from the bearer token before the server runs.
 */
final class CallContext
{
    public static string $transport = 'stdio';

    public static ?int $userId = null;

    public static ?int $tokenId = null;

    public static ?string $scope = null;

    /** @var null|list<string> Empty list means every section. Null means unrestricted. */
    public static ?array $sections = null;

    public static function reset(string $transport = 'stdio'): void
    {
        self::$transport = $transport;
        self::$userId = null;
        self::$tokenId = null;
        self::$scope = null;
        self::$sections = null;
    }

    public static function allowsSection(?string $handle): bool
    {
        if ($handle === null || self::$sections === null || self::$sections === []) {
            return true;
        }

        return \in_array($handle, self::$sections, true);
    }
}
