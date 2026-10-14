<?php

namespace rewish\craft\mcp\records;

use craft\db\ActiveRecord;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $tokenHash
 * @property string      $tokenPrefix
 * @property int         $userId
 * @property string      $scope
 * @property null|string $sections
 * @property null|string $expiryDate
 * @property null|string $lastUsedAt
 */
class TokenRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%craftmcp_tokens}}';
    }

    /**
     * @return list<string>
     */
    public function sectionHandles(): array
    {
        if ($this->sections === null || $this->sections === '') {
            return [];
        }

        $decoded = json_decode($this->sections, true);

        return \is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
