# Release Notes for MCP - All in one

## 1.1.0 - 2026-10-15

### Added
- Entry writes return `previewUrl`: a Craft preview-token link on the section’s first preview target (or the entry URL). `null` when the section has no URL. Covers `create_entry`, `update_entry`, `copy_entry_to_site`, `duplicate_entry`, `create_nested_entry`, and `move_nested_entry`. Settings: `previewUrls` (default on) and optional `previewTokenDuration`. A failing preview-target listener drops the link, never the write. Preview tokens use `userId: null` (anonymous Share link).
- `php craft craft-mcp/install` wizard writes Claude Code (`.mcp.json`) and Cursor (`.cursor/mcp.json`) config. Codex (`.codex/config.toml`) is offered interactively but not preselected; only that server’s `[mcp_servers.<name>]` table (and its subtables) is replaced. Codex configs get an absolute `cwd` and binary paths. Prints a `codex mcp add` command for user-level config, and notes that project config needs a trusted project.
- `allowedIps` accepts CIDR ranges as well as exact addresses (IPv4 and IPv6). A bad entry or client address matches nothing. Useful for allowing Anthropic’s Claude connector egress (`160.79.104.0/21`) without emptying the allowlist.
- `list_console_commands` reports `skippedModules` when a module’s controller path cannot be resolved, and still lists every other command.
- OAuth connector for Claude (web, Desktop, mobile) and other OAuth clients. With `oauth` on (requires HTTP transport), a user pastes the MCP URL, logs in to the control panel, picks a scope, and approves. Discovery (RFC 9728 / RFC 8414), Dynamic Client Registration restricted to `oauthRedirectUris`, PKCE S256, rotating refresh tokens, and Connected Apps with Disconnect on the Tokens screen. Off by default; with it off, hand-minted bearer tokens behave exactly as before. Settings: `oauth`, `oauthRedirectUris`, `oauthAccessTokenTtl`, `oauthRefreshTokenTtl`. Set `httpPublicUrl` on headless installs so OAuth URLs point at Craft.

### Changed
- Docs and README point local setup at the install wizard and remote HTTP at Claude Code / Cursor / Codex (not Claude Desktop’s local-server config file).
- `allowedIps` matching uses Yii `IpHelper::inRange`.
- `list_console_commands` uses Craft’s HelpController listing (with per-module skip on bad controller paths) and detects HTTP via `getIsConsoleRequest()`.
- `list_entries` / `count_entries` treat omitted status or `status: "any"` as every status; pass `status: "live"` for live only.
- Token reveal and Setup show a Claude Code `.mcp.json` HTTP block (`type: http`).
- Token expiry is stored and compared in UTC.

### Security
- OAuth token exchange re-checks that the user may still hold the grant (active account, manage-tokens permission, allowed scope). Standing loss revokes the connection.
- Consent offers only scopes that are both requested and allowed; approve refuses anything else.
- Disconnect wins races with refresh: exchange re-checks the grant after issuing, and every MCP request rejects an OAuth access token whose grant was revoked.

## 1.0.0 - 2026-10-14

### Added
- MCP server for Craft CMS 5 and 6 with stdio and optional HTTP transport
- Control panel: dashboard, agent drafts, tools, tokens, activity, setup, and settings
- Content tools for entries, schema, assets, categories, tags, globals, and sites
- Draft-first writes with scopes (readonly, content, full) and optional section limits
- GraphQL, system, database, and debug tools
- Optional Commerce, Freeform, and Calendar tools when those plugins are installed
- Prompts and `craft://` resources for agents
- Bearer tokens for remote HTTP clients
- Activity log of tool calls
