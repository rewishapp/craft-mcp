# Editor guide

Connect Claude or Cursor, approve access, review drafts, disconnect. One page.

## Connect

**Same machine (Cursor, Claude Code, Codex)**

```bash
php craft craft-mcp/install
```

Or paste the **Local stdio** block from **MCP → Setup**.

**Remote HTTP (token)**

1. Settings → turn on **HTTP transport** (or use the Dashboard toggle).
2. **Access** → mint a Content (or Readonly) token.
3. Paste the **Remote token** config from Setup; replace `YOUR_TOKEN`.

**Claude Connectors (OAuth)**

1. Turn on **HTTP** and **OAuth** (Dashboard, Access, or Settings).
2. Set **Public base URL** if the control panel host is not the public CMS URL.
3. In Claude, add a custom connector and paste the MCP URL from Setup → **Claude OAuth**.
4. Sign in to Craft, pick a scope, approve.

## Approve and scopes

| Scope | What the agent can do |
| --- | --- |
| Readonly | Browse entries, schema, assets, sites. No writes. |
| Content | Plus create/update entries (drafts by default), assets, categories. |
| Full | Plus SQL, GraphQL mutations, backups, cache, tinker. Admin only to mint. |

Asset uploads and deletes respect that Craft user’s volume permissions.

## Review drafts

Entry writes default to **draft**. Open **MCP → Recent drafts**, preview when a link is shown, then **Publish** — or edit in the entry editor.

## Disconnect

- **OAuth:** Access → Connected apps → Disconnect.
- **Token:** Access → Revoke.

Turn the server off from the Dashboard when you want every client refused.

## More detail

- [Connect a client](connect.md)
- [Tokens and scopes](tokens.md)
- [Writes and drafts](writes.md)
- [Control panel](control-panel.md)
