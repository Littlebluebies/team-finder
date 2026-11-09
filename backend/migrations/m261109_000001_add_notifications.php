<?php

use yii\db\Migration;

class m261109_000001_add_notifications extends Migration
{
    public function safeUp()
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->addColumn('user', 'line_user_id', $this->string(64)->null());
        $this->addColumn('user', 'line_linked_at', $this->dateTime()->null());
        $this->addColumn('user', 'line_link_state_hash', $this->char(64)->null());
        $this->addColumn('user', 'line_link_state_expires_at', $this->dateTime()->null());
        $this->createIndex('uq_user_line_user_id', 'user', 'line_user_id', true);

        $this->createTable('notification', [
            'id' => $this->primaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'type' => $this->string(32)->notNull(),
            'application_id' => $this->integer()->unsigned()->notNull(),
            'read_at' => $this->dateTime()->null(),
            'email_sent_at' => $this->dateTime()->null(),
            'line_sent_at' => $this->dateTime()->null(),
            'email_error' => $this->string(255)->null(),
            'line_error' => $this->string(255)->null(),
            'attempts' => $this->tinyInteger()->unsigned()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ], $options);

        $this->createIndex('uq_notification_event', 'notification', ['user_id', 'type', 'application_id'], true);
        $this->createIndex('idx_notification_user', 'notification', ['user_id', 'read_at']);
        $this->addCheck(
            'ck_notification_type',
            'notification',
            "type IN ('application_received','application_accepted','application_rejected')"
        );
        $this->addForeignKey('fk_notification_user', 'notification', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_notification_application', 'notification', 'application_id', 'application', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('notification');
        $this->dropIndex('uq_user_line_user_id', 'user');
        $this->dropColumn('user', 'line_link_state_expires_at');
        $this->dropColumn('user', 'line_link_state_hash');
        $this->dropColumn('user', 'line_linked_at');
        $this->dropColumn('user', 'line_user_id');
    }
}
