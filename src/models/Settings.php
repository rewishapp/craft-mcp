<?php

namespace rewish\craft\mcp\models;

use craft\base\Model;
use rewish\craft\mcp\enums\Scope;

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

    public string $additionalInstructions = '';

    public bool $showClientConfigSnippet = true;

    public bool $publishServerCard = false;

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
                'showClientConfigSnippet',
                'publishServerCard',
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
            [['httpPublicUrl'], 'url', 'skipOnEmpty' => true],
            [['additionalInstructions'], 'string'],
        ];
    }
}
