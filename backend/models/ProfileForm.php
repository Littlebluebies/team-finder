<?php

namespace app\models;

use Yii;
use yii\base\Model;

class ProfileForm extends Model
{
    use SkillIdsTrait;

    public $display_name;
    public $faculty;
    public $major;
    public $year;
    public $line_id;
    public $bio;
    public $skill_ids = [];

    public function rules()
    {
        $trim = static fn($value) => is_string($value) ? trim($value) : $value;

        return [
            [['display_name', 'faculty', 'major', 'line_id'], 'filter', 'filter' => $trim],
            [['display_name', 'faculty', 'major', 'year', 'line_id', 'skill_ids'], 'required'],
            ['display_name', 'string', 'max' => 100],
            [['faculty', 'major'], 'string', 'max' => 150],
            ['year', 'integer', 'min' => 1, 'max' => 8],
            ['line_id', 'string', 'max' => 50],
            ['bio', 'string', 'max' => 2000],
            ['skill_ids', 'validateSkills', 'skipOnEmpty' => false],
        ];
    }

    public function update(User $user)
    {
        if (!$this->validate()) {
            return null;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $user->display_name = $this->display_name;
            $user->faculty = $this->faculty;
            $user->major = $this->major;
            $user->year = (int)$this->year;
            $user->line_id = $this->line_id;
            $user->bio = $this->bio === '' ? null : $this->bio;

            if (!$user->save(false)) {
                throw new \RuntimeException('Could not update profile.');
            }

            Yii::$app->db->createCommand()->delete('user_skill', ['user_id' => $user->id])->execute();
            $rows = array_map(static fn($skillId) => [$user->id, $skillId], $this->skill_ids);
            Yii::$app->db->createCommand()
                ->batchInsert('user_skill', ['user_id', 'skill_id'], $rows)
                ->execute();

            $transaction->commit();
            return $user;
        } catch (\Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $exception;
        }
    }
}
