<?php

namespace rewish\craft\mcp\enums;

enum Scope: string
{
    case Readonly = 'readonly';
    case Content = 'content';
    case Full = 'full';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @param list<string> $disabledScopes
     */
    public function isDisabled(array $disabledScopes): bool
    {
        return \in_array($this->value, $disabledScopes, true);
    }

    public static function try(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
