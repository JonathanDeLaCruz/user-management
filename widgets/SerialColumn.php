<?php
namespace webvimark\modules\UserManagement\widgets;

use yii\helpers\Html;
use webvimark\modules\UserManagement\UserManagementModule;

class SerialColumn extends \yii\grid\SerialColumn
{
    public $clearRoute = ['index'];

    protected function renderHeaderCellContent()
    {
        if ($this->grid->filterModel === null || $this->header !== '#') {
            return parent::renderHeaderCellContent();
        }
        $label = UserManagementModule::t('back', 'Clear filters');
        $icon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 4h18l-7 8v5l-4 3v-8Z M17 17l5 5 M22 17l-5 5"/></svg>';
        return Html::a($icon, $this->clearRoute, ['class' => 'btn btn-secondary btn-sm', 'title' => $label, 'aria-label' => $label, 'data-pjax' => '1']);
    }
}
