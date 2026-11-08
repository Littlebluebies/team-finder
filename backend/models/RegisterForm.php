<?php

namespace app\models;

use Yii;
use yii\base\Model;

class RegisterForm extends Model
{
    public $email;
    public $password;
    public $display_name;
    public $faculty;
    public $major;
    public $year;
    public $line_id;
    public $skill_ids = [];

    public function rules()
    {
        $trim = static fn($value) => is_string($value) ? trim($value) : $value;

        return [
            ['email', 'filter', 'filter' => static fn($value) => is_string($value) ? mb_strtolower(trim($value)) : $value],
            [['display_name', 'faculty', 'major', 'line_id'], 'filter', 'filter' => $trim],
            [[
                'email', 'password', 'display_name', 'faculty', 'major',
                'year', 'line_id', 'skill_ids',
            ], 'required'],
            ['email', 'email'],
            ['email', 'string', 'max' => 190],
            ['email', 'unique', 'targetClass' => User::class, 'targetAttribute' => 'email'],
            ['password', 'string', 'min' => 8, 'max' => 72],
            ['display_name', 'string', 'max' => 100],
            [['faculty', 'major'], 'string', 'max' => 150],
            ['year', 'integer', 'min' => 1, 'max' => 8],
            ['line_id', 'string', 'max' => 50],
            ['skill_ids', 'validateSkills', 'skipOnEmpty' => false],
        ];
    }

    public function validateSkills($attribute)
    {
        if (!is_array($this->$attribute) || $this->$attribute === []) {
            $this->addError($attribute, 'Select at least one skill.');
            return;
        }

        $values = $this->$attribute;
        foreach ($values as $value) {
            if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1) {
                $this->addError($attribute, 'Skill IDs must be positive integers.');
                return;
            }
        }

        $ids = array_map('intval', $values);
        if (count(array_unique($ids)) !== count($ids)) {
            $this->addError($attribute, 'Skill IDs must not contain duplicates.');
            return;
        }

        if ((int)Skill::find()->where(['id' => $ids])->count() !== count($ids)) {
            $this->addError($attribute, 'One or more skills do not exist.');
            return;
        }

        $this->$attribute = array_values($ids);
    }

    public function register()
    {
        if (!$this->validate()) {
            return null;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $user = new User();
            $user->email = $this->email;
            $user->display_name = $this->display_name;
            $user->faculty = $this->faculty;
            $user->major = $this->major;
            $user->year = (int)$this->year;
            $user->line_id = $this->line_id;
            $user->password_hash = Yii::$app->security->generatePasswordHash($this->password);
            $token = $user->issueAccessToken();

            if (!$user->save(false)) {
                throw new \RuntimeException('Could not create user.');
            }

            $rows = array_map(static fn($skillId) => [$user->id, $skillId], $this->skill_ids);
            Yii::$app->db->createCommand()
                ->batchInsert('user_skill', ['user_id', 'skill_id'], $rows)
                ->execute();

            $transaction->commit();
            return ['token' => $token, 'user' => $user->toAuthArray()];
        } catch (\Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $exception;
        }
    }
}
