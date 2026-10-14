<?php

namespace rewish\craft\mcp\resources;

use craft\elements\Entry;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Exception\ResourceReadException;

final class CraftResources
{
    #[McpResource(uri: 'craft://schema/sections', name: 'all-sections', description: 'All sections.', mimeType: 'application/json')]
    public function allSections(): array
    {
        $sections = [];
        foreach (\Craft::$app->getEntries()->getAllSections() as $section) {
            $sections[] = ['handle' => $section->handle, 'name' => $section->name, 'type' => $section->type];
        }

        return ['sections' => $sections];
    }

    #[McpResource(uri: 'craft://schema/fields', name: 'all-fields', description: 'All custom fields.', mimeType: 'application/json')]
    public function allFields(): array
    {
        $fields = [];
        foreach (\Craft::$app->getFields()->getAllFields() as $field) {
            $fields[] = ['handle' => $field->handle, 'name' => $field->name, 'type' => substr($field::class, strrpos($field::class, '\\') + 1)];
        }

        return ['fields' => $fields];
    }

    #[McpResourceTemplate(uriTemplate: 'craft://schema/sections/{handle}', name: 'section-schema', description: 'One section, its entry types, and fields.', mimeType: 'application/json')]
    public function sectionSchema(string $handle): array
    {
        $section = \Craft::$app->getEntries()->getSectionByHandle($handle);
        if ($section === null) {
            throw new ResourceReadException("Unknown section {$handle}.");
        }
        $types = [];
        foreach ($section->getEntryTypes() as $type) {
            $fields = [];
            $layout = $type->getFieldLayout();
            foreach ($layout?->getCustomFields() ?? [] as $field) {
                $fields[] = ['handle' => $field->handle, 'name' => $field->name, 'required' => (bool) $field->required];
            }
            $types[] = ['handle' => $type->handle, 'name' => $type->name, 'fields' => $fields];
        }

        return ['section' => $section->handle, 'entryTypes' => $types];
    }

    #[McpResourceTemplate(uriTemplate: 'craft://schema/fields/{handle}', name: 'field-details', description: 'One field and the sections that use it.', mimeType: 'application/json')]
    public function fieldDetails(string $handle): array
    {
        $field = \Craft::$app->getFields()->getFieldByHandle($handle);
        if ($field === null) {
            throw new ResourceReadException("Unknown field {$handle}.");
        }
        $usedBy = [];
        foreach (\Craft::$app->getEntries()->getAllSections() as $section) {
            foreach ($section->getEntryTypes() as $type) {
                if ($type->getFieldLayout()?->getFieldByHandle($handle) !== null) {
                    $usedBy[] = $section->handle.'/'.$type->handle;
                }
            }
        }

        return [
            'handle' => $field->handle,
            'name' => $field->name,
            'type' => substr($field::class, strrpos($field::class, '\\') + 1),
            'usedBy' => $usedBy,
        ];
    }

    #[McpResource(uri: 'craft://config/general', name: 'general-config', description: 'Safe general config.', mimeType: 'application/json')]
    public function generalConfig(): array
    {
        $general = \Craft::$app->getConfig()->getGeneral();

        return [
            'devMode' => (bool) $general->devMode,
            'allowAdminChanges' => (bool) $general->allowAdminChanges,
            'timezone' => $general->timezone,
        ];
    }

    #[McpResource(uri: 'craft://config/routes', name: 'routes-config', description: 'Custom routes.', mimeType: 'application/json')]
    public function routesConfig(): array
    {
        return ['routes' => array_values(\Craft::$app->getRoutes()->getProjectConfigRoutes())];
    }

    #[McpResource(uri: 'craft://config/sites', name: 'sites-config', description: 'Sites.', mimeType: 'application/json')]
    public function sitesConfig(): array
    {
        $sites = [];
        foreach (\Craft::$app->getSites()->getAllSites() as $site) {
            $sites[] = ['handle' => $site->handle, 'language' => $site->language, 'baseUrl' => $site->getBaseUrl()];
        }

        return ['sites' => $sites];
    }

    #[McpResource(uri: 'craft://config/volumes', name: 'volumes-config', description: 'Asset volumes.', mimeType: 'application/json')]
    public function volumesConfig(): array
    {
        $volumes = [];
        foreach (\Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            $volumes[] = ['handle' => $volume->handle, 'name' => $volume->name];
        }

        return ['volumes' => $volumes];
    }

    #[McpResource(uri: 'craft://config/plugins', name: 'installed-plugins', description: 'Installed plugins.', mimeType: 'application/json')]
    public function pluginsConfig(): array
    {
        $plugins = [];
        foreach (\Craft::$app->getPlugins()->getAllPluginInfo() as $handle => $info) {
            $plugins[] = ['handle' => $handle, 'version' => $info['version'] ?? null, 'enabled' => (bool) ($info['isEnabled'] ?? false)];
        }

        return ['plugins' => $plugins];
    }

    #[McpResourceTemplate(uriTemplate: 'craft://entries/{section}', name: 'section-entries', description: 'Up to 50 entries in a section.', mimeType: 'application/json')]
    public function sectionEntries(string $section): array
    {
        $entries = Entry::find()->section($section)->limit(50)->all();
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = ['id' => $entry->id, 'title' => $entry->title, 'slug' => $entry->slug, 'status' => $entry->getStatus()];
        }

        return ['section' => $section, 'count' => \count($rows), 'entries' => $rows];
    }

    #[McpResourceTemplate(uriTemplate: 'craft://entries/{section}/stats', name: 'section-stats', description: 'Status counts for a section.', mimeType: 'application/json')]
    public function sectionStats(string $section): array
    {
        $stats = [];
        foreach (['live', 'pending', 'expired', 'disabled'] as $status) {
            $stats[$status] = (int) Entry::find()->section($section)->status($status)->count();
        }

        return ['section' => $section, 'stats' => $stats];
    }

    #[McpResourceTemplate(uriTemplate: 'craft://entries/{section}/{slug}', name: 'entry-by-slug', description: 'A light entry summary.', mimeType: 'application/json')]
    public function entryBySlug(string $section, string $slug): array
    {
        $entry = Entry::find()->section($section)->slug($slug)->one();
        if (!$entry instanceof Entry) {
            throw new ResourceReadException("No {$section} entry with slug {$slug}.");
        }

        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'status' => $entry->getStatus(),
            'cpEditUrl' => $entry->getCpEditUrl(),
        ];
    }

    #[McpResource(uri: 'craft://guides/content-writing', name: 'content-writing-guide', description: 'How to write Craft content through this server.', mimeType: 'text/plain')]
    public function writingGuide(): string
    {
        return <<<'TEXT'
            Writes default to reviewable drafts. Read an entry with get_entry, change fields, and send them back to update_entry.
            Relations use natural keys: {section, slug}, {volume, filename}, {group, slug}, or {username}. Ids are also accepted.
            Matrix blocks are added with create_nested_entry and moved with move_nested_entry.
            Call describe_entry_schema before writing a section you have not seen.
            publish_entry applies a draft. delete_entry moves an entry to the trash.
            TEXT;
    }
}
