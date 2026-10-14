# MCP - All in one

MCP server for Craft CMS. Agents read and write this install, and the control panel is where you turn the server on, mint tokens, and review drafts.

Package `rewi.sh/craft-mcp`. Handle `craft-mcp`. Craft 5.0+ and 6.0+. PHP 8.2+.

## Start here

1. [Install](installation.md)
2. [Control panel](control-panel.md)
3. [Connect a client](connect.md)
4. [Tokens and scopes](tokens.md)
5. [Tools, prompts, and resources](tools.md)
6. [Writes and drafts](writes.md)
7. [Configuration](configuration.md)

## What you get

- A sidebar in the control panel: Dashboard, Agent drafts, Tools, Tokens, Activity, Setup, and Settings.
- Stdio for Cursor, Claude Code, Codex, and any other client on the same machine.
- HTTP at `/mcp` for a client that is not on this machine. Turn it on, then create a bearer token.
- Read tools for entries, schema, assets, sites, logs, and GraphQL queries.
- Write tools that save drafts first, so a person can publish them.
- Commerce, Freeform, and Calendar tools when those plugins are installed.

In production the server starts off. Turn it on in Settings, or in `config/craft-mcp.php`, before a client can connect.
