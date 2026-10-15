# Configuration

Settings in the control panel are stored in project config. Anything you set in `config/craft-mcp.php` wins over the control panel.

```php
<?php

return [
    'enabled' => true,
    'enableDangerousTools' => false,
    'enableTinker' => false,
    'entryWriteMode' => 'draft',
    'httpTransport' => false,
    'httpPath' => 'mcp',
    'httpPublicUrl' => null,
    'httpSessionTtl' => 3600,
    'oauth' => false,
    'oauthRedirectUris' => [
        'https://claude.ai/api/mcp/auth_callback',
        'https://claude.com/api/mcp/auth_callback',
        'http://localhost/callback',
        'http://127.0.0.1/callback',
    ],
    'oauthAccessTokenTtl' => 3600,
    'oauthRefreshTokenTtl' => 2592000,
    'allowedIps' => [],
    'disabledTools' => [],
    'disabledScopes' => [],
    'paginationLimit' => 100,
    'maxResponseBytes' => 1048576,
    'assetUploadMaxBytes' => 20971520,
    'additionalInstructions' => '',
    'showClientConfigSnippet' => true,
    'publishServerCard' => false,
    'previewUrls' => true,
    'previewTokenDuration' => null,
    'logLevel' => 'error',
];
```

| Key | Meaning |
| --- | --- |
| `enabled` | The server accepts clients. Production defaults this to off. |
| `enableDangerousTools` | Unlocks writes, SQL, GraphQL mutations, backups, and cache clearing. |
| `enableTinker` | Unlocks `tinker` when `psy/psysh` is installed. |
| `entryWriteMode` | `draft` or `live`. |
| `httpTransport` | Serves `/mcp` (or `httpPath`) for bearer tokens. |
| `httpPath` | Path on the primary site. Default `mcp`. Letters, numbers, hyphens, and slashes. |
| `httpPublicUrl` | Base URL clients should call, when it differs from the primary site. Required on headless installs when OAuth is on (otherwise every OAuth URL points at the frontend). |
| `httpSessionTtl` | How long an HTTP MCP session is kept, in seconds. Minimum 60. |
| `oauth` | One-click connector for Claude and other OAuth clients. Requires `httpTransport`. Off by default. |
| `oauthRedirectUris` | Callback URLs apps may register. Defaults cover Claude apps and Claude Code loopback. |
| `oauthAccessTokenTtl` | OAuth access token lifetime in seconds. Minimum 60. |
| `oauthRefreshTokenTtl` | Refresh token lifetime in seconds; each use replaces it. Minimum 3600. |
| `allowedIps` | One address or CIDR range per line (IPv4 and IPv6). Empty allows any IP. Example: `160.79.104.0/21` for Anthropic’s Claude connector egress. A line that does not parse matches nothing. |
| `disabledTools` | Tool names removed for every client. The Tools screen writes this list. |
| `disabledScopes` | Scopes that cannot be minted or used. `readonly`, `content`, or `full`. |
| `paginationLimit` | Default page size cap. 1–500. |
| `maxResponseBytes` | Soft cap for a tool payload. |
| `assetUploadMaxBytes` | Largest file `upload_asset` accepts, from a URL or base64, in bytes. Default 20971520 (20 MB). Minimum 1024. |
| `additionalInstructions` | Extra text included for the agent. |
| `showClientConfigSnippet` | Show the HTTP config on the token reveal page. |
| `publishServerCard` | Serve `/.well-known/mcp/server-card.json`. |
| `previewUrls` | Include `previewUrl` on entry write tool results. |
| `previewTokenDuration` | Preview link lifetime in seconds. Empty uses Craft’s default. |
| `logLevel` | `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, or `emergency`. |

`disabledPrompts` and `disabledResources` are the same idea for prompts and resources. Set them in the config file.

## Environment

```php
return [
    'enabled' => App::env('CRAFT_ENVIRONMENT') !== 'production',
    'enableDangerousTools' => App::env('MCP_WRITES') === '1',
    'allowedIps' => array_filter(explode(',', (string) App::env('MCP_ALLOWED_IPS'))),
];
```

Use `craft\helpers\App` for those `App::env()` calls.
