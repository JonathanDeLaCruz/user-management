<?php
use yii\helpers\Html;
$this->title = $name;
echo Html::tag('h1', Html::encode($name));
echo Html::tag('p', Html::encode($message));
