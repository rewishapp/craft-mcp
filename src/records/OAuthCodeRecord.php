<?php

namespace rewish\craft\mcp\records;

use craft\db\ActiveRecord;

/**
 * @property int         $id
 * @property string      $codeHash
 * @property int         $grantId
 * @property string      $redirectUri
 * @property string      $codeChallenge
 * @property string      $expiryDate
 * @property null|string $dateUsed
 */
class OAuthCodeRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%craftmcp_oauth_codes}}';
    }
}
