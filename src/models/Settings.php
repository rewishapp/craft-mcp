<?php

namespace rewish\craft\mcp\models;

use craft\base\Model;
use rewish\craft\mcp\enums\Scope;
use rewish\craft\mcp\http\oauth\RedirectUris;

class Settings extends Model
{
    public bool $enabled = true;

    /** @var list<string> */
    public array $disabledTools = [];

    /** @var list<string> */
    public array $disabledPrompts = [];

    /** @var list<string> */
    public array $disabledResources = [];

    /** @var list<string> */
    public array $disabledScopes = [];

    public bool $enableDangerousTools = false;

    public bool $enableTinker = false;

    /** @var list<string> */
    public array $allowedIps = [];

    public string $logLevel = 'error';

    public int $paginationLimit = 100;

    public int $maxResponseBytes = 1048576;

    public string $entryWriteMode = 'draft';

    public bool $httpTransport = false;

    public string $httpPath = 'mcp';

    public int $httpSessionTtl = 3600;

    public ?string $httpPublicUrl = null;

    /**
     * Lets Claude and other OAuth clients connect by URL: the user logs in to
     * the control panel, picks a scope and approves, and the app receives
     * tokens tied to that user. Only active while httpTransport is on.
     */
    public bool $oauth = false;

    /**
     * Callback URLs an app may register. Registration is open, so this list is
     * what keeps a consent from being sent anywhere else. A loopback entry
     * matches any port.
     *
     * @var list<string>
     */
    public array $oauthRedirectUris = RedirectUris::DEFAULTS;

    /** Lifetime of an OAuth access token, in seconds. */
    public int $oauthAccessTokenTtl = 3600;

    /** Lifetime of a refresh token, in seconds; each use replaces it. */
    public int $oauthRefreshTokenTtl = 2592000;

    public string $additionalInstructions = '';

    public bool $showClientConfigSnippet = true;

    public bool $publishServerCard = false;

    /** When true, entry write tools include a Craft preview-token URL when the section has a URL. */
    public bool $previewUrls = true;

    /**
     * Preview link lifetime in seconds. Null uses Craft’s `previewTokenDuration` / default.
     */
    public ?int $previewTokenDuration = null;

    public function __construct($config = [])
    {
        if (isset(\Craft::$app) && \Craft::$app->env === 'production') {
            $this->enabled = false;
            $this->enableDangerousTools = false;
            $this->enableTinker = false;
        }

        parent::__construct($config);
    }

    public function defineRules(): array
    {
        return [
            [[
                'enabled',
                'enableDangerousTools',
                'enableTinker',
                'httpTransport',
                'oauth',
                'showClientConfigSnippet',
                'publishServerCard',
                'previewUrls',
            ], 'boolean'],
            [[
                'disabledTools',
                'disabledPrompts',
                'disabledResources',
                'allowedIps',
            ], 'each', 'rule' => ['string']],
            [['disabledScopes'], 'each', 'rule' => ['in', 'range' => Scope::values()]],
            [['logLevel'], 'in', 'range' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']],
            [['paginationLimit'], 'integer', 'min' => 1, 'max' => 500],
            [['maxResponseBytes'], 'integer', 'min' => 0],
            [['entryWriteMode'], 'in', 'range' => ['draft', 'live']],
            [['httpPath'], 'required'],
            [['httpPath'], 'match', 'pattern' => '/^[a-z0-9][a-z0-9\-\/]*$/i'],
            [['httpSessionTtl'], 'integer', 'min' => 60],
            [['previewTokenDuration'], 'integer', 'min' => 60, 'skipOnEmpty' => true],
            // Not Yii's url validator: it wants a dot in the host, and the
            // loopback callbacks Claude Code uses are http://localhost/...
            [['oauthRedirectUris'], 'each', 'rule' => ['match', 'pattern' => '#^https?://[^\s/?\#]+(/[^\s\#]*)?$#i']],
            [['oauthAccessTokenTtl'], 'integer', 'min' => 60],
            [['oauthRefreshTokenTtl'], 'integer', 'min' => 3600],
            [['httpPublicUrl'], 'url', 'skipOnEmpty' => true],
            [['additionalInstructions'], 'string'],
        ];
    }
}
