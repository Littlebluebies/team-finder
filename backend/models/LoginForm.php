<?php

namespace app\models;

use Yii;
use yii\base\Model;

class LoginForm extends Model
{
    public $email;
    public $password;

    public function rules()
    {
        return [
            ['email', 'filter', 'filter' => static fn($value) => is_string($value) ? mb_strtolower(trim($value)) : $value],
            [['email', 'password'], 'required'],
            ['email', 'email'],
            ['email', 'string', 'max' => 190],
            ['password', 'string', 'max' => 72],
        ];
    }

    public function authenticate()
    {
        $user = User::findByEmail($this->email);
        if ($user === null || !Yii::$app->security->validatePassword($this->password, $user->password_hash)) {
            return null;
        }
        return $user;
    }
}
