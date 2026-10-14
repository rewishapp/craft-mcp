# Tools, prompts, and resources

A tool is hidden when any of these is true: it is in `disabledTools`, writes are off and the tool is marked dangerous, or the token scope is below the tool’s minimum. Stdio is unscoped local trust, so scope does not apply there. Writes still have to be turned on.

Call `list_mcp_tools` to see the full catalog, including tools the current connection cannot run. Call `get_mcp_info` for the plugin version and how many tools this connection can run.

## Content and schema

| Tool | Minimum scope | Writes |
| --- | --- | --- |
| `list_entries`, `count_entries`, `get_entry`, `list_drafts`, `list_revisions` | Readonly | |
| `describe_entry_schema`, `list_sections`, `list_fields`, `list_plugins` | Readonly | |
| `list_volumes`, `list_asset_folders`, `list_assets`, `get_asset` | Readonly | |
| `list_categories`, `list_tags` | Readonly | |
| `create_entry`, `update_entry`, `publish_entry`, `delete_entry`, `duplicate_entry` | Content | Yes |
| `copy_entry_to_site`, `create_nested_entry`, `move_nested_entry` | Content | Yes |
| `upload_asset`, `update_asset`, `create_category`, `create_tag`, `update_global_set` | Content | Yes |
| `list_users` | Content | |
| `list_globals` | Full | |
| `create_user` | Full | Yes |

`list_entries` filters by section, entry type, status, site, search, slug, or author username. Relations come back as natural keys. `get_entry` takes an id, or a section and slug, in the same field shape `create_entry` accepts.

## Sites

`list_sites`, `get_site`, and `list_site_groups`. Readonly.

## GraphQL

| Tool | Minimum scope | Writes |
| --- | --- | --- |
| `list_graphql_schemas`, `get_graphql_schema` | Readonly | |
| `query_graphql`, `list_graphql_tokens` | Full | |
| `execute_graphql` | Full | Yes |

`query_graphql` rejects mutations. `execute_graphql` can run them when writes are on.

## System, database, and debug

| Tool | Minimum scope | Writes |
| --- | --- | --- |
| `get_system_info`, `list_console_commands`, `list_routes` | Readonly | |
| `get_queue_jobs`, `get_deprecations` | Readonly | |
| `get_config`, `read_logs`, `get_last_error`, `clear_caches` | Full | `clear_caches` only |
| `get_database_info`, `get_database_schema`, `get_table_counts` | Full | |
| `run_query`, `explain_query` | Full | `run_query` is a single `SELECT`, capped at 200 rows |
| `list_backups`, `create_backup` | Full | `create_backup` only |
| `get_project_config_diff`, `get_environment`, `list_event_handlers` | Full | |
| `tinker` | Full | Yes, and only when tinker is enabled |
| `reload_mcp` | Full | |

`read_logs` and `get_environment` redact values that look like secrets. `get_config` returns a safe slice of general config.

## When another plugin is installed

| Tool | Needs |
| --- | --- |
| `list_freeform_submissions` | Freeform. Content scope. |
| `list_calendar_events` | Calendar. Readonly. |
| `list_product_types`, `list_order_statuses` | Commerce. Readonly. |
| `list_products`, `get_product`, `list_orders`, `get_order` | Commerce. Full scope. |

## This server

`get_mcp_info` and `list_mcp_tools` are readonly. `reload_mcp` needs Full. On HTTP, new tool classes show up on the next request. On stdio, restart the process.

## Prompts

`content_health_analysis`, `content_audit`, `debug_content_issue`, `create_entry_guide`, `query_entries_guide`, `bulk_entry_operations`, `review_pending_drafts`, `explore_section_schema`, `field_usage_analysis`, `explore_content_model`.

These tell the agent which tools to call. They do not change content by themselves.

## Resources

| URI | What it returns |
| --- | --- |
| `craft://schema/sections` | Every section. |
| `craft://schema/sections/{handle}` | One section, its entry types, and fields. |
| `craft://schema/fields` | Custom fields. |
| `craft://schema/fields/{handle}` | One field and where it is used. |
| `craft://entries/{section}` | Up to 50 entries in that section. |
| `craft://entries/{section}/stats` | Status counts. |
| `craft://entries/{section}/{slug}` | A short summary of one entry. |
| `craft://config/general` | Safe general config. |
| `craft://config/routes` | Custom routes. |
| `craft://config/sites` | Sites. |
| `craft://config/volumes` | Asset volumes. |
| `craft://config/plugins` | Installed plugins. |
| `craft://guides/content-writing` | How to write content through this server. |

Field URIs use the field handle. `title` is an entry attribute, so `craft://schema/fields/title` fails when no field has that handle. Use the real handle, such as `blogTitle`.
