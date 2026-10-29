<?php

namespace webvimark\modules\UserManagement\models\search;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use webvimark\modules\UserManagement\models\User;

/**
 * UserSearch represents the model behind the search form about `webvimark\modules\UserManagement\models\User`.
 */
class UserSearch extends User
{
	public function rules()
	{
		return [
			[['id', 'superadmin', 'status', 'created_at', 'updated_at', 'email_confirmed'], 'integer'],
			[['username', 'gridRoleSearch', 'registration_ip', 'email'], 'string'],
		];
	}

	public function scenarios()
	{
		// bypass scenarios() implementation in the parent class
		return Model::scenarios();
	}

	public function search($params)
	{
		$query = User::find();

		$query->with(['roles']);
		$module = \webvimark\modules\UserManagement\UserManagementModule::instance();
		$roleOrder = (new \yii\db\Query())->select(['assignment.user_id', 'first_role' => new \yii\db\Expression('MIN([[role.description]])')])
			->from(['assignment' => $module->auth_assignment_table])
			->innerJoin(['role' => $module->auth_item_table], '[[role.name]] = [[assignment.item_name]]')
			->where(['role.type' => 1])->groupBy('assignment.user_id');
		$query->select([User::tableName() . '.*'])->leftJoin(['role_order' => $roleOrder], '[[role_order.user_id]] = ' . User::tableName() . '.[[id]]');

		if ( !\webvimark\modules\UserManagement\models\User::component()->isSuperadmin )
		{
			$query->where(['superadmin'=>0]);
		}

		$dataProvider = new ActiveDataProvider([
			'query' => $query,
			'pagination' => [
				'pageSize' => max(10, min(100, (int)Yii::$app->request->cookies->getValue('_grid_page_size', 20))),
			],
			'sort'=>[
				'defaultOrder'=>[
					'id'=>SORT_DESC,
				],
			],
		]);
		$sort = $dataProvider->getSort();
		foreach (array_keys($sort->attributes) as $attribute) {
			$sort->attributes[$attribute] = ['asc' => [User::tableName() . '.' . $attribute => SORT_ASC, User::tableName() . '.id' => SORT_ASC],
				'desc' => [User::tableName() . '.' . $attribute => SORT_DESC, User::tableName() . '.id' => SORT_DESC]];
		}
		$sort->attributes['gridRoleSearch'] = ['asc' => ['role_order.first_role' => SORT_ASC, User::tableName() . '.id' => SORT_ASC],
			'desc' => ['role_order.first_role' => SORT_DESC, User::tableName() . '.id' => SORT_DESC]];

		if (!($this->load($params) && $this->validate())) {
			return $dataProvider;
		}

		if ($this->gridRoleSearch !== null && $this->gridRoleSearch !== '')
		{
			$query->andWhere(['in', User::tableName() . '.id', (new \yii\db\Query())->select('filter_assignment.user_id')
				->from(['filter_assignment' => $module->auth_assignment_table])
				->innerJoin(['filter_role' => $module->auth_item_table], '[[filter_role.name]] = [[filter_assignment.item_name]]')
				->where(['filter_role.type' => 1, 'filter_assignment.item_name' => $this->gridRoleSearch])]);
		}

		$query->andFilterWhere([
			'id' => $this->id,
			'superadmin' => $this->superadmin,
			'status' => $this->status,
			'registration_ip' => $this->registration_ip,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
			'email_confirmed' => $this->email_confirmed,
		]);

	$query->andFilterWhere(['like', 'username', $this->username])
			->andFilterWhere(['like', 'email', $this->email]);

		return $dataProvider;
	}
}
