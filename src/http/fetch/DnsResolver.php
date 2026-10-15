<?php

namespace rewish\craft\mcp\http\fetch;

/**
 * Every IPv4 and IPv6 address a host name answers with, for SafeFetch to vet.
 * A lookup that fails answers with none, which SafeFetch refuses.
 */
final readonly class DnsResolver
{
    /**
     * @return list<string>
     */
    public function __invoke(string $host): array
    {
        try {
            // Craft's error handler turns the warning a failed lookup raises
            // into an exception; either way it means no address.
            $records = dns_get_record($host, \DNS_A | \DNS_AAAA);
        } catch (\Throwable) {
            return [];
        }

        if ($records === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));
    }
}
