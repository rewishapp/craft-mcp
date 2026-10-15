<?php

namespace rewish\craft\mcp\support;

use craft\console\controllers\HelpController;
use yii\base\InvalidArgumentException;
use yii\base\Module;

/**
 * Craft’s command listing, tolerant of a module it cannot read.
 *
 * Yii walks every module’s controller path. A module that defines that alias
 * only for console requests throws during discovery; here that module is named
 * in skipped() and the rest of the list still returns.
 */
final class ConsoleCommands extends HelpController
{
    /** @var list<string> */
    private array $skipped = [];

    /**
     * @return list<string>
     */
    public function skipped(): array
    {
        return array_values(array_unique($this->skipped));
    }

    /**
     * @param Module $module
     *
     * @return list<string>
     */
    protected function getModuleCommands($module): array
    {
        try {
            return parent::getModuleCommands($module);
        } catch (InvalidArgumentException) {
            $id = $module->getUniqueId();
            $this->skipped[] = $id !== '' ? $id : 'app';

            return [];
        }
    }
}
