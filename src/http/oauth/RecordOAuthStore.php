<?php

namespace rewish\craft\mcp\http\oauth;

use craft\helpers\Db;
use craft\helpers\Json;
use rewish\craft\mcp\enums\Scope;
use rewish\craft\mcp\records\OAuthClientRecord;
use rewish\craft\mcp\records\OAuthCodeRecord;
use rewish\craft\mcp\records\OAuthGrantRecord;
use rewish\craft\mcp\records\OAuthRefreshTokenRecord;
use rewish\craft\mcp\records\TokenRecord;
use yii\db\ActiveRecord;

/**
 * OAuthStore backed by the craftmcp_oauth_* tables.
 */
final class RecordOAuthStore implements OAuthStore
{
    public function findClient(string $clientId): ?OAuthClient
    {
        $record = OAuthClientRecord::findOne(['clientId' => $clientId]);

        return $record === null ? null : $this->toClient($record);
    }

    public function findClientById(int $id): ?OAuthClient
    {
        $record = OAuthClientRecord::findOne(['id' => $id]);

        return $record === null ? null : $this->toClient($record);
    }

    public function insertClient(OAuthClient $client): OAuthClient
    {
        $record = new OAuthClientRecord();
        $record->setAttributes([
            'clientId' => $client->clientId,
            'name' => $client->name,
            'redirectUris' => Json::encode($client->redirectUris),
        ], false);
        $this->save($record);

        return $this->toClient($record);
    }

    public function touchClient(int $id, \DateTimeImmutable $when): void
    {
        OAuthClientRecord::updateAll(['lastUsedAt' => Db::prepareDateForDb($when)], ['id' => $id]);
    }

    public function findGrant(int $id): ?Grant
    {
        $record = OAuthGrantRecord::findOne(['id' => $id]);

        return $record === null ? null : $this->toGrant($record);
    }

    public function insertGrant(Grant $grant): Grant
    {
        $record = new OAuthGrantRecord();
        $record->setAttributes([
            'clientId' => $grant->clientRecordId,
            'userId' => $grant->userId,
            'scope' => $grant->scope->value,
        ], false);
        $this->save($record);

        return $this->toGrant($record);
    }

    public function revokeGrant(int $id, \DateTimeImmutable $when): void
    {
        OAuthGrantRecord::updateAll(['dateRevoked' => Db::prepareDateForDb($when)], ['id' => $id, 'dateRevoked' => null]);
    }

    public function liveGrants(?int $userId = null): array
    {
        $query = OAuthGrantRecord::find()->where(['dateRevoked' => null])->orderBy(['id' => \SORT_DESC]);
        if ($userId !== null) {
            $query->andWhere(['userId' => $userId]);
        }

        $grants = [];
        foreach ($query->all() as $record) {
            if ($record instanceof OAuthGrantRecord) {
                $grants[] = $this->toGrant($record);
            }
        }

        return $grants;
    }

    public function insertCode(AuthorizationCode $code, string $hash): void
    {
        $record = new OAuthCodeRecord();
        $record->setAttributes([
            'codeHash' => $hash,
            'grantId' => $code->grantId,
            'redirectUri' => $code->redirectUri,
            'codeChallenge' => $code->codeChallenge,
            'expiryDate' => Db::prepareDateForDb($code->expiryDate),
        ], false);
        $this->save($record);
    }

    public function findCode(string $hash): ?AuthorizationCode
    {
        $record = OAuthCodeRecord::findOne(['codeHash' => $hash]);
        if ($record === null) {
            return null;
        }

        return new AuthorizationCode(
            (int) $record->getAttribute('grantId'),
            (string) $record->getAttribute('redirectUri'),
            (string) $record->getAttribute('codeChallenge'),
            $this->date($record->getAttribute('expiryDate')) ?? new \DateTimeImmutable('@0'),
            $this->date($record->getAttribute('dateUsed')),
        );
    }

    public function markCodeUsed(string $hash, \DateTimeImmutable $when): bool
    {
        return OAuthCodeRecord::updateAll(['dateUsed' => Db::prepareDateForDb($when)], ['codeHash' => $hash, 'dateUsed' => null]) === 1;
    }

    public function insertRefreshToken(RefreshToken $token, string $hash): void
    {
        $record = new OAuthRefreshTokenRecord();
        $record->setAttributes([
            'tokenHash' => $hash,
            'grantId' => $token->grantId,
            'expiryDate' => Db::prepareDateForDb($token->expiryDate),
        ], false);
        $this->save($record);
    }

    public function findRefreshToken(string $hash): ?RefreshToken
    {
        $record = OAuthRefreshTokenRecord::findOne(['tokenHash' => $hash]);
        if ($record === null) {
            return null;
        }

        return new RefreshToken(
            (int) $record->getAttribute('grantId'),
            $this->date($record->getAttribute('expiryDate')) ?? new \DateTimeImmutable('@0'),
            $this->date($record->getAttribute('dateUsed')),
        );
    }

    public function markRefreshTokenUsed(string $hash, \DateTimeImmutable $when): bool
    {
        return OAuthRefreshTokenRecord::updateAll(['dateUsed' => Db::prepareDateForDb($when)], ['tokenHash' => $hash, 'dateUsed' => null]) === 1;
    }

    /**
     * Expired codes and refresh tokens, expired OAuth access tokens, revoked
     * grants (cascading to what hangs off them), and registrations nobody has
     * used: open registration means any caller can add a row, so a client with
     * no live grant and no use since the cutoff is dropped.
     */
    public function collectGarbage(\DateTimeImmutable $now, \DateTimeImmutable $unusedClientsBefore): int
    {
        $nowDb = Db::prepareDateForDb($now);
        $cutoff = Db::prepareDateForDb($unusedClientsBefore);
        $removed = OAuthCodeRecord::deleteAll(['<', 'expiryDate', $nowDb]);
        $removed += OAuthRefreshTokenRecord::deleteAll(['<', 'expiryDate', $nowDb]);
        $removed += TokenRecord::deleteAll(['and', ['not', ['grantId' => null]], ['<', 'expiryDate', $nowDb]]);
        $removed += OAuthGrantRecord::deleteAll(['not', ['dateRevoked' => null]]);

        $live = OAuthGrantRecord::find()->select(['clientId'])->where(['dateRevoked' => null])->column();
        $stale = ['and', ['<', 'dateCreated', $cutoff], ['or', ['lastUsedAt' => null], ['<', 'lastUsedAt', $cutoff]]];

        return $removed + OAuthClientRecord::deleteAll($live === [] ? $stale : ['and', $stale, ['not in', 'id', $live]]);
    }

    private function save(ActiveRecord $record): void
    {
        if (!$record->save(false)) {
            throw new \RuntimeException('Failed to persist an OAuth record');
        }
    }

    private function toClient(OAuthClientRecord $record): OAuthClient
    {
        $uris = Json::decodeIfJson((string) $record->getAttribute('redirectUris'));

        return new OAuthClient(
            (string) $record->getAttribute('clientId'),
            (string) $record->getAttribute('name'),
            \is_array($uris) ? array_values(array_filter($uris, is_string(...))) : [],
            (int) $record->getAttribute('id'),
            $this->date($record->getAttribute('dateCreated')),
            $this->date($record->getAttribute('lastUsedAt')),
        );
    }

    private function toGrant(OAuthGrantRecord $record): Grant
    {
        return new Grant(
            (int) $record->getAttribute('clientId'),
            (int) $record->getAttribute('userId'),
            Scope::from((string) $record->getAttribute('scope')),
            (int) $record->getAttribute('id'),
            $this->date($record->getAttribute('dateCreated')),
            $this->date($record->getAttribute('dateRevoked')),
        );
    }

    /**
     * Craft stores dates in UTC, and PHP's default timezone is the site's, so
     * a stored date read without a zone shifts by the site's offset: on a
     * UTC+2 site a 60-second code would expire two hours before it was made.
     */
    private function date(mixed $value): ?\DateTimeImmutable
    {
        return \is_string($value) && $value !== '' ? new \DateTimeImmutable($value, new \DateTimeZone('UTC')) : null;
    }
}
