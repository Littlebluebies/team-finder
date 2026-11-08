<?php

namespace app\models;

use yii\db\ActiveRecord;

class Skill extends ActiveRecord
{
    public static function tableName()
    {
        return 'skill';
    }
}
