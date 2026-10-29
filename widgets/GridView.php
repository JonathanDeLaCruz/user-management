<?php
namespace webvimark\modules\UserManagement\widgets;

use Yii;

/** Keep host query parameters while resetting pagination when filters change. */
class GridView extends \yii\grid\GridView
{
    public function init()
    {
        if ($this->filterUrl === null) $this->filterUrl = $this->queryUrl(false);
        parent::init();
        foreach ($this->columns as $column) {
            if ($column instanceof \yii\grid\DataColumn && $column->attribute !== null && $this->filterModel !== null) {
                $column->filterInputOptions += ['aria-label' => $column->label ?? $this->filterModel->getAttributeLabel($column->attribute)];
            }
        }
    }

    public function queryUrl(bool $clearFilters = true): array
    {
        $params = Yii::$app->request->queryParams;
        $pagination = $this->dataProvider->getPagination();
        if ($pagination) unset($params[$pagination->pageParam]);
        unset($params['_pjax']);
        if ($clearFilters && $this->filterModel !== null) {
            $name = $this->filterModel->formName();
            if ($name !== '') unset($params[$name]);
            else foreach ($this->filterModel->safeAttributes() as $attribute) unset($params[$attribute]);
        }
        // 'r' is Yii's route in applications without pretty URLs.
        unset($params['r']);
        return ['/' . Yii::$app->controller->getRoute()] + $params;
    }
}
