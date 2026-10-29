<?php
namespace routefixtures\mapped;

final class MappedController extends \yii\web\Controller
{
    public $label;
    public static array $labels = [];
    public function init() { parent::init(); self::$labels[] = $this->label; }
    public function actionMapped() { throw new \RuntimeException('Discovery executed a mapped action.'); }
}
