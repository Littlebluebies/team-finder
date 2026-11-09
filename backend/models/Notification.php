<?php

namespace app\models;

use yii\db\ActiveRecord;

class Notification extends ActiveRecord
{
    public const TYPE_RECEIVED = 'application_received';
    public const TYPE_ACCEPTED = 'application_accepted';
    public const TYPE_REJECTED = 'application_rejected';

    public static function tableName()
    {
        return 'notification';
    }

    public function getApplication()
    {
        return $this->hasOne(Application::class, ['id' => 'application_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function toApiArray()
    {
        $application = $this->application;
        $post = $application->post;
        $actor = $this->type === self::TYPE_RECEIVED
            ? $application->applicant
            : $post->owner;

        return [
            'id' => (int)$this->id,
            'type' => $this->type,
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at,
            'post' => [
                'id' => (int)$post->id,
                'title' => $post->title,
            ],
            'actor' => [
                'display_name' => $actor->display_name,
            ],
        ];
    }
}
