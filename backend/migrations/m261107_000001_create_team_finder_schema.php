<?php

use yii\db\Migration;

class m261107_000001_create_team_finder_schema extends Migration
{
    public function safeUp()
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->createTable('user', [
            'id' => $this->primaryKey()->unsigned(),
            'email' => $this->string(190)->notNull(),
            'password_hash' => $this->string(255)->notNull(),
            'display_name' => $this->string(100)->notNull(),
            'faculty' => $this->string(150)->notNull(),
            'major' => $this->string(150)->notNull(),
            'year' => $this->tinyInteger()->unsigned()->notNull(),
            'line_id' => $this->string(50)->notNull(),
            'bio' => $this->text()->null(),
            'access_token_hash' => $this->char(64)->null(),
            'access_token_expires_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ], $options);
        $this->createIndex('uq_user_email', 'user', 'email', true);
        $this->createIndex('uq_user_access_token_hash', 'user', 'access_token_hash', true);
        $this->addCheck('ck_user_year', 'user', 'year BETWEEN 1 AND 8');

        $this->createTable('skill', [
            'id' => $this->primaryKey()->unsigned(),
            'name' => $this->string(100)->notNull(),
        ], $options);
        $this->createIndex('uq_skill_name', 'skill', 'name', true);
        $this->batchInsert('skill', ['id', 'name'], [
            [1, 'ตัดต่อ'],
            [2, 'เขียนเว็บ'],
            [3, 'ถ่ายภาพ'],
            [4, 'UI/UX'],
            [5, 'เขียนคอนเทนต์'],
            [6, 'วิเคราะห์ข้อมูล'],
        ]);

        $this->createTable('post', [
            'id' => $this->primaryKey()->unsigned(),
            'owner_id' => $this->integer()->unsigned()->notNull(),
            'title' => $this->string(200)->notNull(),
            'description' => $this->text()->notNull(),
            'category' => $this->string(20)->notNull(),
            'slots' => $this->smallInteger()->unsigned()->notNull(),
            'deadline' => $this->date()->notNull(),
            'status' => $this->string(10)->notNull()->defaultValue('open'),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ], $options);
        $this->createIndex('idx_post_owner', 'post', 'owner_id');
        $this->addCheck('ck_post_category', 'post', "category IN ('course','contest','personal')");
        $this->addCheck('ck_post_status', 'post', "status IN ('open','closed')");
        $this->addCheck('ck_post_slots', 'post', 'slots > 0');
        $this->addForeignKey('fk_post_owner', 'post', 'owner_id', 'user', 'id', 'RESTRICT', 'CASCADE');

        $this->createTable('user_skill', [
            'user_id' => $this->integer()->unsigned()->notNull(),
            'skill_id' => $this->integer()->unsigned()->notNull(),
            'PRIMARY KEY(user_id, skill_id)',
        ], $options);
        $this->createIndex('idx_user_skill_skill', 'user_skill', 'skill_id');
        $this->addForeignKey('fk_user_skill_user', 'user_skill', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_user_skill_skill', 'user_skill', 'skill_id', 'skill', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('post_skill', [
            'post_id' => $this->integer()->unsigned()->notNull(),
            'skill_id' => $this->integer()->unsigned()->notNull(),
            'PRIMARY KEY(post_id, skill_id)',
        ], $options);
        $this->createIndex('idx_post_skill_skill', 'post_skill', 'skill_id');
        $this->addForeignKey('fk_post_skill_post', 'post_skill', 'post_id', 'post', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_post_skill_skill', 'post_skill', 'skill_id', 'skill', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('application', [
            'id' => $this->primaryKey()->unsigned(),
            'post_id' => $this->integer()->unsigned()->notNull(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'message' => $this->text()->notNull(),
            'status' => $this->string(10)->notNull()->defaultValue('pending'),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ], $options);
        $this->createIndex('uq_application_post_user', 'application', ['post_id', 'user_id'], true);
        $this->createIndex('idx_application_user', 'application', 'user_id');
        $this->addCheck('ck_application_status', 'application', "status IN ('pending','accepted','rejected')");
        $this->addForeignKey('fk_application_post', 'application', 'post_id', 'post', 'id', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('fk_application_user', 'application', 'user_id', 'user', 'id', 'RESTRICT', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('application');
        $this->dropTable('post_skill');
        $this->dropTable('user_skill');
        $this->dropTable('post');
        $this->dropTable('skill');
        $this->dropTable('user');
    }
}
