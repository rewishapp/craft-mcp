<?php

namespace rewish\craft\mcp\http;

use craft\db\Query;
use craft\helpers\Db;
use Mcp\Server\Session\SessionStoreInterface;
use Symfony\Component\Uid\Uuid;

final class DbSessionStore implements SessionStoreInterface
{
    private const TABLE = '{{%craftmcp_sessions}}';

    public function __construct(private readonly int $ttl = 3600) {}

    public function exists(Uuid $id): bool
    {
        return $this->read($id) !== false;
    }

    public function read(Uuid $id): false|string
    {
        $data = (new Query())
            ->select(['data'])
            ->from(self::TABLE)
            ->where(['id' => $id->toRfc4122()])
            ->andWhere(['>=', 'dateUpdated', $this->oldest()])
            ->scalar()
        ;

        return \is_string($data) ? $data : false;
    }

    public function write(Uuid $id, string $data): bool
    {
        $now = Db::prepareDateForDb(new \DateTimeImmutable());
        \Craft::$app->getDb()->createCommand()->upsert(self::TABLE, [
            'id' => $id->toRfc4122(),
            'data' => $data,
            'dateCreated' => $now,
            'dateUpdated' => $now,
        ], [
            'data' => $data,
            'dateUpdated' => $now,
        ])->execute();

        return true;
    }

    public function destroy(Uuid $id): bool
    {
        return \Craft::$app->getDb()->createCommand()->delete(self::TABLE, ['id' => $id->toRfc4122()])->execute() > 0;
    }

    public function gc(): array
    {
        $expired = (new Query())
            ->select(['id'])
            ->from(self::TABLE)
            ->where(['<', 'dateUpdated', $this->oldest()])
            ->column()
        ;

        if ($expired !== []) {
            \Craft::$app->getDb()->createCommand()->delete(self::TABLE, ['id' => $expired])->execute();
        }

        return array_map(Uuid::fromString(...), array_filter($expired, 'is_string'));
    }

    private function oldest(): string
    {
        return Db::prepareDateForDb((new \DateTimeImmutable())->modify(\sprintf('-%d seconds', $this->ttl)));
    }
}
