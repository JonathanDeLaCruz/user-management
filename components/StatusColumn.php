<?php
namespace webvimark\modules\UserManagement\components;
use yii\helpers\Html;
use webvimark\modules\UserManagement\UserManagementModule;
class StatusColumn extends \yii\grid\DataColumn
{
    public $optionsArray = [];
    public function init()
    {
        if ($this->filter === null) {
            $this->filter = $this->optionsArray
                ? array_column($this->optionsArray, 1, 0)
                : [0 => \Yii::t('yii', 'No'), 1 => \Yii::t('yii', 'Yes')];
        }
        $this->filterInputOptions['class'] = trim('form-select ' . str_replace('form-control', '', $this->filterInputOptions['class'] ?? ''));
        $this->filterInputOptions += ['prompt' => UserManagementModule::t('back', 'All')];
        parent::init();
    }
    protected function renderDataCellContent($model, $key, $index)
    {
        $value = $model->{$this->attribute};
        foreach ($this->optionsArray as [$status, $label, $color]) {
            if ((string)$status === (string)$value) return Html::tag('span', Html::encode($label), ['class' => 'badge text-bg-' . $color]);
        }
        return Html::tag('span', Html::encode(\Yii::t('yii', $value ? 'Yes' : 'No')), ['class' => 'badge text-bg-' . ($value ? 'success' : 'secondary')]);
    }
}
