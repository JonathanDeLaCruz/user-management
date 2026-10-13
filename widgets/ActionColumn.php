<?php
namespace webvimark\modules\UserManagement\widgets;
class ActionColumn extends \yii\grid\ActionColumn
{
    public function init()
    {
        $this->icons = ['eye-open' => '<span aria-hidden="true">↗</span>', 'pencil' => '<span aria-hidden="true">✎</span>', 'trash' => '<span aria-hidden="true">×</span>'];
        $this->buttonOptions = ['class' => 'btn btn-sm btn-outline-secondary'];
        parent::init();
    }
}
