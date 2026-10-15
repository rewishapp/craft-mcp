<?php

namespace rewish\craft\mcp\http\oauth;

use craft\helpers\UrlHelper;
use rewish\craft\mcp\enums\Scope;
use rewish\craft\mcp\Mcp;
use rewish\craft\mcp\services\Setup;

/**
 * Everything the OAuth services need to know about this install, resolved
 * once so the services themselves stay plain PHP.
 */
final readonly class OAuthConfig
{
    /** Where the consent screen lives, under the control panel. */
    public const CONSENT_PATH = 'craft-mcp/connect';

    /**
     * @param list<string> $disabledScopes
     */
    public function __construct(
        public string $baseUrl,
        public string $resource,
        public string $authorizationEndpoint,
        public RedirectUris $redirectUris,
        public int $accessTokenTtl = 3600,
        public int $refreshTokenTtl = 2592000,
        public array $disabledScopes = [],
    ) {
    }

    public static function current(): self
    {
        $settings = Mcp::settings();
        $setup = new Setup();

        return new self(
            $setup->baseUrl(),
            $setup->endpointUrl(),
            UrlHelper::cpUrl(self::CONSENT_PATH),
            new RedirectUris($settings->oauthRedirectUris),
            $settings->oauthAccessTokenTtl,
            $settings->oauthRefreshTokenTtl,
            $settings->disabledScopes,
        );
    }

    /** The authorization server's identifier, which is where Craft answers. */
    public function issuer(): string
    {
        return $this->baseUrl;
    }

    public function tokenEndpoint(): string
    {
        return $this->resource.'/oauth/token';
    }

    public function registrationEndpoint(): string
    {
        return $this->resource.'/oauth/register';
    }

    /**
     * RFC 9728 path-insertion form for a resource with a path, which is the
     * address the 401 points clients at.
     */
    public function protectedResourceMetadataUrl(): string
    {
        $path = (string) parse_url($this->resource, \PHP_URL_PATH);

        return $this->baseUrl.'/.well-known/oauth-protected-resource'.rtrim($path, '/');
    }

    /**
     * @return list<Scope> every scope this install will issue
     */
    public function scopes(): array
    {
        return array_values(array_filter(Scope::cases(), fn (Scope $scope): bool => !$scope->isDisabled($this->disabledScopes)));
    }
}
