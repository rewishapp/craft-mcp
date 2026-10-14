# Writes and drafts

Entry writes are drafts until you say otherwise.

**Entry write mode** in Settings:

| Mode | What a write does |
| --- | --- |
| Draft, then review | `create_entry` and `update_entry` save a draft. The live entry stays as it was. |
| Live | Writes apply to the live entry. |

Drafts show up under **Agent drafts** and in `list_drafts`, with a control panel link. `publish_entry` applies a draft. `delete_entry` moves an entry to the trash, where it can be restored.

`update_entry` accepts `expectedDateUpdated`. Pass the `dateUpdated` you last read. If someone else saved the entry since then, the write is refused.

`duplicate_entry` makes an unpublished draft. `copy_entry_to_site` copies field values onto another site as a draft. It does not translate them. `create_nested_entry` adds one Matrix block. The owner is drafted first when write mode is draft.

These tools are hidden until **Allow writes and other dangerous tools** is on, and the token scope is at least **Content**:

- `create_entry`, `update_entry`, `publish_entry`, `delete_entry`, `duplicate_entry`
- `copy_entry_to_site`, `create_nested_entry`, `move_nested_entry`
- `upload_asset`, `update_asset`, `create_category`, `create_tag`, `update_global_set`

`create_user`, `run_query`, `clear_caches`, `execute_graphql`, `create_backup`, and `tinker` need a **Full** token as well. `tinker` also needs **Allow tinker** and the `psy/psysh` package. `upload_asset` accepts an `https` URL or a base64 payload, up to 20 MB.

Turn writes off again when the agent is finished. Existing drafts stay in the control panel for review.
