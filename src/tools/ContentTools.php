<?php

namespace rewish\craft\mcp\tools;

use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\db\ElementQuery;
use craft\elements\GlobalSet;
use craft\elements\Tag;
use craft\elements\User;
use craft\enums\CmsEdition;
use craft\errors\InvalidElementException;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\FileHelper;
use craft\models\Volume;
use craft\models\VolumeFolder;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\http\fetch\CurlTransport;
use rewish\craft\mcp\http\fetch\DnsResolver;
use rewish\craft\mcp\http\fetch\SafeFetch;
use rewish\craft\mcp\Mcp;
use rewish\craft\mcp\services\Activity;
use rewish\craft\mcp\support\AssetIntake;
use rewish\craft\mcp\support\CallContext;
use rewish\craft\mcp\support\ElementAuth;
use rewish\craft\mcp\support\Page;
use rewish\craft\mcp\support\UserWriteRules;
use yii\base\Model;

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

    #[McpTool(name: 'upload_asset', description: 'Upload a file into a volume. Pass exactly one of url (a file on the public internet, fetched over https by the server: no redirects, no private or internal addresses) or base64 (a small file, optionally as a data: URI). Both are capped by the assetUploadMaxBytes setting. folder is a path in the volume such as "blog/2026" (omitted: the volume root; missing folders are created); folderId is the alternative. Returns the asset and its natural key {volume, path, filename}.', annotations: new ToolAnnotations(destructiveHint: true, openWorldHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function uploadAsset(string $volume, string $filename, ?string $url = null, ?string $base64 = null, ?int $folderId = null, ?string $folder = null, ?string $title = null, ?string $alt = null): array
    {
        return Activity::run('upload_asset', function () use ($volume, $filename, $url, $base64, $folderId, $folder, $title, $alt) {
            $model = $this->volume($volume);
            if ($folderId !== null && $folder !== null) {
                throw new ToolCallException('Pass either folder (a path) or folderId, not both.');
            }

            $bytes = $this->intake()->bytes($url, $base64);
            if ($folderId !== null) {
                $target = \Craft::$app->getAssets()->getFolderById($folderId);
                if ($target === null || (int) $target->volumeId !== (int) $model->id) {
                    throw new ToolCallException("Folder {$folderId} is not in volume {$volume}.");
                }
            } else {
                $target = $this->folder($model, $folder, true);
            }

            $name = AssetsHelper::prepareAssetName($filename);
            $temp = AssetsHelper::tempFilePath(pathinfo($name, \PATHINFO_EXTENSION) ?: 'tmp');

            try {
                if (file_put_contents($temp, $bytes) === false) {
                    throw new ToolCallException('The file could not be written to Craft\'s temp folder.');
                }

                $asset = new Asset();
                $asset->tempFilePath = $temp;
                $asset->setFilename($name);
                $asset->setMimeType(FileHelper::getMimeType($temp, null, false));
                $asset->newFolderId = $target->id;
                $asset->setVolumeId((int) $model->id);
                $asset->uploaderId = CallContext::$userId;
                $asset->avoidFilenameConflicts = true;
                $asset->setScenario(Asset::SCENARIO_CREATE);
                if ($title !== null) {
                    $asset->title = $title;
                }
                if ($alt !== null) {
                    $asset->alt = $alt;
                }

                ElementAuth::assertCanSave($asset);

                if (!\Craft::$app->getElements()->saveElement($asset)) {
                    throw new ToolCallException('Craft rejected the upload: '.$this->errors($asset));
                }
            } finally {
                // Craft streams the temp file into the volume and leaves it behind.
                if (is_file($temp)) {
                    FileHelper::unlink($temp);
                }
            }

            return $this->saved($asset);
        });
    }

    #[McpTool(name: 'update_asset', description: 'Update an asset: title, alt text, rename the file (newFilename), or move it to another folder in the same volume (newFolder, a path; "" for the root). Address it by id, or by volume + folder + filename. Only supplied values change; the file contents cannot be replaced.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function updateAsset(?int $id = null, ?string $volume = null, ?string $folder = null, ?string $filename = null, ?string $title = null, ?string $alt = null, ?string $newFilename = null, ?string $newFolder = null): array
    {
        return Activity::run('update_asset', function () use ($id, $volume, $folder, $filename, $title, $alt, $newFilename, $newFolder) {
            $asset = $this->findAsset($id, $volume, $folder, $filename);
            ElementAuth::assertCanSave($asset);
            if ($title !== null) {
                $asset->title = $title;
            }
            if ($alt !== null) {
                $asset->alt = $alt;
            }
            if ($newFolder !== null) {
                $model = $asset->getVolume();
                $asset->newFolderId = $this->folder($model, $newFolder, true)->id;
                $asset->setScenario(Asset::SCENARIO_MOVE);
            }
            if ($newFilename !== null) {
                $asset->newFilename = AssetsHelper::prepareAssetName($newFilename);
                $asset->setScenario(Asset::SCENARIO_FILEOPS);
            }
            if (!\Craft::$app->getElements()->saveElement($asset)) {
                throw new ToolCallException('Craft rejected the asset update: '.$this->errors($asset));
            }

            return $this->saved($asset);
        });
    }

    #[McpTool(name: 'delete_asset', description: 'Move an asset to the trash (restorable in the control panel). Address it by id, or by volume + folder + filename.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'content', dangerous: true)]
    public function deleteAsset(?int $id = null, ?string $volume = null, ?string $folder = null, ?string $filename = null): array
    {
        return Activity::run('delete_asset', function () use ($id, $volume, $folder, $filename) {
            $asset = $this->findAsset($id, $volume, $folder, $filename);
            ElementAuth::assertCanDelete($asset);
            if (!\Craft::$app->getElements()->deleteElement($asset)) {
                throw new ToolCallException('Craft could not delete the asset.');
            }

            return ['success' => true, 'deleted' => $asset->id, 'restorable' => true];
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

    #[McpTool(name: 'create_user', description: 'Create a pending user: they cannot sign in until they activate the account, or set_user_status makes it active. No activation email is sent unless sendActivationEmail is true. Never creates an admin. groups are user group handles (Craft Pro). Requires a full-scope token and an admin Craft user.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'full', dangerous: true)]
    public function createUser(string $email, ?string $username = null, ?string $firstName = null, ?string $lastName = null, #[Schema(type: 'array', items: ['type' => 'string'])] ?array $groups = null, bool $sendActivationEmail = false): array
    {
        return Activity::run('create_user', function () use ($email, $username, $firstName, $lastName, $groups, $sendActivationEmail) {
            $this->assertAdminCaller();
            $users = \Craft::$app->getUsers();
            $edition = \Craft::$app->edition;
            UserWriteRules::assertRoomForUser($edition->name, $users->getMaxUsers($edition), (int) User::find()->status(null)->count());
            $groupIds = $this->groupIds($groups);

            $user = new User();
            $user->email = $email;
            $user->username = $username ?? $email;
            $user->firstName = $firstName;
            $user->lastName = $lastName;
            $user->pending = true;
            if (!\Craft::$app->getElements()->saveElement($user)) {
                throw new ToolCallException('Craft rejected the user: '.$this->errors($user));
            }
            if ($groupIds !== null) {
                $users->assignUserToGroups((int) $user->id, $groupIds);
            }
            $sent = $sendActivationEmail && $users->sendActivationEmail($user);

            return ['success' => true, 'user' => $this->userSummary($user), 'activationEmailSent' => $sent];
        });
    }

    #[McpTool(name: 'update_user', description: 'Change a user\'s first name, last name, or groups. groups is the complete set and replaces the current one ([] removes every group). Admin accounts and the account this connection acts as are refused; email and password are never changed here. Requires a full-scope token and an admin Craft user.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'content', minScope: 'full', dangerous: true)]
    public function updateUser(string $user, ?string $firstName = null, ?string $lastName = null, #[Schema(type: 'array', items: ['type' => 'string'])] ?array $groups = null): array
    {
        return Activity::run('update_user', function () use ($user, $firstName, $lastName, $groups) {
            $this->assertAdminCaller();
            $target = $this->findUser($user);
            $groupIds = $this->groupIds($groups);

            if ($firstName !== null) {
                $target->firstName = $firstName;
            }
            if ($lastName !== null) {
                $target->lastName = $lastName;
            }
            // A stored fullName wins on save and would throw the new parts
            // away; cleared, it is rebuilt from them.
            if ($firstName !== null || $lastName !== null) {
                $target->fullName = null;
            }
            if (!\Craft::$app->getElements()->saveElement($target)) {
                throw new ToolCallException('Craft rejected the user: '.$this->errors($target));
            }
            if ($groupIds !== null) {
                \Craft::$app->getUsers()->assignUserToGroups((int) $target->id, $groupIds);
            }

            return ['success' => true, 'user' => $this->userSummary($target)];
        });
    }

    #[McpTool(name: 'set_user_status', description: 'Set a user\'s status: "active" activates a pending account or lifts a suspension, "suspended" stops sign-in but keeps the account, "inactive" deactivates it. Admin accounts and the account this connection acts as are refused. Requires a full-scope token and an admin Craft user.', annotations: new ToolAnnotations(destructiveHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'content', minScope: 'full', dangerous: true)]
    public function setUserStatus(string $user, #[Schema(enum: ['active', 'suspended', 'inactive'])] string $status): array
    {
        return Activity::run('set_user_status', function () use ($user, $status) {
            $this->assertAdminCaller();
            $target = $this->findUser($user);
            $users = \Craft::$app->getUsers();

            try {
                match ($status) {
                    'active' => $this->activate($target),
                    'suspended' => $users->suspendUser($target),
                    'inactive' => $users->deactivateUser($target),
                    default => throw new ToolCallException("Unknown status '{$status}'; use active, suspended or inactive."),
                };
            } catch (InvalidElementException) {
                throw new ToolCallException('Craft rejected the status change: '.$this->errors($target));
            }

            return ['success' => true, 'user' => $this->userSummary($target)];
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

    private function intake(): AssetIntake
    {
        $max = Mcp::settings()->assetUploadMaxBytes;

        return new AssetIntake(new SafeFetch((new DnsResolver())(...), (new CurlTransport())(...), $max), $max);
    }

    private function volume(string $handle): Volume
    {
        return \Craft::$app->getVolumes()->getVolumeByHandle($handle)
            ?? throw new ToolCallException("Unknown volume {$handle}.");
    }

    /**
     * The folder at a path inside a volume (null or "" is the root). A missing
     * folder is created when $create is set, otherwise null comes back.
     */
    private function folder(Volume $volume, ?string $path, bool $create): ?VolumeFolder
    {
        $assets = \Craft::$app->getAssets();
        $trimmed = trim((string) $path, '/');
        if ($trimmed === '') {
            return $assets->getRootFolderByVolumeId((int) $volume->id);
        }

        // Folders are created on disk for local volumes, so a segment that
        // steps outside its parent is refused here.
        foreach (explode('/', $trimmed) as $segment) {
            if (\in_array($segment, ['', '.', '..'], true) || str_contains($segment, '\\')) {
                throw new ToolCallException("Invalid folder path '{$path}': use folder names separated by '/', such as 'blog/2026', without '.' or '..'.");
            }
        }

        $existing = $assets->findFolder(['volumeId' => $volume->id, 'path' => $trimmed.'/']);
        if ($existing !== null || !$create) {
            return $existing;
        }

        return $assets->ensureFolderByFullPathAndVolume($trimmed.'/', $volume, false);
    }

    private function findAsset(?int $id, ?string $volume, ?string $folder, ?string $filename): Asset
    {
        if ($id !== null) {
            $asset = Asset::find()->id($id)->status(null)->one();
        } else {
            if ($volume === null || $filename === null) {
                throw new ToolCallException('Pass the asset id, or volume and filename (with folder when it is not in the volume root).');
            }
            $target = $this->folder($this->volume($volume), $folder, false);
            $asset = $target === null
                ? null
                : Asset::find()->volume($volume)->folderId($target->id)->filename($filename)->status(null)->one();
        }

        return $asset instanceof Asset ? $asset : throw new ToolCallException('No asset matched. Use list_assets to find it.');
    }

    /**
     * @return array<string, mixed>
     */
    private function saved(Asset $asset): array
    {
        // Read back: a rename or move settles its final filename and folder on save.
        $saved = Asset::find()->id($asset->id)->status(null)->one() ?? $asset;

        return [
            'success' => true,
            'asset' => $this->asset($saved),
            'key' => [
                'volume' => $saved->getVolume()?->handle,
                'path' => rtrim((string) ($saved->getFolder()?->path ?? ''), '/'),
                'filename' => $saved->getFilename(),
            ],
        ];
    }

    private function errors(Model $model): string
    {
        $messages = [];
        foreach ($model->getErrors() as $list) {
            $messages = [...$messages, ...$list];
        }

        return $messages === [] ? 'no reason given.' : implode(' ', $messages);
    }

    /**
     * Full scope skips Craft's permission lookups, so a user write also needs
     * an admin behind the token (stdio has no user and is local trust).
     */
    private function assertAdminCaller(): void
    {
        $actor = CallContext::$userId
            ? \Craft::$app->getUsers()->getUserById(CallContext::$userId)
            : null;
        if ($actor !== null && !$actor->admin) {
            throw new ToolCallException('Only an admin user can write users through MCP.');
        }
    }

    /**
     * The account a username or email names, once the rules allow changing it.
     */
    private function findUser(string $usernameOrEmail): User
    {
        $target = User::find()->status(null)->username($usernameOrEmail)->one()
            ?? User::find()->status(null)->email($usernameOrEmail)->one()
            ?? throw new ToolCallException("User '{$usernameOrEmail}' not found. Use list_users to find the username.");

        UserWriteRules::assertMayChange((string) $target->username, (bool) $target->admin, (int) $target->id, CallContext::$userId);

        return $target;
    }

    /**
     * Resolved before anything is saved, so an unknown handle or an edition
     * without groups refuses the whole write instead of leaving it half-made.
     *
     * @param null|array<int, mixed> $handles
     *
     * @return null|list<int>
     */
    private function groupIds(?array $handles): ?array
    {
        if ($handles === null) {
            return null;
        }

        $edition = \Craft::$app->edition;
        UserWriteRules::assertGroupsAvailable($edition->name, $edition->value >= CmsEdition::Pro->value);

        $ids = [];
        foreach ($handles as $handle) {
            $group = \Craft::$app->getUserGroups()->getGroupByHandle((string) $handle);
            if ($group === null) {
                throw new ToolCallException("User group '{$handle}' not found.");
            }
            $ids[] = (int) $group->id;
        }

        return $ids;
    }

    private function activate(User $user): void
    {
        if ($user->suspended) {
            \Craft::$app->getUsers()->unsuspendUser($user);
        }
        if ($user->getStatus() !== User::STATUS_ACTIVE) {
            \Craft::$app->getUsers()->activateUser($user);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function userSummary(User $user): array
    {
        $fresh = User::find()->id($user->id)->status(null)->one() ?? $user;

        return [
            'id' => $fresh->id,
            'username' => $fresh->username,
            'email' => $fresh->email,
            'firstName' => $fresh->firstName,
            'lastName' => $fresh->lastName,
            'status' => $fresh->getStatus(),
            'groups' => array_map(static fn ($group) => $group->handle, $fresh->getGroups()),
        ];
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
