<?php

namespace app\controllers;

use app\models\Skill;
use yii\filters\VerbFilter;
use yii\rest\Controller;

class SkillController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['index' => ['GET']],
            ],
        ]);
    }

    public function actionIndex()
    {
        return array_map(static function ($skill) {
            return ['id' => (int)$skill->id, 'name' => $skill->name];
        }, Skill::find()->orderBy(['id' => SORT_ASC])->all());
    }
}
