<?php

namespace rewish\craft\mcp;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use rewish\craft\mcp\models\Settings;
use yii\base\Event;

class Mcp extends BasePlugin
{
    public const EVENT_REGISTER_TOOLS = 'registerTools';

    public const EVENT_REGISTER_PROMPTS = 'registerPrompts';

    public const EVENT_REGISTER_RESOURCES = 'registerResources';

    public const EVENT_REGISTER_FIELD_TRANSLATORS = 'registerFieldTranslators';

    public string $schemaVersion = '1.0.0';

    public bool $hasCpSection = true;

    public bool $hasCpSettings = false;

    public function init(): void
    {
        parent::init();

        \Craft::setAlias('@craft-mcp', $this->getBasePath());
        $this->controllerNamespace = 'rewish\craft\mcp\controllers';

        $this->registerPermissions();
        $this->registerCpRoutes();
        $this->registerHttpRoute();
    }

    public static function settings(): Settings
    {
        $plugin = self::getInstance();
        if (!$plugin instanceof self) {
            return new Settings();
        }

        $settings = $plugin->getSettings();

        return $settings instanceof Settings ? $settings : new Settings();
    }

    public function getCpNavItem(): ?array
    {
        $user = \Craft::$app->getUser()->getIdentity();
        if ($user === null || !$user->can('craft-mcp:access')) {
            return null;
        }

        $item = parent::getCpNavItem();
        $item['label'] = 'MCP - All in one';
        $item['url'] = 'craft-mcp';
        // Keep Craft’s default icon-mask.svg for the sidebar. icon.svg is the colored Plugin Store mark.
        $item['subnav'] = [
            'dashboard' => ['label' => 'Dashboard', 'url' => 'craft-mcp'],
            'drafts' => ['label' => 'Agent drafts', 'url' => 'craft-mcp/drafts'],
            'tools' => ['label' => 'Tools', 'url' => 'craft-mcp/tools'],
            'tokens' => ['label' => 'Tokens', 'url' => 'craft-mcp/tokens'],
            'activity' => ['label' => 'Activity', 'url' => 'craft-mcp/activity'],
            'setup' => ['label' => 'Setup', 'url' => 'craft-mcp/setup'],
            'settings' => ['label' => 'Settings', 'url' => 'craft-mcp/settings'],
        ];

        return $item;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    private function registerPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, static function (RegisterUserPermissionsEvent $event) {
            $event->permissions[] = [
                'heading' => 'MCP - All in one',
                'permissions' => [
                    'craft-mcp:access' => ['label' => 'Open the MCP dashboard'],
                    'craft-mcp:manageTokens' => [
                        'label' => 'Manage own MCP tokens',
                        'nested' => [
                            'craft-mcp:manageAllTokens' => ['label' => 'Manage everyone’s MCP tokens'],
                        ],
                    ],
                    'craft-mcp:manageSettings' => ['label' => 'Change MCP settings'],
                ],
            ];
        });
    }

    private function registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, static function (RegisterUrlRulesEvent $event) {
            $event->rules['craft-mcp'] = 'craft-mcp/cp/index';
            $event->rules['craft-mcp/drafts'] = 'craft-mcp/cp/drafts';
            $event->rules['craft-mcp/tools'] = 'craft-mcp/cp/tools';
            $event->rules['craft-mcp/tokens'] = 'craft-mcp/cp/tokens';
            $event->rules['craft-mcp/tokens/reveal'] = 'craft-mcp/cp/reveal-token';
            $event->rules['craft-mcp/activity'] = 'craft-mcp/cp/activity';
            $event->rules['craft-mcp/setup'] = 'craft-mcp/cp/setup';
            $event->rules['craft-mcp/settings'] = 'craft-mcp/cp/settings';
        });
    }

    private function registerHttpRoute(): void
    {
        // Other plugins register during init. Attach after they load so this path replaces theirs.
        \Craft::$app->onInit(static function (): void {
            Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, static function (RegisterUrlRulesEvent $event): void {
                $settings = self::settings();
                if ($settings->httpTransport && $settings->enabled) {
                    $path = trim($settings->httpPath, '/');
                    unset($event->rules[$path]);
                    $event->rules[$path] = 'craft-mcp/http/handle';
                }
                if ($settings->publishServerCard) {
                    $event->rules['.well-known/mcp/server-card.json'] = 'craft-mcp/http/card';
                }
            });
        });
    }
}
