<?php

namespace rewish\craft\mcp\tools;

use craft\models\Site;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\services\Activity;

final class SiteTools
{
    #[McpTool(name: 'list_sites', description: 'List sites with handles, languages, and base URLs.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'sites')]
    public function listSites(): array
    {
        return Activity::run('list_sites', fn () => ['count' => \count($this->rows()), 'sites' => $this->rows()]);
    }

    #[McpTool(name: 'get_site', description: 'Get one site by id or handle.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'sites')]
    public function getSite(?int $id = null, ?string $handle = null): array
    {
        return Activity::run('get_site', function () use ($id, $handle) {
            $sites = \Craft::$app->getSites();
            $site = $id ? $sites->getSiteById($id) : ($handle ? $sites->getSiteByHandle($handle) : null);
            if ($site === null) {
                throw new ToolCallException('Pass a site id or handle.');
            }

            return ['found' => true, 'site' => $this->row($site)];
        });
    }

    #[McpTool(name: 'list_site_groups', description: 'List site groups and the sites in each.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'sites')]
    public function listSiteGroups(): array
    {
        return Activity::run('list_site_groups', static function () {
            $groups = [];
            foreach (\Craft::$app->getSites()->getAllGroups() as $group) {
                $sites = [];
                foreach (\Craft::$app->getSites()->getSitesByGroupId($group->id) as $site) {
                    $sites[] = $site->handle;
                }
                $groups[] = ['id' => $group->id, 'name' => $group->name, 'sites' => $sites];
            }

            return ['count' => \count($groups), 'groups' => $groups];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        $rows = [];
        foreach (\Craft::$app->getSites()->getAllSites() as $site) {
            $rows[] = $this->row($site);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Site $site): array
    {
        return [
            'id' => $site->id,
            'handle' => $site->handle,
            'name' => $site->getName(),
            'language' => $site->language,
            'primary' => (bool) $site->primary,
            'enabled' => (bool) $site->enabled,
            'baseUrl' => $site->getBaseUrl(),
            'groupId' => $site->groupId,
        ];
    }
}
