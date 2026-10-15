# Tokens and scopes

HTTP clients send a bearer token. Stdio does not use one.

Create a token under **Access**. Give it a name, a scope, an optional expiry in days, and an optional section limit. The secret starts with `cmcp_`, is shown once, and is stored only as a hash. Copy it before you leave the reveal page. The reveal page also shows a Claude Code `.mcp.json` HTTP block (`type: http`) you can paste into Claude Code, Cursor, or Codex. Revoke a token from the list. Revoking deletes it. There is no way to show the secret again.

Expiry is stored and checked in UTC, the same way Craft stores datetime columns.

## Scopes

| Scope | Can |
| --- | --- |
| Readonly | Look at content, schema, sites, and the tools marked readonly. |
| Content | Readonly, plus entry, asset, category, tag, and global writes when writes are turned on. |
| Full | Content, plus system, database, GraphQL mutation, backup, and user tools. Only an admin can mint this. |

A write tool also stays hidden until **Allow writes and other dangerous tools** is on. A Content token with writes off can still read. It cannot create or update entries.

## Section limit

Leave the section limit off and the token can use every section that Craft user can already edit.

Turn the limit on and check the sections this token may change. `list_entries` without a section stays inside that list. Asking for another section returns “This token cannot access that section.” Creating or updating an entry outside the list fails the same way.

Craft’s own permissions still apply. A token cannot save a section its user cannot save.

## What to mint

- A laptop that only researches content: **Readonly**.
- An editor agent that should draft entries: **Content**, with the section limit on, and writes turned on.
- A maintainer who should read logs, run a `SELECT`, or clear caches: **Full**, writes on, and a short expiry.

Empty **Expires in days** keeps the token until you revoke it.

## Connected apps (OAuth)

Connected apps are always on the Access screen. When OAuth is off, enable it from there (or Settings), then paste the MCP URL in Claude Connectors. When it is on, approved apps appear in the table — Disconnect removes the grant and every access token it issued. Hand-minted tokens (`cmcp_…`) stay on the Tokens list as before.
