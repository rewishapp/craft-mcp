<?php

namespace rewish\craft\mcp\elements;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\elements\User;
use craft\fields\BaseRelationField;
use craft\fields\Matrix;
use craft\models\EntryType;
use craft\models\Section;
use craft\models\Site;
use Mcp\Exception\ToolCallException;
use rewish\craft\mcp\Mcp;
use rewish\craft\mcp\support\CallContext;
use rewish\craft\mcp\support\PreviewLinks;

final class EntryWriter
{
    public function __construct(private readonly EntryReader $reader = new EntryReader()) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $section = $this->section((string) ($input['section'] ?? ''));
        $this->assertCan($section, true);
        $typeHandle = isset($input['type']) ? (string) $input['type'] : null;
        $type = $this->entryType($section, $typeHandle);
        $site = $this->site(isset($input['site']) ? (string) $input['site'] : null);

        $entry = new Entry();
        $entry->sectionId = $section->id;
        $entry->typeId = $type->id;
        $entry->siteId = $site->id;
        $entry->title = (string) ($input['title'] ?? 'Untitled');
        if (!empty($input['slug'])) {
            $entry->slug = (string) $input['slug'];
        }

        $fields = \is_array($input['fields'] ?? null) ? $input['fields'] : [];
        $this->applyFields($entry, $fields);

        $userId = CallContext::$userId;
        $draft = Mcp::settings()->entryWriteMode !== 'live' && ($input['draft'] ?? true) !== false;

        if ($draft) {
            $ok = \Craft::$app->getDrafts()->saveElementAsDraft($entry, $userId, 'MCP draft', 'Created by MCP - All in one');
        } else {
            $entry->enabled = (bool) ($input['enabled'] ?? true);
            $ok = \Craft::$app->getElements()->saveElement($entry);
        }

        if (!$ok) {
            throw new ToolCallException($this->errors($entry));
        }

        return $this->writeResult($entry);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function update(array $input): array
    {
        $entry = $this->find((int) ($input['id'] ?? 0), isset($input['site']) ? (string) $input['site'] : null);
        $section = $entry->getSection();
        if ($section === null) {
            throw new ToolCallException('That entry has no section.');
        }
        $this->assertCan($section, true);
        $this->assertFresh($entry, $input['expectedDateUpdated'] ?? null);

        $target = $this->editable($entry);
        if (\array_key_exists('title', $input)) {
            $target->title = (string) $input['title'];
        }
        if (\array_key_exists('slug', $input)) {
            $target->slug = (string) $input['slug'];
        }
        if (\is_array($input['fields'] ?? null)) {
            $this->applyFields($target, $input['fields']);
        }

        if (!\Craft::$app->getElements()->saveElement($target)) {
            throw new ToolCallException($this->errors($target));
        }

        return $this->writeResult($target);
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(int $id, ?string $site): array
    {
        $entry = $this->find($id, $site);
        $section = $entry->getSection();
        if ($section === null) {
            throw new ToolCallException('That entry has no section.');
        }
        $this->assertCan($section, true);

        if ($entry->getIsDraft()) {
            $applied = \Craft::$app->getDrafts()->applyDraft($entry);
            if (!$applied instanceof Entry) {
                throw new ToolCallException('Craft did not return the published entry.');
            }
            $entry = $applied;
        } else {
            $entry->enabled = true;
            if (!\Craft::$app->getElements()->saveElement($entry)) {
                throw new ToolCallException($this->errors($entry));
            }
        }

        return ['success' => true, 'entry' => $this->reader->summarize($entry, false)];
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(int $id, ?string $site): array
    {
        $entry = $this->find($id, $site);
        $section = $entry->getSection();
        if ($section === null) {
            throw new ToolCallException('That entry has no section.');
        }
        $this->assertCan($section, true);

        if (!\Craft::$app->getElements()->deleteElement($entry)) {
            throw new ToolCallException('Craft could not move that entry to the trash.');
        }

        return ['success' => true, 'id' => $id, 'trashed' => true];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public function duplicate(int $id, ?string $site, array $overrides): array
    {
        $entry = $this->find($id, $site);
        $canonical = $entry->getCanonical(true);
        if (!$canonical instanceof Entry) {
            throw new ToolCallException('Could not find the canonical entry to duplicate.');
        }
        $section = $canonical->getSection();
        if ($section === null) {
            throw new ToolCallException('That entry has no section.');
        }
        $this->assertCan($section, true);

        $copy = \Craft::$app->getElements()->duplicateElement($canonical, [], true, true);
        if (!$copy instanceof Entry) {
            throw new ToolCallException('Craft could not duplicate that entry.');
        }

        $copy->enabled = false;
        if (isset($overrides['title'])) {
            $copy->title = (string) $overrides['title'];
        }
        if (\is_array($overrides['fields'] ?? null)) {
            $this->applyFields($copy, $overrides['fields']);
        }
        if (!\Craft::$app->getElements()->saveElement($copy)) {
            throw new ToolCallException($this->errors($copy));
        }

        return $this->writeResult($copy);
    }

    /**
     * @return array<string, mixed>
     */
    public function copyToSite(int $id, string $siteHandle): array
    {
        $source = $this->find($id, null);
        $canonical = $source->getCanonical(true);
        if (!$canonical instanceof Entry) {
            throw new ToolCallException('Could not find the canonical entry.');
        }
        $section = $canonical->getSection();
        if ($section === null) {
            throw new ToolCallException('That entry has no section.');
        }
        $this->assertCan($section, true);
        $site = $this->site($siteHandle);

        $localized = Entry::find()->id($canonical->id)->siteId($site->id)->status(null)->drafts(false)->one();
        if (!$localized instanceof Entry) {
            \Craft::$app->getElements()->propagateElement($canonical, (int) $site->id);
            $localized = Entry::find()->id($canonical->id)->siteId($site->id)->status(null)->drafts(false)->one();
        }
        if (!$localized instanceof Entry) {
            throw new ToolCallException('That entry is not available on the target site.');
        }

        $draft = $this->editable($localized);
        $draft->title = $source->title;
        $this->applyFields($draft, $this->reader->fields($source, 0));
        if (!\Craft::$app->getElements()->saveElement($draft)) {
            throw new ToolCallException($this->errors($draft));
        }

        return $this->writeResult($draft);
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    public function addNested(int $ownerId, string $fieldHandle, string $typeHandle, array $fields, ?string $site): array
    {
        $owner = $this->find($ownerId, $site);
        $section = $owner->getSection();
        if ($section !== null) {
            $this->assertCan($section, true);
        }
        $target = $this->editable($owner);
        $field = $this->field($target, $fieldHandle);
        if (!$field instanceof Matrix && !$field instanceof BaseRelationField) {
            throw new ToolCallException("Field {$fieldHandle} does not accept nested entries.");
        }

        $type = null;
        if ($field instanceof Matrix) {
            foreach ($field->getEntryTypes() as $candidate) {
                if ($candidate->handle === $typeHandle) {
                    $type = $candidate;

                    break;
                }
            }
        }
        if ($type === null) {
            throw new ToolCallException("Unknown block type {$typeHandle}.");
        }

        $block = new Entry();
        $block->setPrimaryOwner($target);
        $block->fieldId = $field->id;
        $block->typeId = $type->id;
        $block->siteId = $target->siteId;
        $this->applyFields($block, $fields);
        if (!\Craft::$app->getElements()->saveElement($block)) {
            throw new ToolCallException($this->errors($block));
        }

        return [
            'success' => true,
            'ownerId' => $target->id,
            'previewUrl' => PreviewLinks::forEntry($target),
            'block' => [
                'id' => $block->id,
                'type' => $typeHandle,
                'cpEditUrl' => $target->getCpEditUrl(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function moveNested(int $blockId, int $position): array
    {
        $block = Entry::find()->id($blockId)->status(null)->drafts(null)->one();
        if (!$block instanceof Entry || !$block->getOwnerId()) {
            throw new ToolCallException('That id is not a nested entry.');
        }

        $owner = $block->getOwner();
        if ($owner instanceof Entry && $owner->getSection() !== null) {
            $this->assertCan($owner->getSection(), true);
        }

        $block->sortOrder = max(1, $position);
        if (!\Craft::$app->getElements()->saveElement($block)) {
            throw new ToolCallException($this->errors($block));
        }

        return [
            'success' => true,
            'id' => $block->id,
            'sortOrder' => $block->sortOrder,
            'previewUrl' => $owner instanceof Entry ? PreviewLinks::forEntry($owner) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function writeResult(Entry $entry): array
    {
        return [
            'success' => true,
            'draft' => $entry->getIsDraft(),
            'entry' => $this->reader->summarize($entry),
            'previewUrl' => PreviewLinks::forEntry($entry),
        ];
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function applyFields(Entry $entry, array $fields): void
    {
        $layout = $entry->getFieldLayout();
        if ($layout === null) {
            return;
        }

        $byHandle = [];
        foreach ($layout->getCustomFields() as $field) {
            $byHandle[$field->handle] = $field;
        }

        $values = [];
        foreach ($fields as $handle => $value) {
            if (!isset($byHandle[$handle])) {
                throw new ToolCallException("Unknown field {$handle}.");
            }
            $values[$handle] = $this->writeValue($byHandle[$handle], $value);
        }

        if ($values !== []) {
            $entry->setFieldValues($values);
        }
    }

    private function writeValue(FieldInterface $field, mixed $value): mixed
    {
        if ($field instanceof BaseRelationField && !$field instanceof Matrix) {
            return $this->relationIds($field, $value);
        }

        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if (\is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) && str_contains($field::class, 'Date')) {
            return new \DateTimeImmutable($value);
        }

        return $value;
    }

    /**
     * @return list<int>
     */
    private function relationIds(BaseRelationField $field, mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $items = \is_array($value) && array_is_list($value) ? $value : [$value];
        $ids = [];
        foreach ($items as $item) {
            if (\is_int($item) || (\is_string($item) && ctype_digit($item))) {
                $ids[] = (int) $item;

                continue;
            }
            if (!\is_array($item)) {
                throw new ToolCallException('Relation values must be an id or a natural key.');
            }
            $ids[] = $this->resolveKey($item);
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $key
     */
    private function resolveKey(array $key): int
    {
        if (isset($key['id']) && is_numeric($key['id'])) {
            return (int) $key['id'];
        }

        if (isset($key['section'], $key['slug'])) {
            $entry = Entry::find()->section((string) $key['section'])->slug((string) $key['slug'])->status(null)->one();
            if ($entry instanceof Entry && $entry->id) {
                return (int) $entry->id;
            }
        }

        if (isset($key['volume'], $key['filename'])) {
            $asset = Asset::find()->volume((string) $key['volume'])->filename((string) $key['filename'])->one();
            if ($asset instanceof Asset && $asset->id) {
                return (int) $asset->id;
            }
        }

        if (isset($key['group'], $key['slug'])) {
            $category = Category::find()->group((string) $key['group'])->slug((string) $key['slug'])->one();
            if ($category instanceof Category && $category->id) {
                return (int) $category->id;
            }
        }

        if (isset($key['username'])) {
            $user = User::find()->username((string) $key['username'])->one();
            if ($user instanceof User && $user->id) {
                return (int) $user->id;
            }
        }

        if (isset($key['group'], $key['title'])) {
            $tag = Tag::find()->group((string) $key['group'])->title((string) $key['title'])->one();
            if ($tag instanceof Tag && $tag->id) {
                return (int) $tag->id;
            }
        }

        throw new ToolCallException('Could not resolve a relation from that natural key.');
    }

    private function editable(Entry $entry): Entry
    {
        if ($entry->getIsDraft() || Mcp::settings()->entryWriteMode === 'live') {
            return $entry;
        }

        $canonical = $entry->getCanonical(true);
        if (!$canonical instanceof Entry) {
            return $entry;
        }

        $draft = \Craft::$app->getDrafts()->createDraft($canonical, CallContext::$userId, 'MCP draft', 'Updated by MCP - All in one');
        if (!$draft instanceof Entry) {
            throw new ToolCallException('Craft could not open a draft for that entry.');
        }

        return $draft;
    }

    private function find(int $id, ?string $site): Entry
    {
        if ($id < 1) {
            throw new ToolCallException('An entry id is required.');
        }

        $query = Entry::find()->id($id)->status(null)->drafts(null);
        if ($site !== null && $site !== '') {
            $query->site($site);
        }

        $entry = $query->one();
        if (!$entry instanceof Entry) {
            throw new ToolCallException("No entry found for id {$id}.");
        }

        return $entry;
    }

    private function section(string $handle): Section
    {
        if ($handle === '') {
            throw new ToolCallException('A section handle is required.');
        }

        $section = \Craft::$app->getEntries()->getSectionByHandle($handle);
        if ($section === null) {
            throw new ToolCallException("Unknown section {$handle}.");
        }

        return $section;
    }

    private function entryType(Section $section, ?string $handle): EntryType
    {
        $types = $section->getEntryTypes();
        if ($handle === null || $handle === '') {
            if (\count($types) === 1) {
                return $types[0];
            }

            throw new ToolCallException('This section has more than one entry type. Pass type.');
        }

        foreach ($types as $type) {
            if ($type->handle === $handle) {
                return $type;
            }
        }

        throw new ToolCallException("Unknown entry type {$handle}.");
    }

    private function site(?string $handle): Site
    {
        $sites = \Craft::$app->getSites();
        if ($handle === null || $handle === '') {
            return $sites->getCurrentSite();
        }

        $site = $sites->getSiteByHandle($handle);
        if ($site === null) {
            throw new ToolCallException("Unknown site {$handle}.");
        }

        return $site;
    }

    private function field(Entry $entry, string $handle): FieldInterface
    {
        $layout = $entry->getFieldLayout();
        $field = $layout?->getFieldByHandle($handle);
        if (!$field instanceof FieldInterface) {
            throw new ToolCallException("Unknown field {$handle}.");
        }

        return $field;
    }

    private function assertCan(Section $section, bool $save): void
    {
        if (!CallContext::allowsSection($section->handle)) {
            throw new ToolCallException("This token cannot access the {$section->handle} section.");
        }

        $user = CallContext::$userId ? \Craft::$app->getUsers()->getUserById(CallContext::$userId) : null;
        if ($user === null) {
            return;
        }

        $permission = ($save ? 'saveEntries:' : 'viewEntries:').$section->uid;
        if (!$user->can($permission)) {
            throw new ToolCallException('This token’s user does not have permission for that section.');
        }
    }

    private function assertFresh(Entry $entry, mixed $expected): void
    {
        if (!\is_string($expected) || $expected === '' || !$entry->dateUpdated instanceof \DateTimeInterface) {
            return;
        }

        $actual = $entry->dateUpdated->format(\DATE_ATOM);
        if ($actual !== $expected && $entry->dateUpdated->format('Y-m-d H:i:s') !== $expected) {
            throw new ToolCallException("The entry changed since you read it. Current dateUpdated is {$actual}.");
        }
    }

    private function errors(ElementInterface $element): string
    {
        $messages = [];
        foreach ($element->getErrors() as $errors) {
            foreach ((array) $errors as $error) {
                $messages[] = (string) $error;
            }
        }

        return $messages === [] ? 'Craft rejected the save.' : implode(' ', $messages);
    }
}
