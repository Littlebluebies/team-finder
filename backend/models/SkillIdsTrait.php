<?php

namespace app\models;

use Yii;

trait SkillIdsTrait
{
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
}
