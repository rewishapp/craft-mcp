<?php

namespace rewish\craft\mcp\prompts;

use Mcp\Capability\Attribute\McpPrompt;

final class GuidePrompts
{
    #[McpPrompt(name: 'content_health_analysis', description: 'Summarize entry counts, drafts, and how fresh each section is.')]
    public function contentHealth(?string $section = null): array
    {
        return $this->messages('Review content health'.($section ? " for the {$section} section" : '').'. Call count_entries grouped by status and section, then list_drafts. Report stale sections and how many drafts are waiting.');
    }

    #[McpPrompt(name: 'content_audit', description: 'Audit structure, required fields, and obvious content gaps.')]
    public function contentAudit(string $section): array
    {
        return $this->messages("Audit the {$section} section. Call describe_entry_schema, then list_entries with a small limit. Note empty required-looking fields and inconsistent titles.");
    }

    #[McpPrompt(name: 'debug_content_issue', description: 'Debug a content problem with schema, the entry payload, and recent errors.')]
    public function debugContent(string $section, ?string $slug = null): array
    {
        $target = $slug ? "the {$section}/{$slug} entry" : "the {$section} section";

        return $this->messages("Debug {$target}. Read describe_entry_schema and get_entry. If the install allows it, also call get_last_error. Explain the likely cause before changing anything.");
    }

    #[McpPrompt(name: 'create_entry_guide', description: 'Explain how to create a draft in a section.')]
    public function createGuide(string $section): array
    {
        return $this->messages("Explain how to create a {$section} entry. Call describe_entry_schema first. Writes land as drafts unless write mode is live. Show a sample create_entry payload using natural keys for relations.");
    }

    #[McpPrompt(name: 'query_entries_guide', description: 'Show the efficient way to query a section.')]
    public function queryGuide(string $section): array
    {
        return $this->messages("Show how to query the {$section} section with list_entries and count_entries. Prefer filters over loading every entry.");
    }

    #[McpPrompt(name: 'bulk_entry_operations', description: 'Plan a safe bulk update that stays in drafts.')]
    public function bulkGuide(string $section): array
    {
        return $this->messages("Plan a bulk update for {$section}. List matching entries, then update_entry one at a time so each change is a reviewable draft. Do not publish unless asked.");
    }

    #[McpPrompt(name: 'review_pending_drafts', description: 'Walk the pending draft queue.')]
    public function reviewDrafts(): array
    {
        return $this->messages('Call list_drafts and summarize each draft: section, title, author, and control panel link. Do not publish unless asked.');
    }

    #[McpPrompt(name: 'explore_section_schema', description: 'Walk a section, its entry types, and fields.')]
    public function exploreSection(string $section): array
    {
        return $this->messages("Explore the {$section} schema with list_sections and describe_entry_schema. Explain each field kind in plain language.");
    }

    #[McpPrompt(name: 'field_usage_analysis', description: 'Find where a field handle is used.')]
    public function fieldUsage(string $handle): array
    {
        return $this->messages("Find where the {$handle} field is used. Call list_fields and describe_entry_schema for the sections that include it.");
    }

    #[McpPrompt(name: 'explore_content_model', description: 'Give an overview of the whole content model.')]
    public function exploreModel(): array
    {
        $craft = \Craft::$app->getVersion();

        return $this->messages("Give a content-model overview of this Craft {$craft} install. Call list_sections, list_fields, list_volumes, and list_sites. Keep it short enough to scan.");
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function messages(string $content): array
    {
        return [['role' => 'user', 'content' => $content]];
    }
}
