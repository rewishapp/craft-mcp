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
    'allowedIps' => [],
    'disabledTools' => [],
    'disabledScopes' => [],
    'paginationLimit' => 100,
    'maxResponseBytes' => 1048576,
    'additionalInstructions' => '',
    'showClientConfigSnippet' => true,
    'publishServerCard' => false,
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
| `httpPublicUrl` | Base URL clients should call, when it differs from the primary site. |
| `httpSessionTtl` | How long an HTTP MCP session is kept, in seconds. Minimum 60. |
| `allowedIps` | One address per line in the control panel. Empty allows any IP. |
| `disabledTools` | Tool names removed for every client. The Tools screen writes this list. |
| `disabledScopes` | Scopes that cannot be minted or used. `readonly`, `content`, or `full`. |
| `paginationLimit` | Default page size cap. 1–500. |
| `maxResponseBytes` | Soft cap for a tool payload. |
| `additionalInstructions` | Extra text included for the agent. |
| `showClientConfigSnippet` | Show the HTTP config on the token reveal page. |
| `publishServerCard` | Serve `/.well-known/mcp/server-card.json`. |
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
