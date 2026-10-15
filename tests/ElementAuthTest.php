<?php

namespace rewish\craft\mcp\tests;

use craft\base\ElementInterface;
use PHPUnit\Framework\TestCase;
use rewish\craft\mcp\support\CallContext;
use rewish\craft\mcp\support\ElementAuth;

/**
 * @coversNothing
 */
final class ElementAuthTest extends TestCase
{
    protected function tearDown(): void
    {
        CallContext::reset();
        parent::tearDown();
    }

    public function testStdioWithoutUserIsNoOp(): void
    {
        CallContext::reset('stdio');
        $element = $this->createMock(ElementInterface::class);
        ElementAuth::assertCanSave($element);
        ElementAuth::assertCanDelete($element);
        $this->addToAssertionCount(1);
    }
}
