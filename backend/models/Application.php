<?php

namespace app\models;

use yii\db\ActiveRecord;

class Application extends ActiveRecord
{
    public static function tableName()
    {
        return 'application';
    }

    public function rules()
    {
        return [
            [['message'], 'required'],
            ['message', 'string', 'min' => 1],
        ];
    }

    public function getPost()
    {
        return $this->hasOne(Post::class, ['id' => 'post_id']);
    }

    public function getApplicant()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function toApiArray($includeLineId = false)
    {
        $applicant = [
            'id' => (int)$this->applicant->id,
            'display_name' => $this->applicant->display_name,
            'skills' => array_map(static function ($skill) {
                return ['id' => (int)$skill->id, 'name' => $skill->name];
            }, $this->applicant->getSkills()->orderBy(['skill.id' => SORT_ASC])->all()),
        ];
        if ($includeLineId) {
            $applicant['line_id'] = $this->applicant->line_id;
        }

        return [
            'id' => (int)$this->id,
            'post_id' => (int)$this->post_id,
            'status' => $this->status,
            'message' => $this->message,
            'created_at' => $this->created_at,
            'applicant' => $applicant,
        ];
    }

    public function toMyArray()
    {
        $postStatus = $this->post->status;
        if ($postStatus === 'open' && $this->post->deadline < date('Y-m-d')) {
            $postStatus = 'closed';
        }

        $owner = [
            'id' => (int)$this->post->owner->id,
            'display_name' => $this->post->owner->display_name,
        ];
        if ($this->status === 'accepted') {
            $owner['line_id'] = $this->post->owner->line_id;
        }

        return [
            'id' => (int)$this->id,
            'status' => $this->status,
            'message' => $this->message,
            'created_at' => $this->created_at,
            'post' => [
                'id' => (int)$this->post->id,
                'title' => $this->post->title,
                'status' => $postStatus,
                'owner' => $owner,
            ],
        ];
    }
}
