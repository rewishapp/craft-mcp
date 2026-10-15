<?php

namespace rewish\craft\mcp\tests;

use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\TestCase;
use rewish\craft\mcp\support\UserWriteRules;
use rewish\craft\mcp\tools\ContentTools;

/**
 * @coversNothing
 */
final class UserWriteRulesTest extends TestCase
{
    public function testPlainOtherAccountMayChange(): void
    {
        UserWriteRules::assertMayChange('jane', false, 7, 1);
        UserWriteRules::assertMayChange('jane', false, 7, null);

        $this->addToAssertionCount(1);
    }

    public function testAdminIsRefused(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('admin');

        UserWriteRules::assertMayChange('root', true, 7, 1);
    }

    public function testOwnAccountIsRefused(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('your own account');

        UserWriteRules::assertMayChange('me', false, 1, 1);
    }

    public function testEditionCap(): void
    {
        UserWriteRules::assertRoomForUser('Team', 5, 4);
        UserWriteRules::assertRoomForUser('Pro', null, 900);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Craft Solo allows 1 user account');

        UserWriteRules::assertRoomForUser('Solo', 1, 1);
    }

    public function testGroupsNeedPro(): void
    {
        UserWriteRules::assertGroupsAvailable('Pro', true);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('User groups need Craft Pro');

        UserWriteRules::assertGroupsAvailable('Team', false);
    }

    public function testNoUserWriteTakesAdminPasswordOrNewEmail(): void
    {
        $offending = [];
        foreach (['createUser', 'updateUser', 'setUserStatus'] as $method) {
            foreach ((new \ReflectionMethod(ContentTools::class, $method))->getParameters() as $parameter) {
                $name = strtolower($parameter->getName());
                if (\in_array($name, UserWriteRules::FORBIDDEN_PARAMETERS, true) || ($name === 'email' && $method !== 'createUser')) {
                    $offending[] = "{$method}({$parameter->getName()})";
                }
            }
        }

        self::assertSame([], $offending);
    }
}
