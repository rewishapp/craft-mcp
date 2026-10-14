<?php

namespace rewish\craft\mcp\support;

final class Page
{
    /**
     * @param list<mixed> $items
     *
     * @return array{count: int, total: int, limit: int, offset: int, hasMore: bool, items: list<mixed>}
     */
    public static function make(array $items, int $total, int $limit, int $offset, string $key): array
    {
        return [
            'count' => \count($items),
            $key => $items,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'hasMore' => ($offset + \count($items)) < $total,
        ];
    }

    public static function limit(int $limit, int $max = 100): int
    {
        if ($limit < 1) {
            return 25;
        }

        return min($limit, $max);
    }

    public static function offset(int $offset): int
    {
        return max(0, $offset);
    }
}
