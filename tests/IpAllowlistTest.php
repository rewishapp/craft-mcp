<?php

namespace rewish\craft\mcp\tests;

use PHPUnit\Framework\TestCase;
use rewish\craft\mcp\support\IpAllowlist;

/**
 * @coversNothing
 */
final class IpAllowlistTest extends TestCase
{
    public function testEmptyListAllowsAnyone(): void
    {
        self::assertTrue(IpAllowlist::allows('203.0.113.10', []));
    }

    public function testExactIpv4AndIpv6(): void
    {
        self::assertTrue(IpAllowlist::allows('127.0.0.1', ['127.0.0.1']));
        self::assertFalse(IpAllowlist::allows('127.0.0.2', ['127.0.0.1']));
        self::assertTrue(IpAllowlist::allows('::1', ['::1']));
        self::assertFalse(IpAllowlist::allows('::2', ['::1']));
    }

    public function testCidrIpv4(): void
    {
        self::assertTrue(IpAllowlist::allows('160.79.104.10', ['160.79.104.0/21']));
        self::assertTrue(IpAllowlist::allows('160.79.111.255', ['160.79.104.0/21']));
        self::assertFalse(IpAllowlist::allows('160.79.112.1', ['160.79.104.0/21']));
        self::assertFalse(IpAllowlist::allows('not-an-ip', ['160.79.104.0/21']));
        self::assertFalse(IpAllowlist::allows('160.79.104.10', ['not-a-cidr']));
    }

    public function testCidrIpv6(): void
    {
        self::assertTrue(IpAllowlist::allows('2001:db8::1', ['2001:db8::/32']));
        self::assertFalse(IpAllowlist::allows('2001:db9::1', ['2001:db8::/32']));
    }
}
