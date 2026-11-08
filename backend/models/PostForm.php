<?php

namespace app\models;

use Yii;
use yii\base\Model;

class PostForm extends Model
{
    use SkillIdsTrait;

    public $title;
    public $description;
    public $category;
    public $slots;
    public $deadline;
    public $skill_ids = [];

    public function rules()
    {
        return [
            [['title', 'description', 'category', 'slots', 'deadline', 'skill_ids'], 'required'],
            ['title', 'filter', 'filter' => static fn($value) => is_string($value) ? trim($value) : $value],
            ['title', 'string', 'min' => 1, 'max' => 200],
            ['description', 'string', 'min' => 1],
            ['category', 'in', 'range' => ['course', 'contest', 'personal']],
            ['slots', 'integer', 'min' => 1, 'max' => 99],
            ['deadline', 'date', 'format' => 'php:Y-m-d'],
            ['skill_ids', 'validateSkills', 'skipOnEmpty' => false],
        ];
    }

    public function create(User $owner)
    {
        if (!$this->validate()) {
            return null;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $post = new Post();
            $post->owner_id = $owner->id;
            $post->title = $this->title;
            $post->description = $this->description;
            $post->category = $this->category;
            $post->slots = (int)$this->slots;
            $post->deadline = $this->deadline;
            $post->status = 'open';

            if (!$post->save(false)) {
                throw new \RuntimeException('Could not create post.');
            }

            $rows = array_map(static fn($skillId) => [$post->id, $skillId], $this->skill_ids);
            Yii::$app->db->createCommand()
                ->batchInsert('post_skill', ['post_id', 'skill_id'], $rows)
                ->execute();

            $transaction->commit();
            return $post;
        } catch (\Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $exception;
        }
    }
}
