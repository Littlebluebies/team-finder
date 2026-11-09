<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

class User extends ActiveRecord implements IdentityInterface
{
    public static function tableName()
    {
        return 'user';
    }

    public static function findIdentity($id)
    {
        return static::findOne($id);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        return static::find()
            ->where(['access_token_hash' => hash('sha256', $token)])
            ->andWhere(['>', 'access_token_expires_at', date('Y-m-d H:i:s')])
            ->one();
    }

    public static function findByEmail($email)
    {
        return static::findOne(['email' => $email]);
    }

    public function getId()
    {
        return (int)$this->id;
    }

    public function getAuthKey()
    {
        return '';
    }

    public function validateAuthKey($authKey)
    {
        return false;
    }

    public function getSkills()
    {
        return $this->hasMany(Skill::class, ['id' => 'skill_id'])
            ->viaTable('user_skill', ['user_id' => 'id']);
    }

    public function issueAccessToken()
    {
        $token = Yii::$app->security->generateRandomString(64);
        $this->access_token_hash = hash('sha256', $token);
        $this->access_token_expires_at = date('Y-m-d H:i:s', time() + 604800);
        return $token;
    }

    public function toAuthArray()
    {
        return [
            'id' => (int)$this->id,
            'email' => $this->email,
            'display_name' => $this->display_name,
            'faculty' => $this->faculty,
            'major' => $this->major,
            'year' => (int)$this->year,
            'line_id' => $this->line_id,
            'line_linked' => $this->line_user_id !== null,
            'line_linked_at' => $this->line_linked_at,
            'bio' => $this->bio,
            'skills' => array_map(static function ($skill) {
                return ['id' => (int)$skill->id, 'name' => $skill->name];
            }, $this->getSkills()->orderBy(['skill.id' => SORT_ASC])->all()),
        ];
    }
}
