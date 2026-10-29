<?php

namespace webvimark\modules\UserManagement\models\rbacDB\search;

use webvimark\modules\UserManagement\models\rbacDB\AuthItemGroup;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * AuthItemGroupSearch represents the model behind the search form about `app\modules\merchant\models\AuthItemGroup`.
 */
class AuthItemGroupSearch extends AuthItemGroup
{
	public function rules()
	{
		return [
			[['code', 'name', 'created_at', 'updated_at'], 'string'],
		];
	}

	public function scenarios()
	{
		// bypass scenarios() implementation in the parent class
		return Model::scenarios();
	}

	public function search($params)
	{
		$query = AuthItemGroup::find();

		$dataProvider = new ActiveDataProvider([
			'query' => $query,
			'pagination' => [
				'pageSize' => max(10, min(100, (int)Yii::$app->request->cookies->getValue('_grid_page_size', 20))),
			],
			'sort'=>[
				'defaultOrder'=>['created_at'=> SORT_DESC],
			],
		]);
		$sort = $dataProvider->getSort();
		foreach (array_keys($sort->attributes) as $attribute) {
			$sort->attributes[$attribute] = ['asc' => [$attribute => SORT_ASC, 'code' => SORT_ASC], 'desc' => [$attribute => SORT_DESC, 'code' => SORT_DESC]];
		}

		if (!($this->load($params) && $this->validate())) {
			return $dataProvider;
		}

		foreach (['created_at', 'updated_at'] as $attribute) \webvimark\modules\UserManagement\components\DateFilter::apply($query, $this, $attribute, static::tableName() . '.' . $attribute);

	$query->andFilterWhere(['like', \webvimark\modules\UserManagement\UserManagementModule::instance()->auth_item_group_table.'.code', $this->code])
			->andFilterWhere(['like', \webvimark\modules\UserManagement\UserManagementModule::instance()->auth_item_group_table.'.name', $this->name]);

		return $dataProvider;
	}
}
