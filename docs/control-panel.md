# Control panel

**MCP - All in one** adds its own item in the control panel sidebar.

| Screen | What it is for |
| --- | --- |
| Dashboard | Ops hub: setup checklist, status cards with quick toggles, metrics, recent activity. |
| Recent drafts | Drafts on this install (including agent writes), with preview links when available. |
| Tools | Every tool, grouped by category. Search the list. Hide or show a tool. Hidden tools stay hidden for every client. |
| Access | Bearer tokens and Connected apps (OAuth). Enable OAuth from here if it is off. |
| Activity | Every tool call, including failures. Stdio calls have no user. HTTP calls show the token’s user. |
| Setup | Three paths: local stdio, remote token, Claude OAuth. |
| Settings | Essentials (server, writes, HTTP, OAuth, public URL) plus Advanced. Hidden unless you can change settings. |

## Permissions

| Permission | Allows |
| --- | --- |
| Open the MCP dashboard | Dashboard, drafts, tools, activity, and setup. |
| Manage MCP tokens | Create and revoke your own tokens. |
| Manage everyone’s MCP tokens | See and revoke tokens that belong to other users. |
| Change MCP settings | Save settings and hide tools. |

Admins have these permissions. Only an admin can mint a **Full** token.

## Tools screen

Search filters the catalog in place. **Hide** removes that tool from the server. **Show** puts it back. A tool can also be missing because writes are off, the token scope is too low, or the plugin it needs is not installed. Commerce tools stay hidden until Craft Commerce is installed. The same is true for Freeform submissions and Calendar events.
