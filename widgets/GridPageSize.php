<?php
namespace webvimark\modules\UserManagement\widgets;
use Yii;
use yii\helpers\Html;
use webvimark\modules\UserManagement\UserManagementModule;
class GridPageSize extends \yii\base\Widget
{
    public $pjaxId;
    public function run()
    {
        return Html::beginForm(['grid-page-size'], 'post', ['class' => 'd-flex gap-2 align-items-center justify-content-end'])
            . Html::label(Html::encode(UserManagementModule::t('back', 'Per page')), $this->id)
            . Html::dropDownList('pageSize', Yii::$app->request->cookies->getValue('_grid_page_size', 20), [10=>10,20=>20,50=>50,100=>100], ['id' => $this->id, 'class'=>'form-select form-select-sm w-auto'])
            . Html::submitButton(UserManagementModule::t('back', 'Apply'), ['class'=>'btn btn-sm btn-outline-secondary']) . Html::endForm();
    }
}
