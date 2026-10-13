<?php
namespace webvimark\modules\UserManagement\widgets;

class ActionColumn extends \yii\grid\ActionColumn
{
    protected function initDefaultButton($name, $iconName, $additionalOptions = [])
    {
        $colors = ['view' => 'success', 'update' => 'primary', 'delete' => 'danger'];
        $additionalOptions['class'] = 'btn btn-sm btn-' . ($colors[$name] ?? 'secondary') . ' um-action';
        parent::initDefaultButton($name, $iconName, $additionalOptions);
    }
}
