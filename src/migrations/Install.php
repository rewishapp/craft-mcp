<?php

namespace rewish\craft\mcp\migrations;

use craft\db\Migration;
use craft\db\Table;

class Install extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%craftmcp_tokens}}')) {
            $this->createTable('{{%craftmcp_tokens}}', [
                'id' => $this->primaryKey(),
                'name' => $this->string()->notNull(),
                'tokenHash' => $this->string(64)->notNull(),
                'tokenPrefix' => $this->string(16)->notNull(),
                'userId' => $this->integer()->notNull(),
                'scope' => $this->string(16)->notNull(),
                'sections' => $this->text()->null(),
                'expiryDate' => $this->dateTime()->null(),
                'lastUsedAt' => $this->dateTime()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
            $this->createIndex(null, '{{%craftmcp_tokens}}', ['tokenHash'], true);
            $this->addForeignKey(null, '{{%craftmcp_tokens}}', ['userId'], Table::USERS, ['id'], 'CASCADE', 'CASCADE');
        }

        if (!$this->db->tableExists('{{%craftmcp_sessions}}')) {
            $this->createTable('{{%craftmcp_sessions}}', [
                'id' => $this->string(36)->notNull(),
                'data' => $this->mediumText()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
            ]);
            $this->addPrimaryKey(null, '{{%craftmcp_sessions}}', ['id']);
        }

        if (!$this->db->tableExists('{{%craftmcp_activity}}')) {
            $this->createTable('{{%craftmcp_activity}}', [
                'id' => $this->primaryKey(),
                'userId' => $this->integer()->null(),
                'tokenId' => $this->integer()->null(),
                'tool' => $this->string()->notNull(),
                'transport' => $this->string(16)->notNull(),
                'success' => $this->boolean()->notNull()->defaultValue(false),
                'durationMs' => $this->integer()->notNull()->defaultValue(0),
                'message' => $this->text()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
            $this->createIndex(null, '{{%craftmcp_activity}}', ['tool'], false);
            $this->createIndex(null, '{{%craftmcp_activity}}', ['dateCreated'], false);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%craftmcp_activity}}');
        $this->dropTableIfExists('{{%craftmcp_sessions}}');
        $this->dropTableIfExists('{{%craftmcp_tokens}}');

        return true;
    }
}
