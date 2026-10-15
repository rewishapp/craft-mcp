<?php

namespace rewish\craft\mcp\support;

use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use rewish\craft\mcp\Mcp;

/**
 * Craft preview-token URL for an entry (draft or live), when it has a URL.
 *
 * Matches Craft’s Share button: anonymous `preview/preview` with a preview token.
 * userId is null — that id only selects a provisional draft; MCP writes real drafts.
 */
final class PreviewLinks
{
    public static function forEntry(Entry $entry): ?string
    {
        $settings = Mcp::settings();
        if (!$settings->previewUrls) {
            return null;
        }

        $base = self::targetUrl($entry);
        if ($base === null) {
            return null;
        }

        try {
            return self::mint($entry, $base);
        } catch (\Throwable $e) {
            \Craft::warning(\sprintf('Could not mint a preview link for entry %s: %s', $entry->id, $e->getMessage()), __METHOD__);

            return null;
        }
    }

    private static function targetUrl(Entry $entry): ?string
    {
        $targets = self::previewTargets($entry);
        $url = $targets[0]['url'] ?? null;
        if (!\is_string($url) || $url === '') {
            try {
                $url = $entry->getUrl();
            } catch (\Throwable) {
                $url = null;
            }
        }

        return \is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function previewTargets(Entry $entry): array
    {
        try {
            return $entry->getPreviewTargets();
        } catch (\Throwable $e) {
            \Craft::warning(\sprintf('Preview targets unavailable for entry %s, using its own URL: %s', $entry->id, $e->getMessage()), __METHOD__);

            return [];
        }
    }

    private static function mint(Entry $entry, string $url): ?string
    {
        $route = [
            'preview/preview',
            [
                'elementType' => Entry::class,
                'canonicalId' => (int) $entry->getCanonicalId(),
                'siteId' => (int) $entry->siteId,
                'draftId' => $entry->draftId ? (int) $entry->draftId : null,
                'revisionId' => $entry->revisionId ? (int) $entry->revisionId : null,
                'userId' => null,
            ],
        ];

        $settings = Mcp::settings();
        $tokens = \Craft::$app->getTokens();

        if ($settings->previewTokenDuration !== null && $settings->previewTokenDuration > 0) {
            $expiry = DateTimeHelper::currentUTCDateTime();
            $expiry->add(DateTimeHelper::secondsToInterval($settings->previewTokenDuration));
            $token = $tokens->createToken($route, null, $expiry);
        } else {
            $token = $tokens->createPreviewToken($route);
        }

        return \is_string($token) && $token !== '' ? UrlHelper::urlWithToken($url, $token) : null;
    }
}
