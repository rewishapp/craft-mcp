# Control panel

**MCP - All in one** adds its own item in the control panel sidebar.

| Screen | What it is for |
| --- | --- |
| Dashboard | Server, environment, HTTP, writes, tool count, tokens, today’s calls, and drafts waiting. |
| Agent drafts | Drafts an agent saved, with links into the entry editor. |
| Tools | Every tool, grouped by category. Search the list. Hide or show a tool. Hidden tools stay hidden for every client. |
| Tokens | Create, reveal, and revoke bearer tokens for HTTP clients. |
| Activity | Every tool call, including failures. Stdio calls have no user. HTTP calls show the token’s user. |
| Setup | Config blocks for a local client and for HTTP. |
| Settings | Server, writes, HTTP, and the extra instructions sent to the agent. |

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
