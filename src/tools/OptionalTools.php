<?php

namespace rewish\craft\mcp\tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\services\Activity;
use rewish\craft\mcp\support\Page;
use Solspace\Calendar\Elements\Event;
use Solspace\Freeform\Elements\Submission;

final class OptionalTools
{
    public static function isAvailable(): bool
    {
        return \Craft::$app->getPlugins()->isPluginInstalled('freeform')
            || \Craft::$app->getPlugins()->isPluginInstalled('calendar');
    }

    #[McpTool(name: 'list_freeform_submissions', description: 'List recent Freeform submissions when Freeform is installed.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'freeform', minScope: 'content')]
    public function listFreeformSubmissions(int $limit = 25): array
    {
        return Activity::run('list_freeform_submissions', static function () use ($limit) {
            if (!\Craft::$app->getPlugins()->isPluginInstalled('freeform') || !class_exists(Submission::class)) {
                return ['available' => false, 'submissions' => []];
            }

            $rows = [];
            $query = Submission::find()->limit(Page::limit($limit))->orderBy(['dateCreated' => \SORT_DESC]);
            foreach ($query->all() as $submission) {
                $rows[] = [
                    'id' => $submission->id,
                    'title' => $submission->title,
                    'dateCreated' => $submission->dateCreated?->format(\DATE_ATOM),
                    'cpEditUrl' => $submission->getCpEditUrl(),
                ];
            }

            return ['available' => true, 'count' => \count($rows), 'submissions' => $rows];
        });
    }

    #[McpTool(name: 'list_calendar_events', description: 'List upcoming Calendar events when Calendar is installed.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'calendar')]
    public function listCalendarEvents(int $limit = 25): array
    {
        return Activity::run('list_calendar_events', static function () use ($limit) {
            if (!\Craft::$app->getPlugins()->isPluginInstalled('calendar') || !class_exists(Event::class)) {
                return ['available' => false, 'events' => []];
            }

            $rows = [];
            $query = Event::find()->limit(Page::limit($limit));
            foreach ($query->all() as $event) {
                $rows[] = [
                    'id' => $event->id,
                    'title' => $event->title,
                    'cpEditUrl' => $event->getCpEditUrl(),
                ];
            }

            return ['available' => true, 'count' => \count($rows), 'events' => $rows];
        });
    }
}
