<?php

namespace rewish\craft\mcp\services;

use craft\elements\User;
use rewish\craft\mcp\enums\Scope;
use rewish\craft\mcp\Mcp;
use rewish\craft\mcp\records\TokenRecord;

final class Tokens
{
    /**
     * @param list<string> $sections
     *
     * @return array{plain: string, record: TokenRecord}
     */
    public function issue(int $userId, string $name, string $scope, ?string $expiryDate, array $sections): array
    {
        $scopeEnum = Scope::try($scope);
        if ($scopeEnum === null) {
            throw new \InvalidArgumentException('Unknown token scope.');
        }

        if (\in_array($scopeEnum->value, Mcp::settings()->disabledScopes, true)) {
            throw new \InvalidArgumentException('That scope cannot be minted on this install.');
        }

        $plain = 'cmcp_'.bin2hex(random_bytes(24));
        $record = new TokenRecord();
        $record->name = $name;
        $record->tokenHash = hash('sha256', $plain);
        $record->tokenPrefix = substr($plain, 0, 12);
        $record->userId = $userId;
        $record->scope = $scopeEnum->value;
        $record->sections = $sections === [] ? null : json_encode(array_values($sections));
        $record->expiryDate = $expiryDate;
        $record->save(false);

        return ['plain' => $plain, 'record' => $record];
    }

    /**
     * Issue the short-lived access token an OAuth grant hands to an app. It
     * belongs to the grant (deleted with it), covers every section, and is
     * hidden from the Tokens screen.
     *
     * @return array{plaintext: string, record: TokenRecord}
     */
    public function issueOAuthAccessToken(int $userId, Scope $scope, string $name, \DateTimeImmutable $expiresAt, int $grantId): array
    {
        $plaintext = 'cmcp_'.bin2hex(random_bytes(24));
        $record = new TokenRecord();
        $record->name = $name;
        $record->tokenHash = hash('sha256', $plaintext);
        $record->tokenPrefix = substr($plaintext, 0, 12);
        $record->userId = $userId;
        $record->scope = $scope->value;
        $record->sections = null;
        $record->grantId = $grantId;
        $record->expiryDate = $expiresAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $record->save(false);

        return ['plaintext' => $plaintext, 'record' => $record];
    }

    public function authenticate(string $plain): ?TokenRecord
    {
        $record = TokenRecord::find()->where(['tokenHash' => hash('sha256', $plain)])->one();
        if (!$record instanceof TokenRecord) {
            return null;
        }

        if ($record->expiryDate !== null && self::isExpiredUtc((string) $record->expiryDate)) {
            return null;
        }

        if (\in_array($record->scope, Mcp::settings()->disabledScopes, true)) {
            return null;
        }

        $record->lastUsedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        try {
            $record->save(false);
        } catch (\Throwable) {
        }

        return $record;
    }

    public function revoke(int $id): void
    {
        $record = TokenRecord::find()->where(['id' => $id])->one();
        if ($record instanceof TokenRecord) {
            $record->delete();
        }
    }

    /** Delete every access token an OAuth grant issued. */
    public function revokeGrant(int $grantId): void
    {
        TokenRecord::deleteAll(['grantId' => $grantId]);
    }

    /**
     * Hand-minted tokens only: access tokens issued through OAuth belong to a
     * grant and are managed as connected apps, not on the Tokens screen.
     *
     * @return list<TokenRecord>
     */
    public function all(?int $userId = null): array
    {
        $query = TokenRecord::find()->where(['grantId' => null])->orderBy(['dateCreated' => \SORT_DESC]);
        if ($userId !== null) {
            $query->andWhere(['userId' => $userId]);
        }

        return $query->all();
    }

    public function userCanMint(string $scope, User $user): bool
    {
        if ($scope === Scope::Full->value && !$user->admin) {
            return false;
        }

        return $user->can('craft-mcp:manageTokens') || $user->admin;
    }

    /**
     * Craft stores datetime columns as UTC. Read them that way so a site timezone
     * offset cannot shift expiry earlier or later by several hours.
     */
    private static function isExpiredUtc(string $expiryDate): bool
    {
        $utc = new \DateTimeZone('UTC');
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $expiryDate, $utc);
        if ($parsed === false) {
            try {
                $parsed = new \DateTimeImmutable($expiryDate, $utc);
            } catch (\Throwable) {
                return true;
            }
        }

        return $parsed->getTimestamp() < time();
    }
}
