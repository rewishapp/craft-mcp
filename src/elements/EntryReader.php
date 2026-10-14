<?php

namespace rewish\craft\mcp\elements;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\elements\User;
use craft\fields\BaseRelationField;
use craft\fields\Matrix;
use rewish\craft\mcp\events\FieldTranslatorsEvent;
use rewish\craft\mcp\Mcp;

final class EntryReader
{
    /** @var null|array<class-string, callable> */
    private ?array $translators = null;

    /**
     * @return array<string, mixed>
     */
    public function summarize(Entry $entry, bool $withFields = true): array
    {
        $section = $entry->getSection();
        $type = $entry->getType();
        $site = $entry->getSite();
        $author = $entry->getAuthor();

        $payload = [
            'id' => $entry->id,
            'uid' => $entry->uid,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'section' => $section?->handle,
            'type' => $type?->handle,
            'site' => $site?->handle,
            'status' => $entry->getStatus(),
            'enabled' => (bool) $entry->enabled,
            'draft' => $entry->getIsDraft(),
            'draftId' => $entry->draftId,
            'url' => $entry->getUrl(),
            'cpEditUrl' => $entry->getCpEditUrl(),
            'postDate' => $this->date($entry->postDate),
            'dateUpdated' => $this->date($entry->dateUpdated),
            'author' => $author ? ['id' => $author->id, 'username' => $author->username] : null,
        ];

        if ($withFields) {
            $payload['fields'] = $this->fields($entry, 0);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(Entry $entry, int $depth): array
    {
        $layout = $entry->getFieldLayout();
        if ($layout === null) {
            return [];
        }

        $values = [];
        foreach ($layout->getCustomFields() as $field) {
            $values[$field->handle] = $this->value($field, $entry->getFieldValue($field->handle), $depth);
        }

        return $values;
    }

    public function value(FieldInterface $field, mixed $raw, int $depth): mixed
    {
        $translator = $this->translators()[$field::class] ?? null;
        if ($translator !== null) {
            return $translator($field, $raw, 'read');
        }

        if ($depth > 3) {
            return null;
        }

        if ($raw instanceof ElementQuery) {
            $raw = $raw->all();
        }

        if ($field instanceof Matrix || ($field instanceof BaseRelationField && $this->isNestedEntryField($field))) {
            $blocks = \is_array($raw) ? $raw : [];

            return array_map(fn (Entry $block) => $this->block($block, $depth + 1), array_filter($blocks, static fn ($b) => $b instanceof Entry));
        }

        if ($field instanceof BaseRelationField) {
            $elements = \is_array($raw) ? $raw : [];

            return array_map(fn (ElementInterface $element) => $this->naturalKey($element), array_filter($elements, static fn ($e) => $e instanceof ElementInterface));
        }

        return $this->scalarish($raw, $depth);
    }

    /**
     * @return array<string, mixed>
     */
    public function naturalKey(ElementInterface $element): array
    {
        $key = ['id' => $element->id];

        if ($element instanceof Entry) {
            $key['section'] = $element->getSection()?->handle;
            $key['slug'] = $element->slug;
            $key['title'] = $element->title;
            $key['site'] = $element->getSite()?->handle;
        } elseif ($element instanceof Asset) {
            $key['volume'] = $element->getVolume()?->handle;
            $key['filename'] = $element->getFilename();
            $key['title'] = $element->title;
        } elseif ($element instanceof Category) {
            $key['group'] = $element->getGroup()?->handle;
            $key['slug'] = $element->slug;
            $key['title'] = $element->title;
        } elseif ($element instanceof User) {
            $key['username'] = $element->username;
            $key['fullName'] = $element->fullName;
        } elseif ($element instanceof Tag) {
            $key['group'] = $element->getGroup()?->handle;
            $key['title'] = $element->title;
        } else {
            $key['title'] = $element->title ?? null;
        }

        return $key;
    }

    /**
     * @return array<string, mixed>
     */
    private function block(Entry $entry, int $depth): array
    {
        return [
            'id' => $entry->id,
            'type' => $entry->getType()?->handle,
            'title' => $entry->title,
            'enabled' => (bool) $entry->enabled,
            'sortOrder' => $entry->sortOrder,
            'fields' => $this->fields($entry, $depth),
        ];
    }

    private function scalarish(mixed $raw, int $depth): mixed
    {
        if ($raw === null || \is_scalar($raw)) {
            return $raw;
        }

        if ($raw instanceof \DateTimeInterface) {
            return $raw->format(\DATE_ATOM);
        }

        if ($raw instanceof ElementInterface) {
            return $this->naturalKey($raw);
        }

        if (\is_array($raw)) {
            return array_map(fn ($item) => $this->scalarish($item, $depth + 1), $raw);
        }

        if ($raw instanceof \JsonSerializable) {
            return $raw->jsonSerialize();
        }

        if (\is_object($raw) && method_exists($raw, '__toString')) {
            return (string) $raw;
        }

        return null;
    }

    private function date(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format(\DATE_ATOM) : null;
    }

    private function isNestedEntryField(BaseRelationField $field): bool
    {
        return $field instanceof Matrix;
    }

    /**
     * @return array<class-string, callable>
     */
    private function translators(): array
    {
        if ($this->translators !== null) {
            return $this->translators;
        }

        $plugin = Mcp::getInstance();
        $event = new FieldTranslatorsEvent();
        if ($plugin !== null && $plugin->hasEventHandlers(Mcp::EVENT_REGISTER_FIELD_TRANSLATORS)) {
            $plugin->trigger(Mcp::EVENT_REGISTER_FIELD_TRANSLATORS, $event);
        }

        $this->translators = $event->translators;

        return $this->translators;
    }
}
