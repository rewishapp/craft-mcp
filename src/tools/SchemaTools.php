<?php

namespace rewish\craft\mcp\tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\services\Activity;

final class SchemaTools
{
    #[McpTool(name: 'list_sections', description: 'List sections with their entry types and URI format.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'schema')]
    public function listSections(): array
    {
        return Activity::run('list_sections', static function () {
            $sections = [];
            foreach (\Craft::$app->getEntries()->getAllSections() as $section) {
                $types = [];
                foreach ($section->getEntryTypes() as $type) {
                    $types[] = ['handle' => $type->handle, 'name' => $type->name];
                }
                $sections[] = [
                    'handle' => $section->handle,
                    'name' => $section->name,
                    'type' => $section->type,
                    'entryTypes' => $types,
                ];
            }

            return ['count' => \count($sections), 'sections' => $sections];
        });
    }

    #[McpTool(name: 'list_fields', description: 'List custom fields with their type and group.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'schema')]
    public function listFields(): array
    {
        return Activity::run('list_fields', static function () {
            $fields = [];
            foreach (\Craft::$app->getFields()->getAllFields() as $field) {
                $fields[] = [
                    'handle' => $field->handle,
                    'name' => $field->name,
                    'type' => substr($field::class, strrpos($field::class, '\\') + 1),
                    'required' => (bool) $field->required,
                    'searchable' => (bool) ($field->searchable ?? false),
                ];
            }

            return ['count' => \count($fields), 'fields' => $fields];
        });
    }

    #[McpTool(name: 'list_plugins', description: 'List installed plugins with handle, name, version, and enabled state.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'schema')]
    public function listPlugins(): array
    {
        return Activity::run('list_plugins', static function () {
            $plugins = [];
            foreach (\Craft::$app->getPlugins()->getAllPluginInfo() as $handle => $info) {
                $plugins[] = [
                    'handle' => $handle,
                    'name' => $info['name'] ?? $handle,
                    'version' => $info['version'] ?? null,
                    'enabled' => (bool) ($info['isEnabled'] ?? false),
                ];
            }

            return ['count' => \count($plugins), 'plugins' => $plugins];
        });
    }
}
