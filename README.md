# MCP - All in one

MCP server for Craft CMS. Agents can read and write content, inspect the schema, and you get a control panel for tokens, activity, and drafts.

Docs: [docs/README.md](docs/README.md)  
Source: [github.com/rewishapp/craft-mcp](https://github.com/rewishapp/craft-mcp)  
Packagist: [packagist.org/packages/rewi.sh/craft-mcp](https://packagist.org/packages/rewi.sh/craft-mcp)  
Contributing: [CONTRIBUTING.md](CONTRIBUTING.md)

Package: `rewi.sh/craft-mcp`  
Handle: `craft-mcp`  
Craft: 5.0+ and 6.0+  
PHP: 8.2+

## Install

```bash
composer require "rewi.sh/craft-mcp:^1.0.0" -w && php craft plugin/install craft-mcp
```

With DDEV:

```bash
ddev composer require "rewi.sh/craft-mcp:^1.0.0" -w && ddev craft plugin/install craft-mcp
```

Local path (this repo next to your Craft project):

```bash
composer config repositories.craft-mcp '{"type": "path", "url": "../plugins/craft-mcp", "options": {"symlink": true}}'
composer require rewi.sh/craft-mcp:@dev -w && php craft plugin/install craft-mcp
```

Open **MCP - All in one** in the control panel. In production the server starts off. Turn it on under Settings, or in `config/craft-mcp.php`:

```php
<?php

return [
    'enabled' => true,
    'enableDangerousTools' => true,
];
```

`enableDangerousTools` is what unlocks entry writes. Writes are drafts until someone publishes them.

## Connect Cursor, Claude Code, Codex, or other

The Setup screen copies a stdio config that points at `bin/mcp-server`. For a remote client, turn on HTTP and create a token. The default endpoint path is `mcp`.

## Scopes

- `readonly` can look at content and schema.
- `content` can also write entries, assets, categories, tags, and globals.
- `full` can use system, database, and GraphQL mutation tools. Only admins can mint it.

A token can be limited to specific sections. Calls still have to pass Craft’s own permissions for that user.
