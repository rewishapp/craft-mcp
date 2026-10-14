<?php

namespace rewish\craft\mcp\tools;

use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\elements\User;
use craft\models\Section;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\elements\EntryReader;
use rewish\craft\mcp\elements\EntryWriter;
use rewish\craft\mcp\Mcp;
use rewish\craft\mcp\services\Activity;
use rewish\craft\mcp\support\CallContext;
use rewish\craft\mcp\support\Page;

final class EntryTools
{
    public function __construct(
        private readonly EntryReader $reader = new EntryReader(),
        private readonly EntryWriter $writer = new EntryWriter(),
    ) {}

    #[McpTool(name: 'list_entries', description: 'List entries. Relations come back as natural keys. Filter by section, type, status, site, search, slug, or author username.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'content')]
    public function listEntries(
        ?string $section = null,
        ?string $type = null,
        ?string $status = null,
        ?string $site = null,
        ?string $search = null,
        ?string $slug = null,
        ?string $author = null,
        int $limit = 25,
        int $offset = 0,
    ): array {
        return Activity::run('list_entries', function () use ($section, $type, $status, $site, $search, $slug, $author, $limit, $offset) {
            $this->guardSection($section);
            $limit = Page::limit($limit);
            $offset = Page::offset($offset);
            $query = $this->query($section, $type, $status, $site, $search, $slug, $author);
            $total = (int) (clone $query)->count();
            $entries = $query->limit($limit)->offset($offset)->all();

            return Page::make(array_map(fn (Entry $entry) => $this->reader->summarize($entry), $entries), $total, $limit, $offset, 'entries');
        });
    }

    #[McpTool(name: 'count_entries', description: 'Count entries with the same filters as list_entries. Optional groupBy: status, section, type, or site.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'content')]
    public function countEntries(
        ?string $section = null,
        ?string $type = null,
        ?string $status = null,
        ?string $site = null,
        ?string $search = null,
        ?string $groupBy = null,
    ): array {
        return Activity::run('count_entries', function () use ($section, $type, $status, $site, $search, $groupBy) {
            $this->guardSection($section);
            $query = $this->query($section, $type, $status, $site, $search, null, null);
            if ($groupBy === null || $groupBy === '') {
                return ['total' => (int) $query->count()];
            }

            $groups = [];
            foreach ($query->limit(1000)->all() as $entry) {
                if (!$entry instanceof Entry) {
                    continue;
                }
                $key = match ($groupBy) {
                    'status' => (string) $entry->getStatus(),
                    'section' => (string) $entry->getSection()?->handle,
                    'type' => (string) $entry->getType()?->handle,
                    'site' => (string) $entry->getSite()?->handle,
                    default => throw new ToolCallException('groupBy must be status, section, type, or site.'),
                };
                $groups[$key] = ($groups[$key] ?? 0) + 1;
            }

            return ['groupBy' => $groupBy, 'groups' => $groups, 'total' => array_sum($groups)];
        });
    }

    #[McpTool(name: 'get_entry', description: 'Get one entry by id or by section + slug, in the same field shape create_entry and update_entry accept.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'content')]
    public function getEntry(?int $id = null, ?string $section = null, ?string $slug = null, ?string $site = null): array
    {
        return Activity::run('get_entry', function () use ($id, $section, $slug, $site) {
            $entry = $this->one($id, $section, $slug, $site);

            return ['found' => true, 'entry' => $this->reader->summarize($entry)];
        });
    }

    #[McpTool(name: 'describe_entry_schema', description: 'Describe a section entry type: each field handle, kind, and the input shape create_entry expects.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'schema')]
    public function describeEntrySchema(string $section, ?string $type = null): array
    {
        return Activity::run('describe_entry_schema', function () use ($section, $type) {
            $this->guardSection($section);
            $model = \Craft::$app->getEntries()->getSectionByHandle($section);
            if (!$model instanceof Section) {
                throw new ToolCallException("Unknown section {$section}.");
            }

            $types = [];
            foreach ($model->getEntryTypes() as $entryType) {
                if ($type !== null && $type !== '' && $entryType->handle !== $type) {
                    continue;
                }
                $fields = [];
                $layout = $entryType->getFieldLayout();
                foreach ($layout?->getCustomFields() ?? [] as $field) {
                    $fields[] = [
                        'handle' => $field->handle,
                        'name' => $field->name,
                        'kind' => $this->kind($field::class),
                        'required' => $field->required,
                        'instructions' => $field->instructions ?: null,
                        'input' => $this->inputHint($field::class),
                    ];
                }
                $types[] = [
                    'handle' => $entryType->handle,
                    'name' => $entryType->name,
                    'hasTitleField' => $entryType->hasTitleField,
                    'fields' => $fields,
                ];
            }

            return [
                'section' => $model->handle,
                'entryTypes' => $types,
                'writeMode' => Mcp::settings()->entryWriteMode,
                'notes' => 'Relations accept an id or a natural key such as {section, slug}, {volume, filename}, {group, slug}, or {username}. Matrix blocks are edited with create_nested_entry and move_nested_entry.',
            ];
        });
    }

    #[McpTool(name: 'list_drafts', description: 'List unpublished drafts, newest first, with control panel links.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'content')]
    public function listDrafts(?string $section = null, int $limit = 25): array
    {
        return Activity::run('list_drafts', function () use ($section, $limit) {
            $this->guardSection($section);
            $query = Entry::find()->drafts()->status(null)->orderBy(['dateUpdated' => \SORT_DESC]);
            if ($section) {
                $query->section($section);
            }
            $limit = Page::limit($limit);
            $total = (int) (clone $query)->count();
            $entries = $query->limit($limit)->all();

            return Page::make(array_map(fn (Entry $entry) => $this->reader->summarize($entry, false), $entries), $total, $limit, 0, 'drafts');
        });
    }

    #[McpTool(name: 'list_revisions', description: 'List saved revisions for an entry, newest first.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'content')]
    public function listRevisions(int $id, int $limit = 20): array
    {
        return Activity::run('list_revisions', function () use ($id, $limit) {
            $entry = $this->one($id, null, null, null);
            $canonicalId = $entry->getCanonicalId();
            $query = Entry::find()->revisionOf($canonicalId)->status(null)->orderBy(['dateCreated' => \SORT_DESC]);
            $limit = Page::limit($limit, 50);
            $revisions = [];
            foreach ($query->limit($limit)->all() as $revision) {
                if (!$revision instanceof Entry) {
                    continue;
                }
                $revisions[] = [
                    'id' => $revision->id,
                    'revisionId' => $revision->revisionId,
                    'title' => $revision->title,
                    'dateCreated' => $revision->dateCreated?->format(\DATE_ATOM),
                    'revisionNotes' => $revision->revisionNotes ?? null,
                ];
            }

            return ['count' => \count($revisions), 'revisions' => $revisions];
        });
    }

    #[McpTool(name: 'create_entry', description: 'Create an entry. Saves a reviewable draft unless entry write mode is live. Pass section, optional type, title, slug, site, and fields.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function createEntry(string $section, ?string $type = null, ?string $title = null, ?string $slug = null, ?string $site = null, #[Schema(type: 'object', additionalProperties: true)] ?array $fields = null, ?bool $draft = null): array
    {
        return Activity::run('create_entry', fn () => $this->writer->create([
            'section' => $section,
            'type' => $type,
            'title' => $title,
            'slug' => $slug,
            'site' => $site,
            'fields' => $fields ?? [],
            'draft' => $draft,
        ]));
    }

    #[McpTool(name: 'update_entry', description: 'Update an entry by id. A live entry is edited on a new draft unless write mode is live. Pass expectedDateUpdated to refuse a stale write.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function updateEntry(int $id, ?string $title = null, ?string $slug = null, ?string $site = null, #[Schema(type: 'object', additionalProperties: true)] ?array $fields = null, ?string $expectedDateUpdated = null): array
    {
        return Activity::run('update_entry', fn () => $this->writer->update([
            'id' => $id,
            'title' => $title,
            'slug' => $slug,
            'site' => $site,
            'fields' => $fields,
            'expectedDateUpdated' => $expectedDateUpdated,
        ]));
    }

    #[McpTool(name: 'publish_entry', description: 'Apply a draft to its canonical entry, or enable a disabled entry.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function publishEntry(int $id, ?string $site = null): array
    {
        return Activity::run('publish_entry', fn () => $this->writer->publish($id, $site));
    }

    #[McpTool(name: 'delete_entry', description: 'Move an entry to the trash. It can be restored from the control panel.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function deleteEntry(int $id, ?string $site = null): array
    {
        return Activity::run('delete_entry', fn () => $this->writer->delete($id, $site));
    }

    #[McpTool(name: 'duplicate_entry', description: 'Duplicate an entry as an unpublished draft you can review before publishing.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function duplicateEntry(int $id, ?string $site = null, ?string $title = null, #[Schema(type: 'object', additionalProperties: true)] ?array $fields = null): array
    {
        return Activity::run('duplicate_entry', fn () => $this->writer->duplicate($id, $site, array_filter([
            'title' => $title,
            'fields' => $fields,
        ], static fn ($value) => $value !== null)));
    }

    #[McpTool(name: 'copy_entry_to_site', description: 'Copy an entry’s field values onto another site as a draft. This does not machine-translate.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function copyEntryToSite(int $id, string $site): array
    {
        return Activity::run('copy_entry_to_site', fn () => $this->writer->copyToSite($id, $site));
    }

    #[McpTool(name: 'create_nested_entry', description: 'Add one Matrix block to an entry field. The owner is drafted first when write mode is draft.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function createNestedEntry(int $ownerId, string $field, string $type, #[Schema(type: 'object', additionalProperties: true)] ?array $fields = null, ?string $site = null): array
    {
        return Activity::run('create_nested_entry', fn () => $this->writer->addNested($ownerId, $field, $type, $fields ?? [], $site));
    }

    #[McpTool(name: 'move_nested_entry', description: 'Move one Matrix block to a 1-based sort position.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function moveNestedEntry(int $id, int $position): array
    {
        return Activity::run('move_nested_entry', fn () => $this->writer->moveNested($id, $position));
    }

    private function query(?string $section, ?string $type, ?string $status, ?string $site, ?string $search, ?string $slug, ?string $author): EntryQuery
    {
        $query = Entry::find()->drafts(false)->orderBy(['dateUpdated' => \SORT_DESC]);
        if ($section) {
            $query->section($section);
        } elseif (\is_array(CallContext::$sections) && CallContext::$sections !== []) {
            $query->section(CallContext::$sections);
        }
        if ($type) {
            $query->type($type);
        }
        if ($status) {
            $query->status($status);
        }
        if ($site) {
            $query->site($site);
        }
        if ($search) {
            $query->search($search);
        }
        if ($slug) {
            $query->slug($slug);
        }
        if ($author) {
            $user = User::find()->username($author)->one();
            $query->authorId($user?->id ?? 0);
        }

        return $query;
    }

    private function one(?int $id, ?string $section, ?string $slug, ?string $site): Entry
    {
        $query = Entry::find()->status(null)->drafts(null);
        if ($id) {
            $query->id($id);
        } elseif ($section && $slug) {
            $this->guardSection($section);
            $query->section($section)->slug($slug);
        } else {
            throw new ToolCallException('Pass id, or section and slug.');
        }
        if ($site) {
            $query->site($site);
        }

        $entry = $query->one();
        if (!$entry instanceof Entry) {
            throw new ToolCallException('No entry matched.');
        }
        $this->guardSection($entry->getSection()?->handle);

        return $entry;
    }

    private function guardSection(?string $handle): void
    {
        if (!CallContext::allowsSection($handle)) {
            throw new ToolCallException('This token cannot access that section.');
        }
    }

    private function kind(string $class): string
    {
        return match (true) {
            str_contains($class, 'PlainText') => 'text',
            str_contains($class, 'Matrix') => 'matrix',
            str_contains($class, 'Assets') => 'assets',
            str_contains($class, 'Entries') => 'entries',
            str_contains($class, 'Categories') => 'categories',
            str_contains($class, 'Tags') => 'tags',
            str_contains($class, 'Users') => 'users',
            str_contains($class, 'Lightswitch') => 'boolean',
            str_contains($class, 'Number') => 'number',
            str_contains($class, 'Date') => 'date',
            str_contains($class, 'Dropdown'), str_contains($class, 'RadioButtons') => 'option',
            str_contains($class, 'Checkboxes'), str_contains($class, 'MultiSelect') => 'options',
            str_contains($class, 'Table') => 'table',
            str_contains($class, 'Link') => 'link',
            default => substr($class, strrpos($class, '\\') + 1),
        };
    }

    private function inputHint(string $class): string
    {
        return match ($this->kind($class)) {
            'text', 'number', 'link' => 'string',
            'boolean' => 'true or false',
            'date' => 'ISO-8601 datetime',
            'option' => 'one of the field’s option values',
            'options' => 'array of option values',
            'entries' => '{section, slug} or an id',
            'assets' => '{volume, filename} or an id',
            'categories' => '{group, slug} or an id',
            'users' => '{username} or an id',
            'tags' => '{group, title} or an id',
            'matrix' => 'use create_nested_entry with a block type handle',
            'table' => 'array of row objects',
            default => 'the value Craft stores for this field',
        };
    }
}
