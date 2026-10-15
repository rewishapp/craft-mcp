# Release Notes for MCP - All in one

## 1.2.1 - 2026-10-15

### Fixed
- Changelog editor-guide link uses an absolute GitHub URL so it works on the Craft Plugin Store.

## 1.2.0 - 2026-10-15

### Added
- Dashboard ops hub: setup checklist, status cards (Server, Writes, HTTP, OAuth) with quick toggles, slim Connect card, and Activity “View all”.
- Access screen (was Tokens): Connected apps always visible, with an Enable OAuth CTA when the connector is off.
- Setup three-path UI: Local stdio, Remote token, Claude OAuth.
- Settings Essentials vs Advanced disclosure.
- Short [editor guide](https://github.com/rewishapp/craft-mcp/blob/v1.2.0/docs/editor-guide.md): connect, approve, drafts, disconnect.
- `delete_asset` moves an asset to the trash. `update_asset` can rename (`newFilename`) and move (`newFolder`) within a volume, and finds an asset by id or by `volume` + `folder` + `filename`.
- `upload_asset` takes a `folder` path (missing folders are created), `title`, and `alt`. Asset writes return a natural key `{volume, path, filename}`.
- `update_user` (name, groups) and `set_user_status` (`active`, `suspended`, `inactive`). `create_user` takes `firstName`, `lastName`, `groups`, and `sendActivationEmail` (off by default), and checks the Craft edition’s user cap first. User writes need a Full token.
- `assetUploadMaxBytes` setting (default 20 MB) caps URL and base64 uploads.

### Changed
- Nav label **Access** (URL still `craft-mcp/tokens`). **Recent drafts** (was Agent drafts). Access and Settings subnav only when the user has permission.
- Settings **Advanced settings** panel is clearer; TTL and upload fields use human-friendly copy, clickable presets (e.g. 20 MB, 30 days), and a live unit hint.
- Tools search sits on the right with Craft’s search icon. Dashboard metric cards use a link-color hover tint and a corner link icon (no underline).
- `create_user` takes `firstName` and `lastName` instead of `fullName`, and `username` is optional (defaults to the email).

### Security
- Asset upload / update / delete check Craft `Elements::canSave` / `canDelete` for the token’s user (volume permissions), same gate the control panel uses. Stdio with no acting user is unchanged.
- `upload_asset` URL fetches go through SSRF protection: https on port 443 only, no credentials or redirects, every resolved address must be public, the connection is pinned to the checked address, and the download is capped.
- User writes never touch admin accounts or the account the token acts as, and never set a password or change an existing email.

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
