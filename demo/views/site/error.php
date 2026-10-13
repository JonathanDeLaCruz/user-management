<?php
use yii\helpers\Html;
$this->title = $name;
?>
<div class="card um-card"><div class="card-body"><h1><?= Html::encode($name) ?></h1><p><?= nl2br(Html::encode($message)) ?></p><?= Html::a('Volver al panel',['/site/index'],['class'=>'btn btn-primary']) ?></div></div>
