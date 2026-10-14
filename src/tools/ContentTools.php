<?php

namespace rewish\craft\mcp\tools;

use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\db\ElementQuery;
use craft\elements\GlobalSet;
use craft\elements\Tag;
use craft\elements\User;
use craft\helpers\Assets as AssetsHelper;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\services\Activity;
use rewish\craft\mcp\support\CallContext;
use rewish\craft\mcp\support\Page;

final class ContentTools
{
    #[McpTool(name: 'list_volumes', description: 'List asset volumes.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'content')]
    public function listVolumes(): array
    {
        return Activity::run('list_volumes', static function () {
            $volumes = [];
            foreach (\Craft::$app->getVolumes()->getAllVolumes() as $volume) {
                $volumes[] = ['id' => $volume->id, 'handle' => $volume->handle, 'name' => $volume->name];
            }

            return ['count' => \count($volumes), 'volumes' => $volumes];
        });
    }

    #[McpTool(name: 'list_asset_folders', description: 'List folders in a volume.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'content')]
    public function listAssetFolders(string $volume): array
    {
        return Activity::run('list_asset_folders', static function () use ($volume) {
            $model = \Craft::$app->getVolumes()->getVolumeByHandle($volume);
            if ($model === null) {
                throw new ToolCallException("Unknown volume {$volume}.");
            }
            $root = \Craft::$app->getAssets()->getRootFolderByVolumeId((int) $model->id);
            if ($root === null) {
                return ['count' => 0, 'folders' => []];
            }
            $folders = [];
            foreach (\Craft::$app->getAssets()->getAllDescendantFolders($root) as $folder) {
                $folders[] = ['id' => $folder->id, 'name' => $folder->name, 'path' => $folder->path];
            }

            return ['count' => \count($folders), 'folders' => $folders];
        });
    }

    #[McpTool(name: 'list_assets', description: 'List assets by volume, folder id, filename, or kind.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'content')]
    public function listAssets(?string $volume = null, ?int $folderId = null, ?string $filename = null, ?string $kind = null, int $limit = 25, int $offset = 0): array
    {
        return Activity::run('list_assets', function () use ($volume, $folderId, $filename, $kind, $limit, $offset) {
            $limit = Page::limit($limit);
            $offset = Page::offset($offset);
            $query = Asset::find()->limit($limit)->offset($offset);
            if ($volume) {
                $query->volume($volume);
            }
            if ($folderId) {
                $query->folderId($folderId);
            }
            if ($filename) {
                $query->filename($filename);
            }
            if ($kind) {
                $query->kind($kind);
            }
            $total = (int) (clone $query)->limit(null)->offset(null)->count();
            $assets = array_map($this->asset(...), $query->all());

            return Page::make($assets, $total, $limit, $offset, 'assets');
        });
    }

    #[McpTool(name: 'get_asset', description: 'Get one asset by id.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'content')]
    public function getAsset(int $id): array
    {
        return Activity::run('get_asset', function () use ($id) {
            $asset = Asset::find()->id($id)->one();
            if (!$asset instanceof Asset) {
                throw new ToolCallException('No asset matched.');
            }

            return ['found' => true, 'asset' => $this->asset($asset)];
        });
    }

    #[McpTool(name: 'upload_asset', description: 'Upload an asset from an https URL or a base64 payload into a volume. Limited to 20 MB.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function uploadAsset(string $volume, string $filename, ?string $url = null, ?string $base64 = null, ?int $folderId = null): array
    {
        return Activity::run('upload_asset', function () use ($volume, $filename, $url, $base64, $folderId) {
            $model = \Craft::$app->getVolumes()->getVolumeByHandle($volume);
            if ($model === null) {
                throw new ToolCallException("Unknown volume {$volume}.");
            }

            $temp = $this->stage($filename, $url, $base64);
            $folder = $folderId
                ? \Craft::$app->getAssets()->getFolderById($folderId)
                : \Craft::$app->getAssets()->getRootFolderByVolumeId((int) $model->id);
            if ($folder === null) {
                throw new ToolCallException('Unknown folder.');
            }

            $asset = new Asset();
            $asset->volumeId = $model->id;
            $asset->newFolderId = $folder->id;
            $asset->tempFilePath = $temp;
            $asset->filename = AssetsHelper::prepareAssetName($filename);
            $asset->avoidFilenameConflicts = true;
            if (!\Craft::$app->getElements()->saveElement($asset)) {
                @unlink($temp);

                throw new ToolCallException('Craft rejected the upload.');
            }

            return ['success' => true, 'asset' => $this->asset($asset)];
        });
    }

    #[McpTool(name: 'update_asset', description: 'Update an asset title or alt text.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function updateAsset(int $id, ?string $title = null, ?string $alt = null): array
    {
        return Activity::run('update_asset', function () use ($id, $title, $alt) {
            $asset = Asset::find()->id($id)->one();
            if (!$asset instanceof Asset) {
                throw new ToolCallException('No asset matched.');
            }
            if ($title !== null) {
                $asset->title = $title;
            }
            if ($alt !== null) {
                $asset->alt = $alt;
            }
            if (!\Craft::$app->getElements()->saveElement($asset)) {
                throw new ToolCallException('Craft rejected the asset update.');
            }

            return ['success' => true, 'asset' => $this->asset($asset)];
        });
    }

    #[McpTool(name: 'list_categories', description: 'List categories in a group.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'content')]
    public function listCategories(?string $group = null, int $limit = 50): array
    {
        return Activity::run('list_categories', static function () use ($group, $limit) {
            $query = Category::find()->limit(Page::limit($limit, 200));
            if ($group) {
                $query->group($group);
            }
            $rows = [];
            foreach ($query->all() as $category) {
                $rows[] = [
                    'id' => $category->id,
                    'title' => $category->title,
                    'slug' => $category->slug,
                    'group' => $category->getGroup()?->handle,
                ];
            }

            return ['count' => \count($rows), 'categories' => $rows];
        });
    }

    #[McpTool(name: 'create_category', description: 'Create a category in a group.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function createCategory(string $group, string $title, ?string $slug = null): array
    {
        return Activity::run('create_category', static function () use ($group, $title, $slug) {
            $model = \Craft::$app->getCategories()->getGroupByHandle($group);
            if ($model === null) {
                throw new ToolCallException("Unknown category group {$group}.");
            }
            $category = new Category();
            $category->groupId = $model->id;
            $category->title = $title;
            if ($slug) {
                $category->slug = $slug;
            }
            if (!\Craft::$app->getElements()->saveElement($category)) {
                throw new ToolCallException('Craft rejected the category.');
            }

            return ['success' => true, 'id' => $category->id, 'slug' => $category->slug];
        });
    }

    #[McpTool(name: 'list_tags', description: 'List tags in a group.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'content')]
    public function listTags(?string $group = null, int $limit = 50): array
    {
        return Activity::run('list_tags', static function () use ($group, $limit) {
            $query = Tag::find()->limit(Page::limit($limit, 200));
            if ($group) {
                $query->group($group);
            }
            $rows = [];
            foreach ($query->all() as $tag) {
                $rows[] = ['id' => $tag->id, 'title' => $tag->title, 'group' => $tag->getGroup()?->handle];
            }

            return ['count' => \count($rows), 'tags' => $rows];
        });
    }

    #[McpTool(name: 'create_tag', description: 'Create a tag in a group.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function createTag(string $group, string $title): array
    {
        return Activity::run('create_tag', static function () use ($group, $title) {
            $model = \Craft::$app->getTags()->getTagGroupByHandle($group);
            if ($model === null) {
                throw new ToolCallException("Unknown tag group {$group}.");
            }
            $tag = new Tag();
            $tag->groupId = $model->id;
            $tag->title = $title;
            if (!\Craft::$app->getElements()->saveElement($tag)) {
                throw new ToolCallException('Craft rejected the tag.');
            }

            return ['success' => true, 'id' => $tag->id];
        });
    }

    #[McpTool(name: 'list_users', description: 'List users by group handle, status, or email.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content')]
    public function listUsers(?string $group = null, ?string $status = null, ?string $email = null, int $limit = 25): array
    {
        return Activity::run('list_users', static function () use ($group, $status, $email, $limit) {
            $query = User::find()->limit(Page::limit($limit));
            if ($group) {
                $query->group($group);
            }
            if ($status) {
                $query->status($status);
            }
            if ($email) {
                $query->email($email);
            }
            $rows = [];
            foreach ($query->all() as $user) {
                $rows[] = [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'fullName' => $user->fullName,
                    'status' => $user->getStatus(),
                    'admin' => (bool) $user->admin,
                ];
            }

            return ['count' => \count($rows), 'users' => $rows];
        });
    }

    #[McpTool(name: 'create_user', description: 'Create a pending user. Requires a full-scope token and an admin Craft user.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'full', dangerous: true)]
    public function createUser(string $email, string $username, ?string $fullName = null): array
    {
        return Activity::run('create_user', static function () use ($email, $username, $fullName) {
            $actor = CallContext::$userId
                ? \Craft::$app->getUsers()->getUserById(CallContext::$userId)
                : null;
            if ($actor !== null && !$actor->admin) {
                throw new ToolCallException('Only an admin user can create users through MCP.');
            }

            $user = new User();
            $user->email = $email;
            $user->username = $username;
            $user->fullName = $fullName;
            $user->pending = true;
            if (!\Craft::$app->getElements()->saveElement($user)) {
                throw new ToolCallException('Craft rejected the user.');
            }

            return ['success' => true, 'id' => $user->id, 'username' => $user->username];
        });
    }

    #[McpTool(name: 'list_globals', description: 'List global sets and their field values.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'content', minScope: 'full')]
    public function listGlobals(?string $site = null): array
    {
        return Activity::run('list_globals', function () use ($site) {
            $sets = [];
            foreach (\Craft::$app->getGlobals()->getAllSets() as $set) {
                if ($site) {
                    $localized = GlobalSet::find()->id($set->id)->site($site)->one();
                    if ($localized instanceof GlobalSet) {
                        $set = $localized;
                    }
                }
                $fields = [];
                foreach ($set->getFieldLayout()?->getCustomFields() ?? [] as $field) {
                    $fields[$field->handle] = $set->getFieldValue($field->handle);
                }
                $sets[] = ['handle' => $set->handle, 'name' => $set->name, 'fields' => $this->plain($fields)];
            }

            return ['count' => \count($sets), 'globals' => $sets];
        });
    }

    #[McpTool(name: 'update_global_set', description: 'Update field values on a global set.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function updateGlobalSet(string $handle, #[Schema(type: 'object', additionalProperties: true)] array $fields, ?string $site = null): array
    {
        return Activity::run('update_global_set', static function () use ($handle, $fields, $site) {
            $set = \Craft::$app->getGlobals()->getSetByHandle($handle);
            if (!$set instanceof GlobalSet) {
                throw new ToolCallException("Unknown global set {$handle}.");
            }
            if ($site) {
                $localized = GlobalSet::find()->id($set->id)->site($site)->one();
                if ($localized instanceof GlobalSet) {
                    $set = $localized;
                }
            }
            $set->setFieldValues($fields);
            if (!\Craft::$app->getElements()->saveElement($set)) {
                throw new ToolCallException('Craft rejected the global set.');
            }

            return ['success' => true, 'handle' => $set->handle];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function asset(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'title' => $asset->title,
            'filename' => $asset->getFilename(),
            'kind' => $asset->kind,
            'size' => $asset->size,
            'width' => $asset->width,
            'height' => $asset->height,
            'volume' => $asset->getVolume()?->handle,
            'url' => $asset->getUrl(),
        ];
    }

    private function stage(string $filename, ?string $url, ?string $base64): string
    {
        if (($url === null) === ($base64 === null)) {
            throw new ToolCallException('Pass either url or base64, not both.');
        }

        $temp = \Craft::$app->getPath()->getTempPath().'/'.uniqid('aio-', true).'-'.preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        if ($url !== null) {
            if (!preg_match('#^https://#i', $url)) {
                throw new ToolCallException('Asset URLs must start with https://');
            }
            $body = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 20]]));
            if (!\is_string($body)) {
                throw new ToolCallException('Could not download that URL.');
            }
        } else {
            $body = base64_decode((string) $base64, true);
            if (!\is_string($body)) {
                throw new ToolCallException('base64 payload could not be decoded.');
            }
        }

        if (\strlen($body) > 20 * 1024 * 1024) {
            throw new ToolCallException('The file is larger than 20 MB.');
        }
        file_put_contents($temp, $body);

        return $temp;
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private function plain(array $fields): array
    {
        $plain = [];
        foreach ($fields as $handle => $value) {
            $plain[$handle] = $this->stringify($value);
        }

        return $plain;
    }

    private function stringify(mixed $value): mixed
    {
        if ($value instanceof ElementQuery) {
            $value = $value->all();
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DATE_ATOM);
        }
        if ($value instanceof ElementInterface) {
            return ['id' => $value->id, 'title' => $value->title ?? null];
        }
        if (\is_array($value)) {
            return array_map($this->stringify(...), $value);
        }
        if (\is_scalar($value) || $value === null) {
            return $value;
        }

        return method_exists($value, '__toString') ? (string) $value : null;
    }
}
