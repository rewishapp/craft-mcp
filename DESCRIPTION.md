# MCP - All in one

Give AI agents a real, controlled way to work with your Craft CMS install.

**MCP - All in one** is a Model Context Protocol server for Craft. Connect Cursor, Claude Code, Codex, or any other MCP client, and agents can read your content model, query entries, and (when you allow it) save draft changes for a human to review and publish.

## Why it exists

Most AI setups guess at your CMS. This plugin talks to Craft directly: sections, fields, sites, assets, drafts, and permissions. You keep the keys. Agents do the drafting.

## What you get

### Control panel

A full CP section so you are never flying blind:

- **Dashboard** — server status, HTTP, writes, tool counts, tokens, activity, and pending drafts
- **Agent drafts** — drafts an agent created, with links into the entry editor
- **Tools** — full catalog; search, hide, or show any tool
- **Tokens** — mint and revoke bearer tokens for remote clients
- **Activity** — every tool call, including failures
- **Setup** — copy-paste config for local stdio and remote HTTP
- **Settings** — enable the server, unlock writes, tune HTTP, add agent instructions

### Two ways to connect

- **Local (stdio)** — same machine as the Craft install; uses that PHP binary
- **Online / production (HTTP)** — remote clients hit your site (default path `/mcp`) with a bearer token

### Safe by default

- In **production**, the server starts **off**
- **Writes** are off until you turn them on
- Entry writes default to **drafts**, not live publishes
- Tokens use scopes: **Readonly**, **Content**, and **Full**
- Tokens can be limited to specific sections
- Calls still respect Craft’s own user permissions
- Secrets are redacted from logs and environment tools

## Tools agents can use

**Content & schema** — list and get entries, count, drafts, revisions, describe entry types, sections, fields, volumes, assets, categories, tags, globals

**Writes (when enabled)** — create/update/publish/delete/duplicate entries, Matrix blocks, multi-site copy, asset upload, categories, tags, globals — draft-first by default

**Sites** — sites and site groups

**GraphQL** — schemas, safe queries, and mutations when Full scope + writes allow it

**System & debug** — system info, config (safe slice), logs, queue, deprecations, project config diff, database schema, read-only SQL, backups, caches

**Optional plugins** — Commerce products/orders, Freeform submissions, and Calendar events when those plugins are installed

Plus **prompts** and **resources** (`craft://…`) so agents can explore the content model without guessing.

## Built for real teams

- Hide tools you never want exposed
- Separate who can open the dashboard, manage tokens, and change settings
- Only admins can mint **Full** tokens
- Review agent drafts in the CP before anything goes live

## Requirements

- Craft CMS **5.0+** or **6.0+**
- PHP **8.2+**

Package: `rewi.sh/craft-mcp`  
Source: [github.com/rewishapp/craft-mcp](https://github.com/rewishapp/craft-mcp)