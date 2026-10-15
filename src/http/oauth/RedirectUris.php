<?php

namespace rewish\craft\mcp\http\oauth;

/**
 * The install's allowlist of redirect URIs an app may register.
 *
 * Registration is open (any app can register itself), so the redirect URI is
 * the only thing between a consent screen and an attacker's server. Only
 * callbacks the install trusts can be registered, and the list fails closed.
 *
 * A loopback entry (`http://localhost/...`, `http://127.0.0.1/...`) matches any
 * port, as RFC 8252 section 7.3 requires for native apps such as Claude Code,
 * which listen on a port chosen per session. Everything else matches exactly.
 */
final readonly class RedirectUris
{
    public const DEFAULTS = [
        'https://claude.ai/api/mcp/auth_callback',
        'https://claude.com/api/mcp/auth_callback',
        'http://localhost/callback',
        'http://127.0.0.1/callback',
    ];

    /**
     * @param list<string> $allowed
     */
    public function __construct(private array $allowed) {}

    public function allows(string $uri): bool
    {
        foreach ($this->allowed as $entry) {
            if ($uri === $entry || $this->sameLoopbackIgnoringPort($uri, (string) $entry)) {
                return true;
            }
        }

        return false;
    }

    private function sameLoopbackIgnoringPort(string $uri, string $entry): bool
    {
        $candidate = parse_url($uri);
        $allowed = parse_url($entry);

        if (!\is_array($candidate) || !\is_array($allowed) || !$this->isLoopback($allowed)) {
            return false;
        }

        return ($candidate['scheme'] ?? null) === 'http'
            && ($candidate['host'] ?? null) === $allowed['host']
            && ($candidate['path'] ?? '') === ($allowed['path'] ?? '')
            && !isset($candidate['query'])
            && !isset($candidate['fragment'])
            && !isset($candidate['user']);
    }

    /**
     * @param array<string, int|string> $parts
     */
    private function isLoopback(array $parts): bool
    {
        return ($parts['scheme'] ?? null) === 'http' && \in_array($parts['host'] ?? null, ['localhost', '127.0.0.1'], true);
    }
}
