<?php

namespace rewish\craft\mcp\records;

use craft\db\ActiveRecord;

/**
 * @property int         $id
 * @property null|int    $userId
 * @property null|int    $tokenId
 * @property string      $tool
 * @property string      $transport
 * @property bool        $success
 * @property int         $durationMs
 * @property null|string $message
 */
class ActivityRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%craftmcp_activity}}';
    }
}
