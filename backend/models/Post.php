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

    public function toApiArray($viewer = null, $includeApplication = false)
    {
        $viewerId = $viewer === null ? null : (int)$viewer->id;
        $isOwner = $viewerId !== null && (int)$this->owner_id === $viewerId;

        $application = null;
        if ($includeApplication && $viewerId !== null && !$isOwner) {
            $application = Application::find()
                ->where(['post_id' => $this->id, 'user_id' => $viewerId])
                ->one();
        }

        $owner = [
            'id' => (int)$this->owner->id,
            'display_name' => $this->owner->display_name,
        ];
        if ($application !== null && $application->status === 'accepted') {
            $owner['line_id'] = $this->owner->line_id;
        }

        return [
            'id' => (int)$this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'slots' => (int)$this->slots,
            'deadline' => $this->deadline,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'is_owner' => $isOwner,
            'viewer_application' => $application === null ? null : [
                'id' => (int)$application->id,
                'status' => $application->status,
            ],
            'owner' => $owner,
            'skills' => array_map(static function ($skill) {
                return ['id' => (int)$skill->id, 'name' => $skill->name];
            }, $this->getSkills()->orderBy(['skill.id' => SORT_ASC])->all()),
        ];
    }
}
