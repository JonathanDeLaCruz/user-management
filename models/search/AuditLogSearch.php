<?php
namespace webvimark\modules\UserManagement\models\search;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\Query;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\components\SecurityPresentation;

final class AuditLogSearch extends Model
{
    public $search = '';
    public $username = '';
    public $event = '';
    public $from = '';
    public $to = '';

    public function attributeLabels(): array
    {
        $t = fn($label) => \webvimark\modules\UserManagement\UserManagementModule::t('front', $label);
        return ['search' => $t('Search activity'), 'username' => $t('Username'), 'event' => $t('Event'), 'from' => $t('From date'), 'to' => $t('To date')];
    }

    public function rules(): array
    {
        return [
            [['search', 'username', 'event'], 'trim'],
            [['search', 'username', 'event'], 'string', 'max' => 100],
            [['from', 'to'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = (new Query())->select(['a.*', 'username' => 'actor.username'])
            ->from(['a' => '{{%um_audit_log}}'])
            ->leftJoin(['actor' => User::tableName()], '[[actor.id]] = [[a.actor_id]]');
        $provider = new ActiveDataProvider([
            'query' => $query, 'key' => 'id',
            'pagination' => ['pageSize' => 30, 'pageSizeLimit' => [10, 100]],
            'sort' => ['attributes' => ['created_at' => ['asc' => ['a.created_at' => SORT_ASC, 'a.id' => SORT_ASC], 'desc' => ['a.created_at' => SORT_DESC, 'a.id' => SORT_DESC]]], 'defaultOrder' => ['created_at' => SORT_DESC]],
        ]);
        $this->load($params);
        if (!$this->validate()) {
            $query->andWhere('0=1');
            return $provider;
        }
        $query->andFilterWhere(['like', 'actor.username', $this->username])
            ->andFilterWhere(['a.event' => $this->event]);
        if ($this->search !== '') {
            $events = [];
            foreach (SecurityPresentation::events() as $code => $label) {
                if (mb_stripos($label, $this->search) !== false) $events[] = $code;
            }
            $query->andWhere(['or', ['like', 'actor.username', $this->search], ['like', 'a.ip', $this->search],
                ['like', 'a.target', $this->search], ['a.event' => $events]]);
        }
        $timezone = new \DateTimeZone(\Yii::$app->timeZone);
        if ($this->from !== '') $query->andWhere(['>=', 'a.created_at', (new \DateTimeImmutable($this->from, $timezone))->getTimestamp()]);
        if ($this->to !== '') $query->andWhere(['<', 'a.created_at', (new \DateTimeImmutable($this->to, $timezone))->modify('+1 day')->getTimestamp()]);
        return $provider;
    }
}
