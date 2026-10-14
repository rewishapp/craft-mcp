<?php

namespace rewish\craft\mcp\events;

use yii\base\Event;

class RegisterClassesEvent extends Event
{
    /** @var list<class-string> */
    public array $classes = [];
}
