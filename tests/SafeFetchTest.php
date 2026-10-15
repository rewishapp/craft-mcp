<?php

namespace rewish\craft\mcp\tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use rewish\craft\mcp\http\fetch\FetchRefused;
use rewish\craft\mcp\http\fetch\SafeFetch;
use rewish\craft\mcp\http\fetch\TransportResponse;

/**
 * @coversNothing
 */
final class SafeFetchTest extends TestCase
{
    /** @var array<string, list<string>> */
    private array $dns = ['images.example.com' => ['93.184.216.34']];

    /** @var list<array<string, mixed>> */
    private array $calls = [];

    private TransportResponse $response;

    protected function setUp(): void
    {
        $this->response = new TransportResponse(200, 'image/png', 'PNGDATA');
        $this->calls = [];
    }

    public function testFetchesPublicHttpsPinnedToVettedAddress(): void
    {
        $result = $this->fetcher()->fetch('https://images.example.com/cat.png');

        self::assertSame('PNGDATA', $result->body);
        self::assertSame('images.example.com', $this->calls[0]['host']);
        self::assertSame('93.184.216.34', $this->calls[0]['ip']);
    }

    #[DataProvider('badUrls')]
    public function testRefusesUnsafeUrls(string $url): void
    {
        $this->expectException(FetchRefused::class);

        try {
            $this->fetcher()->fetch($url);
        } finally {
            self::assertSame([], $this->calls);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badUrls(): array
    {
        return [
            'http' => ['http://images.example.com/cat.png'],
            'ftp' => ['ftp://images.example.com/cat.png'],
            'file' => ['file:///etc/passwd'],
            'credentials' => ['https://user:pass@images.example.com/cat.png'],
            'port' => ['https://images.example.com:8443/cat.png'],
            'loopback literal' => ['https://127.0.0.1/x'],
            'private literal' => ['https://10.0.0.1/x'],
            'metadata literal' => ['https://169.254.169.254/latest/meta-data/'],
        ];
    }

    #[DataProvider('privateAddresses')]
    public function testRefusesHostsResolvingToPrivateAddresses(string $ip): void
    {
        $this->dns['images.example.com'] = [$ip];

        try {
            $this->fetcher()->fetch('https://images.example.com/cat.png');
            self::fail('Expected a refusal.');
        } catch (FetchRefused $e) {
            self::assertStringContainsString('not public', $e->getMessage());
            self::assertSame([], $this->calls);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function privateAddresses(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'private 10/8' => ['10.0.0.1'],
            'private 192.168/16' => ['192.168.1.10'],
            'private 172.16/12' => ['172.16.4.4'],
            'metadata' => ['169.254.169.254'],
            'cgnat' => ['100.64.0.1'],
            'ipv6 loopback' => ['::1'],
            'ipv6 unique local' => ['fd00::1'],
            'ipv4-mapped loopback' => ['::ffff:127.0.0.1'],
        ];
    }

    public function testRefusesWhenAnyAnswerIsPrivate(): void
    {
        $this->dns['images.example.com'] = ['93.184.216.34', '10.0.0.5'];
        $this->expectException(FetchRefused::class);

        $this->fetcher()->fetch('https://images.example.com/cat.png');
    }

    public function testRefusesUnresolvableHost(): void
    {
        $this->expectException(FetchRefused::class);
        $this->expectExceptionMessage('resolve');

        $this->fetcher()->fetch('https://nowhere.example.com/cat.png');
    }

    public function testRefusesRedirects(): void
    {
        $this->response = new TransportResponse(302, null, '');
        $this->expectException(FetchRefused::class);
        $this->expectExceptionMessage('redirect');

        $this->fetcher()->fetch('https://images.example.com/cat.png');
    }

    public function testRefusesBodyOverCap(): void
    {
        $this->response = new TransportResponse(200, 'image/png', str_repeat('x', 11));

        try {
            $this->fetcher(10)->fetch('https://images.example.com/cat.png');
            self::fail('Expected a refusal.');
        } catch (FetchRefused $e) {
            self::assertStringContainsString('larger', $e->getMessage());
            self::assertSame(10, $this->calls[0]['maxBytes']);
        }
    }

    private function fetcher(int $max = 1024): SafeFetch
    {
        return new SafeFetch(
            fn (string $host): array => $this->dns[$host] ?? [],
            function (string $url, string $host, string $ip, int $maxBytes): TransportResponse {
                $this->calls[] = compact('url', 'host', 'ip', 'maxBytes');

                return $this->response;
            },
            $max,
        );
    }
}
