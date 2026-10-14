<?php

namespace rewish\craft\mcp\events;

use yii\base\Event;

class FieldTranslatorsEvent extends Event
{
    /**
     * Field class name => callable(object $field, mixed $value, string $direction): mixed
     * Direction is "read" or "write".
     *
     * @var array<class-string, callable>
     */
    public array $translators = [];
}
