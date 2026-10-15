# Connect a client

Use stdio when Cursor, Claude Code, Codex, or another client runs on the same machine as Craft. Use HTTP when the client is somewhere else.

## Wizard

```bash
php craft craft-mcp/install
```

Writes project-level config for the clients you pick. Non-interactive mode configures Claude Code and Cursor. Codex is available interactively (or say yes when prompted) and only replaces its own `[mcp_servers.<name>]` table in `.codex/config.toml`. The wizard also prints a `codex mcp add …` command for user-level Codex config.

The Setup screen in the control panel prints both stdio and HTTP blocks too.

## Stdio

From the Craft project root, the command is the CLI `php` and Composer’s bin proxy:

```json
{
  "mcpServers": {
    "craft-mcp": {
      "command": "php",
      "args": ["vendor/bin/mcp-server"]
    }
  }
}
```

`vendor/bin/mcp-server` boots Craft, then this plugin’s server. The working directory must be the Craft project, because that is where `vendor/` lives.

### DDEV

Cursor on your Mac cannot see PHP inside the container. Call DDEV, and let DDEV run PHP:

```json
{
  "mcpServers": {
    "craft-mcp": {
      "command": "ddev",
      "args": ["exec", "php", "vendor/bin/mcp-server"]
    }
  }
}
```

If Cursor’s working directory is not the DDEV project, pass the project name:

```json
{
  "mcpServers": {
    "craft-mcp": {
      "command": "/opt/homebrew/bin/ddev",
      "args": ["exec", "-p", "site", "php", "vendor/bin/mcp-server"]
    }
  }
}
```

Use the absolute path to `ddev` when the app that launches the server does not have Homebrew on its `PATH`. Replace `site` with your DDEV project name.

The Setup screen prints `PHP_BINARY`. On a web request that is the PHP-FPM binary inside the machine that rendered the page. That path is right for a client inside the same container. It is the wrong command for an app on your Mac.

Stdio has no token. It runs as local trust, with the same reach as the Craft console user. Keep that command on machines you control.

## HTTP

1. Settings → turn on **HTTP transport**. The path defaults to `mcp`, so the URL is `https://your-site.example/mcp`.
2. Tokens → create a token. The secret is shown once, with a config block you can paste.
3. Paste that block into the client.

Claude Code `.mcp.json` (also fine for Cursor and Codex):

```json
{
  "mcpServers": {
    "craft-mcp": {
      "type": "http",
      "url": "https://your-site.example/mcp",
      "headers": {
        "Authorization": "Bearer cmcp_…"
      }
    }
  }
}
```

Claude Desktop’s config file starts **local** servers only. Do not put a remote `url` there. Use Claude Code, Cursor, Codex, or a custom connector for a remote Craft site.

A missing or unknown bearer token gets `401`. The token’s Craft user, scope, and section limit apply to every call.

If another plugin also registers `/mcp`, this plugin replaces that route while HTTP transport is on. Turn HTTP off here and the other plugin’s route is left as it was.

Set **Public base URL** when clients should call a different host than the control panel.

## Claude connector (OAuth)

With **HTTP transport** and **OAuth connector** on, Claude (web, Desktop, mobile) can add the site under Customize → Connectors by its MCP URL. The user logs in to the control panel, picks a scope, and approves. No hand-minted bearer token is required.

Production checklist:

1. **Server enabled** — otherwise the endpoint answers that HTTP is not enabled.
2. **`httpPublicUrl`** — set this on a headless install so OAuth discovery and redirects point at Craft, not the frontend.
3. Connector URL must match the advertised resource exactly (usually `https://cms.example.com/mcp`).

Consent lives at **MCP - All in one → Connect** (`/admin/craft-mcp/connect`). Connected apps appear on the Tokens screen; Disconnect revokes the grant immediately. Hand-minted tokens keep working when OAuth is off or unused.

For Anthropic’s egress, add `160.79.104.0/21` to **Allowed IPs** if that list is not empty.

## After you connect

Ask the client to call `get_mcp_info` or `list_sections`. Activity should show the call. If the client cannot see the server, confirm **Server enabled** is on, then restart the stdio process. HTTP picks up setting changes on the next request. A stdio process keeps the tools it loaded at startup, so restart `mcp-server` after you hide a tool or install a plugin.
