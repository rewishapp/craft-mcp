<?php

namespace rewish\craft\mcp\records;

use craft\db\ActiveRecord;

/**
 * @property int         $id
 * @property int         $clientId
 * @property int         $userId
 * @property string      $scope
 * @property null|string $dateRevoked
 */
class OAuthGrantRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%craftmcp_oauth_grants}}';
    }
}
