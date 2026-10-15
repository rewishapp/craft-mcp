<?php

namespace rewish\craft\mcp;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Gc;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use rewish\craft\mcp\http\oauth\RecordOAuthStore;
use rewish\craft\mcp\models\Settings;
use yii\base\Event;

class Mcp extends BasePlugin
{
    public const EVENT_REGISTER_TOOLS = 'registerTools';

    public const EVENT_REGISTER_PROMPTS = 'registerPrompts';

    public const EVENT_REGISTER_RESOURCES = 'registerResources';

    public const EVENT_REGISTER_FIELD_TRANSLATORS = 'registerFieldTranslators';

    public string $schemaVersion = '1.1.0';

    public bool $hasCpSection = true;

    public bool $hasCpSettings = false;

    public function init(): void
    {
        parent::init();

        \Craft::setAlias('@craft-mcp', $this->getBasePath());
        if (\Craft::$app instanceof \craft\console\Application) {
            $this->controllerNamespace = 'rewish\craft\mcp\console\controllers';
        } else {
            $this->controllerNamespace = 'rewish\craft\mcp\controllers';
        }

        $this->registerPermissions();
        $this->registerCpRoutes();
        $this->registerHttpRoute();
        $this->registerOAuthGarbageCollection();
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

            $settings = self::settings();
            if ($settings->httpTransport && $settings->oauth) {
                $event->rules['craft-mcp/connect'] = 'craft-mcp/oauth-consent/index';
            }

            // Headless CP host (empty cpTrigger): OAuth discovery must answer on the same host.
            if ($settings->httpTransport && $settings->enabled && $settings->oauth && \Craft::$app->getConfig()->getGeneral()->cpTrigger === '') {
                foreach (self::oauthSiteRules($settings) as $pattern => $route) {
                    $event->rules[$pattern] = $route;
                }
            }
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
                    if ($settings->oauth) {
                        foreach (self::oauthSiteRules($settings) as $pattern => $route) {
                            $event->rules[$pattern] = $route;
                        }
                    }
                }
                if ($settings->publishServerCard) {
                    $event->rules['.well-known/mcp/server-card.json'] = 'craft-mcp/http/card';
                }
            });
        });
    }

    /**
     * @return array<string, string>
     */
    private static function oauthSiteRules(Settings $settings): array
    {
        $path = trim($settings->httpPath, '/');

        return [
            '.well-known/oauth-protected-resource' => 'craft-mcp/oauth/protected-resource',
            '.well-known/oauth-protected-resource/'.$path => 'craft-mcp/oauth/protected-resource',
            '.well-known/oauth-authorization-server' => 'craft-mcp/oauth/authorization-server',
            '.well-known/oauth-authorization-server/'.$path => 'craft-mcp/oauth/authorization-server',
            $path.'/oauth/register' => 'craft-mcp/oauth/register',
            $path.'/oauth/token' => 'craft-mcp/oauth/token',
        ];
    }

    private function registerOAuthGarbageCollection(): void
    {
        Event::on(Gc::class, Gc::EVENT_RUN, static function (): void {
            if (!self::settings()->oauth) {
                return;
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            (new RecordOAuthStore())->collectGarbage($now, $now->modify('-30 days'));
        });
    }
}
