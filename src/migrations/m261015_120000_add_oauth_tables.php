<?php

namespace rewish\craft\mcp\migrations;

use craft\db\Migration;
use craft\db\Table;

/**
 * Adds the OAuth connector's tables, and a nullable grantId on craftmcp_tokens
 * so the access tokens an app receives are deleted with its grant.
 *
 * Additive only: no existing table, column or row is altered, so tokens
 * minted before this migration keep working exactly as they did. Every step is
 * guarded, so Install can run this same migration on a fresh install.
 */
class m261015_120000_add_oauth_tables extends Migration
{
    public function safeUp(): bool
    {
        $this->createClients();
        $this->createGrants();
        $this->createCodes();
        $this->createRefreshTokens();

        if ($this->db->getTableSchema('{{%craftmcp_tokens}}', true)?->getColumn('grantId') === null) {
            $this->addColumn('{{%craftmcp_tokens}}', 'grantId', (string) $this->integer()->null()->after('scope'));
            $this->createIndex(null, '{{%craftmcp_tokens}}', ['grantId'], false);
            $this->addForeignKey(null, '{{%craftmcp_tokens}}', ['grantId'], '{{%craftmcp_oauth_grants}}', ['id'], 'CASCADE');
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->getTableSchema('{{%craftmcp_tokens}}', true)?->getColumn('grantId') !== null) {
            // OAuth-issued tokens mean nothing without their grant; hand-minted
            // ones (grantId null) are untouched.
            $this->delete('{{%craftmcp_tokens}}', ['not', ['grantId' => null]]);
            $this->dropForeignKeyIfExists('{{%craftmcp_tokens}}', ['grantId']);
            $this->dropIndexIfExists('{{%craftmcp_tokens}}', ['grantId'], false);
            $this->dropColumn('{{%craftmcp_tokens}}', 'grantId');
        }

        $this->dropTableIfExists('{{%craftmcp_oauth_refresh_tokens}}');
        $this->dropTableIfExists('{{%craftmcp_oauth_codes}}');
        $this->dropTableIfExists('{{%craftmcp_oauth_grants}}');
        $this->dropTableIfExists('{{%craftmcp_oauth_clients}}');

        return true;
    }

    private function createClients(): void
    {
        if ($this->db->tableExists('{{%craftmcp_oauth_clients}}')) {
            return;
        }

        $this->createTable('{{%craftmcp_oauth_clients}}', [
            'id' => $this->primaryKey(),
            'clientId' => $this->string(64)->notNull(),
            'name' => $this->string(100)->notNull(),
            'redirectUris' => $this->text()->notNull(),
            'lastUsedAt' => $this->dateTime()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        $this->createIndex(null, '{{%craftmcp_oauth_clients}}', ['clientId'], true);
    }

    private function createGrants(): void
    {
        if ($this->db->tableExists('{{%craftmcp_oauth_grants}}')) {
            return;
        }

        $this->createTable('{{%craftmcp_oauth_grants}}', [
            'id' => $this->primaryKey(),
            'clientId' => $this->integer()->notNull(),
            'userId' => $this->integer()->notNull(),
            'scope' => $this->string(16)->notNull(),
            'dateRevoked' => $this->dateTime()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        $this->createIndex(null, '{{%craftmcp_oauth_grants}}', ['userId', 'dateRevoked'], false);
        $this->addForeignKey(null, '{{%craftmcp_oauth_grants}}', ['clientId'], '{{%craftmcp_oauth_clients}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%craftmcp_oauth_grants}}', ['userId'], Table::USERS, ['id'], 'CASCADE');
    }

    private function createCodes(): void
    {
        if ($this->db->tableExists('{{%craftmcp_oauth_codes}}')) {
            return;
        }

        $this->createTable('{{%craftmcp_oauth_codes}}', [
            'id' => $this->primaryKey(),
            'codeHash' => $this->string(64)->notNull(),
            'grantId' => $this->integer()->notNull(),
            'redirectUri' => $this->text()->notNull(),
            'codeChallenge' => $this->string(128)->notNull(),
            'expiryDate' => $this->dateTime()->notNull(),
            'dateUsed' => $this->dateTime()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        $this->createIndex(null, '{{%craftmcp_oauth_codes}}', ['codeHash'], true);
        $this->addForeignKey(null, '{{%craftmcp_oauth_codes}}', ['grantId'], '{{%craftmcp_oauth_grants}}', ['id'], 'CASCADE');
    }

    private function createRefreshTokens(): void
    {
        if ($this->db->tableExists('{{%craftmcp_oauth_refresh_tokens}}')) {
            return;
        }

        $this->createTable('{{%craftmcp_oauth_refresh_tokens}}', [
            'id' => $this->primaryKey(),
            'tokenHash' => $this->string(64)->notNull(),
            'grantId' => $this->integer()->notNull(),
            'expiryDate' => $this->dateTime()->notNull(),
            'dateUsed' => $this->dateTime()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        $this->createIndex(null, '{{%craftmcp_oauth_refresh_tokens}}', ['tokenHash'], true);
        $this->addForeignKey(null, '{{%craftmcp_oauth_refresh_tokens}}', ['grantId'], '{{%craftmcp_oauth_grants}}', ['id'], 'CASCADE');
    }
}
