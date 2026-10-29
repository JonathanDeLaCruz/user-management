<?php

namespace webvimark\modules\UserManagement\models\search;

use webvimark\modules\UserManagement\models\User;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use webvimark\modules\UserManagement\models\UserVisitLog;

/**
 * UserVisitLogSearch represents the model behind the search form about `webvimark\modules\UserManagement\models\UserVisitLog`.
 */
class UserVisitLogSearch extends UserVisitLog
{
	public function rules()
	{
		return [
			[['id'], 'integer'],
			[['token', 'ip', 'language', 'user_id', 'os', 'browser', 'visit_time'], 'string'],
		];
	}

	public function scenarios()
	{
		// bypass scenarios() implementation in the parent class
		return Model::scenarios();
	}

	public function search($params)
	{
		$query = UserVisitLog::find();

		$query->joinWith(['user']);

		// Don't let non-superadmin view superadmin activity
		if ( !\webvimark\modules\UserManagement\models\User::component()->isSuperadmin )
		{
			$query->andWhere([User::tableName() . '.superadmin'=>0]);
		}

		$dataProvider = new ActiveDataProvider([
			'query' => $query,
			'pagination' => [
				'pageSize' => max(10, min(100, (int)Yii::$app->request->cookies->getValue('_grid_page_size', 20))),
			],
			'sort'=>[
				'defaultOrder'=>['id'=> SORT_DESC],
			],
		]);
		$sort = $dataProvider->getSort();
		foreach (array_keys($sort->attributes) as $attribute) {
			$column = $attribute === 'user_id' ? User::tableName() . '.username' : static::tableName() . '.' . $attribute;
			$sort->attributes[$attribute] = ['asc' => [$column => SORT_ASC, static::tableName() . '.id' => SORT_ASC],
				'desc' => [$column => SORT_DESC, static::tableName() . '.id' => SORT_DESC]];
		}

		if (!($this->load($params) && $this->validate())) {
			return $dataProvider;
		}

		\webvimark\modules\UserManagement\components\DateFilter::apply($query, $this, 'visit_time', static::tableName() . '.visit_time');

		$query->andFilterWhere([
			$this->tableName() . '.id' => $this->id,
		]);

	$query->andFilterWhere(['like', User::tableName() . '.username', $this->user_id])
			->andFilterWhere(['like', static::tableName() . '.ip', $this->ip])
			->andFilterWhere(['like', static::tableName() . '.os', $this->os])
			->andFilterWhere(['like', static::tableName() . '.browser', $this->browser])
			->andFilterWhere(['like', static::tableName() . '.language', $this->language]);

		return $dataProvider;
	}
}
