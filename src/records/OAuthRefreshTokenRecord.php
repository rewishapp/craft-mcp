<?php

namespace rewish\craft\mcp\records;

use craft\db\ActiveRecord;

/**
 * @property int         $id
 * @property string      $tokenHash
 * @property int         $grantId
 * @property string      $expiryDate
 * @property null|string $dateUsed
 */
class OAuthRefreshTokenRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%craftmcp_oauth_refresh_tokens}}';
    }
}
