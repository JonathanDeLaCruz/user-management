<?php
namespace webvimark\modules\UserManagement\widgets;
use yii\helpers\Html;
use yii\helpers\Json;
class GridBulkActions extends \yii\base\Widget
{
    public $gridId;
    public $actions = [];
    public static function t($category, $message) { return \webvimark\modules\UserManagement\UserManagementModule::t('back', $message); }
    public function run()
    {
        $options = [];
        foreach ($this->actions as $url => $label) {
            if (is_array($label)) $options += $label; else $options[$url] = $label;
        }
        $grid = Json::htmlEncode($this->gridId); $selector = Json::htmlEncode('#' . $this->id);
        $confirmation = Json::htmlEncode(self::t('back', 'Apply the action to the selected records?'));
        $this->view->registerJs("$(document).off('change', $selector).on('change', $selector, function() { const ids = $('#' + $grid).yiiGridView('getSelectedRows'); const url = this.value; this.value = ''; if (!url || !ids.length) return; yii.confirm($confirmation, function() { const form = $('<form>', {method:'post', action:url}); form.append($('<input>', {type:'hidden', name:yii.getCsrfParam(), value:yii.getCsrfToken()})); ids.forEach(id => form.append($('<input>', {type:'hidden', name:'selection[]', value:id}))); form.appendTo('body').trigger('submit'); }); });");
        return Html::dropDownList(null, '', $options, ['id'=>$this->id,'prompt'=>self::t('back', 'Selected actions'),'class'=>'form-select form-select-sm mt-2']);
    }
}
