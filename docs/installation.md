# Install

Requirements: Craft CMS 5.0+ or 6.0+, PHP 8.2+.

From the Craft project:

```bash
composer require rewi.sh/craft-mcp
php craft plugin/install craft-mcp
```

While the package is still a local folder, point Composer at it first:

```bash
composer config repositories.craft-mcp '{"type": "path", "url": "../craft-mcp", "options": {"symlink": true}}'
composer require rewi.sh/craft-mcp:@dev
php craft plugin/install craft-mcp
```

Change the path so it points at this folder.

After install, open **MCP - All in one** in the control panel sidebar.

## Turn the server on

Development installs start with the server on and writes off. Production installs start with the server off.

Settings → **Server enabled**, or `config/craft-mcp.php`:

```php
<?php

return [
    'enabled' => true,
];
```

A file in `config/craft-mcp.php` overrides the control panel for any key it sets.

## DDEV

Mount this folder into the project and add the path repository inside the container, the same way other local plugins are wired. The stdio command from your Mac is `ddev`, not the PHP binary printed inside the container. See [Connect a client](connect.md).
