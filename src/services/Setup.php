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

    /**
     * The site origin the MCP endpoint and OAuth issuer live under, without a
     * trailing slash: httpPublicUrl when set, otherwise the primary site.
     */
    public function baseUrl(): string
    {
        $settings = Mcp::settings();
        if ($settings->httpPublicUrl) {
            return rtrim($settings->httpPublicUrl, '/');
        }

        return rtrim(\Craft::$app->getSites()->getPrimarySite()->getBaseUrl() ?? '', '/');
    }

    public function endpointUrl(): string
    {
        return $this->baseUrl().'/'.trim(Mcp::settings()->httpPath, '/');
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

    /**
     * Claude Code `.mcp.json` block (`type: http`). Cursor and Codex accept the same shape with url + headers.
     */
    public function httpSnippet(string $token = 'YOUR_TOKEN'): string
    {
        $payload = [
            'mcpServers' => [
                'craft-mcp' => [
                    'type' => 'http',
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
