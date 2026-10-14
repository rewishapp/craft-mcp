# MCP - All in one

MCP server for Craft CMS. Agents can read and write content, inspect the schema, and you get a control panel for tokens, activity, and drafts.

Docs: [docs/README.md](docs/README.md)  
Source: [github.com/rewishapp/craft-mcp](https://github.com/rewishapp/craft-mcp)

Package: `rewi.sh/craft-mcp`  
Handle: `craft-mcp`  
Craft: 5.0+ and 6.0+  
PHP: 8.2+

## Install

Once on Packagist:

```bash
composer require rewi.sh/craft-mcp
php craft plugin/install craft-mcp
```

From GitHub before Packagist:

```bash
composer config repositories.craft-mcp '{"type": "vcs", "url": "https://github.com/rewishapp/craft-mcp.git"}'
composer require rewi.sh/craft-mcp:dev-main
php craft plugin/install craft-mcp
```

Local path (this repo checked out next to your Craft project):

```bash
composer config repositories.craft-mcp '{"type": "path", "url": "../plugins/craft-mcp"}'
composer require rewi.sh/craft-mcp:@dev
php craft plugin/install craft-mcp
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

## Releases

Version comes from `composer.json`. On every push to `main`, CI runs lint and tests. If that version is not tagged yet and `CHANGELOG.md` has a matching `## x.y.z` section, GitHub Actions creates `vX.Y.Z` and a GitHub Release.

To ship a new version: bump `version` in `composer.json`, add notes under that version in `CHANGELOG.md`, merge to `main`. Unchanged versions do not create another release.

After the first GitHub Release, submit `rewi.sh/craft-mcp` on [Packagist](https://packagist.org/packages/submit) and enable the GitHub service hook so tags sync automatically.
