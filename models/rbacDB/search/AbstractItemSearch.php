<?php

namespace webvimark\modules\UserManagement\models\rbacDB\search;

use webvimark\modules\UserManagement\models\rbacDB\AbstractItem;
use webvimark\modules\UserManagement\models\rbacDB\Permission;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;

abstract class AbstractItemSearch extends AbstractItem
{
	public function rules()
	{
		return [
			[['name', 'description', 'group_code'], 'string'],
		];
	}

	public function scenarios()
	{
		// bypass scenarios() implementation in the parent class
		return Model::scenarios();
	}

	public function search($params)
	{
		$query = ( static::ITEM_TYPE == static::TYPE_ROLE ) ? Role::find() : Permission::find();

		$query->joinWith(['group']);

		$dataProvider = new ActiveDataProvider([
			'query' => $query,
			'pagination' => [
				'pageSize' => \max(10, min(100, (int)Yii::$app->request->cookies->getValue('_grid_page_size', 20))),
			],
			'sort'=>[
				'defaultOrder'=>[
					'created_at'=>SORT_DESC,
				],
			],
		]);
		$sort = $dataProvider->getSort();
		$table = \webvimark\modules\UserManagement\UserManagementModule::instance()->auth_item_table;
		foreach (array_keys($sort->attributes) as $attribute) {
			$column = $attribute === 'group_code' ? \webvimark\modules\UserManagement\UserManagementModule::instance()->auth_item_group_table . '.name' : $table . '.' . $attribute;
			$sort->attributes[$attribute] = ['asc' => [$column => SORT_ASC, $table . '.name' => SORT_ASC],
				'desc' => [$column => SORT_DESC, $table . '.name' => SORT_DESC]];
		}

		if (!($this->load($params) && $this->validate())) {
			return $dataProvider;
		}

	$query->andFilterWhere(['like', \webvimark\modules\UserManagement\UserManagementModule::instance()->auth_item_table.'.name', $this->name])
			->andFilterWhere(['like', \webvimark\modules\UserManagement\UserManagementModule::instance()->auth_item_table.'.description', $this->description])
			->andFilterWhere([\webvimark\modules\UserManagement\UserManagementModule::instance()->auth_item_table.'.group_code'=>$this->group_code]);

		return $dataProvider;
	}
}
