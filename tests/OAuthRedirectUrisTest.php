<?php

namespace rewish\craft\mcp\tests;

use PHPUnit\Framework\TestCase;
use rewish\craft\mcp\http\oauth\OAuthError;
use rewish\craft\mcp\http\oauth\Pkce;
use rewish\craft\mcp\http\oauth\RedirectUris;

/**
 * @coversNothing
 */
final class OAuthRedirectUrisTest extends TestCase
{
    public function testExactAndLoopbackPortFlexible(): void
    {
        $uris = new RedirectUris(RedirectUris::DEFAULTS);

        self::assertTrue($uris->allows('https://claude.ai/api/mcp/auth_callback'));
        self::assertFalse($uris->allows('https://evil.example/callback'));
        self::assertTrue($uris->allows('http://localhost:54321/callback'));
        self::assertTrue($uris->allows('http://127.0.0.1:9999/callback'));
        self::assertFalse($uris->allows('http://localhost:54321/other'));
        self::assertFalse($uris->allows('https://localhost/callback'));
    }

    public function testPkceS256Only(): void
    {
        $verifier = str_repeat('a', 43);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        Pkce::assertChallenge($challenge, 'S256');
        self::assertTrue(Pkce::verify($verifier, $challenge));
        self::assertFalse(Pkce::verify('wrong-verifier-that-is-long-enough-xxxxxx', $challenge));

        $this->expectException(OAuthError::class);
        Pkce::assertChallenge($challenge, 'plain');
    }
}
