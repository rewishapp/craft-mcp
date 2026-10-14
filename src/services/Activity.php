<?php

namespace rewish\craft\mcp\services;

use rewish\craft\mcp\Mcp;
use rewish\craft\mcp\records\ActivityRecord;
use rewish\craft\mcp\support\CallContext;

final class Activity
{
    public static function run(string $tool, callable $work): mixed
    {
        $started = hrtime(true);

        try {
            $result = $work();
            self::guardSize($result);
            self::write($tool, true, $started, null);

            return $result;
        } catch (\Throwable $e) {
            self::write($tool, false, $started, $e->getMessage());

            throw $e;
        }
    }

    public static function write(string $tool, bool $success, int $started, ?string $message): void
    {
        try {
            $record = new ActivityRecord();
            $record->userId = CallContext::$userId;
            $record->tokenId = CallContext::$tokenId;
            $record->tool = $tool;
            $record->transport = CallContext::$transport;
            $record->success = $success;
            $record->durationMs = (int) ((hrtime(true) - $started) / 1_000_000);
            $record->message = $message !== null ? mb_substr($message, 0, 2000) : null;
            $record->save(false);
        } catch (\Throwable) {
            // Activity logging must not take the tool call down with it.
        }
    }

    private static function guardSize(mixed $result): void
    {
        $max = Mcp::settings()->maxResponseBytes;
        if ($max < 1) {
            return;
        }

        $encoded = json_encode($result);
        if (\is_string($encoded) && \strlen($encoded) > $max) {
            throw new \RuntimeException('The result is too large. Narrow the query with a limit, offset, or fewer fields.');
        }
    }
}
