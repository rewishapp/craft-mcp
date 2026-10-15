<?php

namespace rewish\craft\mcp\records;

use craft\db\ActiveRecord;

/**
 * @property int         $id
 * @property string      $clientId
 * @property string      $name
 * @property string      $redirectUris
 * @property null|string $lastUsedAt
 */
class OAuthClientRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%craftmcp_oauth_clients}}';
    }
}
