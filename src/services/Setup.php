<?php

namespace rewish\craft\mcp\services;

use rewish\craft\mcp\Mcp;

final class Setup
{
    public function binPath(): string
    {
        $plugin = Mcp::getInstance();
        if ($plugin === null) {
            return 'vendor/rewi.sh/craft-mcp/bin/mcp-server';
        }

        $root = \dirname($plugin->getBasePath());
        foreach ([$root.'/bin/mcp-server', $plugin->getBasePath().'/bin/mcp-server'] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return $root.'/bin/mcp-server';
    }

    public function endpointUrl(): string
    {
        $settings = Mcp::settings();
        $path = trim($settings->httpPath, '/');
        if ($settings->httpPublicUrl) {
            return rtrim($settings->httpPublicUrl, '/').'/'.$path;
        }

        return rtrim(\Craft::$app->getSites()->getPrimarySite()->getBaseUrl() ?? '', '/').'/'.$path;
    }

    public function stdioSnippet(): string
    {
        $payload = [
            'mcpServers' => [
                'craft-mcp' => [
                    'command' => \PHP_BINARY,
                    'args' => [$this->binPath()],
                ],
            ],
        ];

        return json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) ?: '';
    }

    public function httpSnippet(string $token = 'YOUR_TOKEN'): string
    {
        $payload = [
            'mcpServers' => [
                'craft-mcp' => [
                    'url' => $this->endpointUrl(),
                    'headers' => [
                        'Authorization' => 'Bearer '.$token,
                    ],
                ],
            ],
        ];

        return json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) ?: '';
    }
}
