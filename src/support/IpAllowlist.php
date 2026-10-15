<?php

namespace rewish\craft\mcp\support;

use yii\helpers\IpHelper;

/**
 * Exact addresses and CIDR ranges (IPv4 and IPv6).
 *
 * An empty list admits everyone. A bad entry or client address admits no one,
 * so a typo fails closed. Uses Yii’s IpHelper::inRange.
 */
final class IpAllowlist
{
    /**
     * @param list<string> $entries
     */
    public static function allows(?string $clientIp, array $entries): bool
    {
        if ($entries === []) {
            return true;
        }

        if ($clientIp === null || filter_var($clientIp, \FILTER_VALIDATE_IP) === false) {
            return false;
        }

        foreach ($entries as $entry) {
            $entry = trim((string) $entry);
            if ($entry !== '' && self::valid($entry) && IpHelper::inRange($clientIp, $entry)) {
                return true;
            }
        }

        return false;
    }

    public static function matches(string $clientIp, string $entry): bool
    {
        $entry = trim($entry);
        if ($entry === '' || filter_var($clientIp, \FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return self::valid($entry) && IpHelper::inRange($clientIp, $entry);
    }

    private static function valid(string $entry): bool
    {
        [$address, $mask] = array_pad(explode('/', $entry, 2), 2, null);
        if (filter_var($address, \FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($mask === null) {
            return true;
        }

        return ctype_digit($mask) && (int) $mask <= (str_contains((string) $address, ':') ? 128 : 32);
    }
}
