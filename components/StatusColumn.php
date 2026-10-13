<?php
namespace webvimark\modules\UserManagement\components;
use yii\helpers\Html;
class StatusColumn extends \yii\grid\DataColumn
{
    public $optionsArray = [];
    protected function renderDataCellContent($model, $key, $index)
    {
        $value = $model->{$this->attribute};
        foreach ($this->optionsArray as [$status, $label, $color]) {
            if ((string)$status === (string)$value) return Html::tag('span', Html::encode($label), ['class' => 'badge text-bg-' . $color]);
        }
        return Html::tag('span', $value ? 'Sí' : 'No', ['class' => 'badge text-bg-' . ($value ? 'success' : 'secondary')]);
    }
}
