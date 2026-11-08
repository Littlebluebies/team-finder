<?php

namespace app\models;

use yii\db\ActiveRecord;

class Post extends ActiveRecord
{
    public static function tableName()
    {
        return 'post';
    }

    public function getOwner()
    {
        return $this->hasOne(User::class, ['id' => 'owner_id']);
    }

    public function getSkills()
    {
        return $this->hasMany(Skill::class, ['id' => 'skill_id'])
            ->viaTable('post_skill', ['post_id' => 'id']);
    }

    public function toApiArray()
    {
        return [
            'id' => (int)$this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'slots' => (int)$this->slots,
            'deadline' => $this->deadline,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'owner' => [
                'id' => (int)$this->owner->id,
                'display_name' => $this->owner->display_name,
            ],
            'skills' => array_map(static function ($skill) {
                return ['id' => (int)$skill->id, 'name' => $skill->name];
            }, $this->getSkills()->orderBy(['skill.id' => SORT_ASC])->all()),
        ];
    }
}
