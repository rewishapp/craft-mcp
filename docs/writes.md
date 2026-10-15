# Writes and drafts

Entry writes are drafts until you say otherwise.

**Entry write mode** in Settings:

| Mode | What a write does |
| --- | --- |
| Draft, then review | `create_entry` and `update_entry` save a draft. The live entry stays as it was. |
| Live | Writes apply to the live entry. |

Drafts show up under **Recent drafts** and in `list_drafts`, with a control panel link (and a preview link when available). `publish_entry` applies a draft. `delete_entry` moves an entry to the trash, where it can be restored.

Entry write tools also return `previewUrl` when **Include preview URLs on entry writes** is on (default): a Craft preview-token link that renders the draft as saved, without a login, on the section’s first preview target (or the entry URL). It is `null` for sections without URLs. Lifetime follows Craft’s preview tokens unless `previewTokenDuration` is set. A failing preview-target listener never fails the write — only the link is dropped.

`update_entry` accepts `expectedDateUpdated`. Pass the `dateUpdated` you last read. If someone else saved the entry since then, the write is refused.

`duplicate_entry` makes an unpublished draft. `copy_entry_to_site` copies field values onto another site as a draft. It does not translate them. `create_nested_entry` adds one Matrix block. The owner is drafted first when write mode is draft.

These tools are hidden until **Allow writes and other dangerous tools** is on, and the token scope is at least **Content**:

- `create_entry`, `update_entry`, `publish_entry`, `delete_entry`, `duplicate_entry`
- `copy_entry_to_site`, `create_nested_entry`, `move_nested_entry`
- `upload_asset`, `update_asset`, `delete_asset`, `create_category`, `create_tag`, `update_global_set`

`create_user`, `update_user`, `set_user_status`, `run_query`, `clear_caches`, `execute_graphql`, `create_backup`, and `tinker` need a **Full** token as well. `tinker` also needs **Allow tinker** and the `psy/psysh` package. `upload_asset` accepts an `https` URL or a base64 payload, up to `assetUploadMaxBytes` (20 MB by default). Asset upload, update, and delete also require the token’s Craft user to pass `Elements::canSave` / `canDelete` for that asset (the same volume permissions as the control panel). Stdio without an acting user skips that check.

**URL fetches are guarded against SSRF.** The server fetches only `https` on port 443, with no credentials in the URL and no redirects. It resolves the host itself, refuses the request if any address is not on the public internet (loopback, private, link-local such as `169.254.169.254`, carrier-grade NAT, reserved, IPv4-mapped), and connects to the address it checked, so a DNS change between the check and the request cannot swap it. The download stops one byte past the size cap.

**User writes** need a Full token and an admin Craft user. They never create or touch an admin, never change the account the token acts as, and never set a password or an existing account’s email. `delete_asset` is a soft delete: the asset can be restored from the trash.

Turn writes off again when the agent is finished. Existing drafts stay in the control panel for review.
