<?php
namespace webvimark\modules\UserManagement\models\search;

use yii\base\Model;
use yii\data\ActiveDataProvider;

final class AuditLogSearch extends Model
{
    public $search = '';
    public $username = '';
    public $event = '';
    public $from = '';
    public $to = '';
    public $created_at = '';
    public $target = '';
    public $ip = '';
    public $details = '';

    public function attributeLabels(): array
    {
        $t = fn($label) => \webvimark\modules\UserManagement\UserManagementModule::t('front', $label);
        return ['search' => $t('Search activity'), 'username' => $t('Username'), 'event' => $t('Event'), 'from' => $t('From date'), 'to' => $t('To date'),
            'created_at' => $t('Date'), 'target' => $t('Target'), 'ip' => 'IP', 'details' => $t('Details')];
    }

    public function rules(): array
    {
        return [
            [['search', 'username', 'event', 'target', 'ip', 'details'], 'trim'],
            [['search', 'username', 'event', 'target', 'ip', 'details'], 'string', 'max' => 255],
            [['from', 'to', 'created_at'], 'string'],
            [['from', 'to'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = (new \webvimark\modules\UserManagement\components\AuditDisplayQuery())->query();
        $sort = [];
        foreach (['created_at' => 'created_at', 'username' => 'display_username', 'event' => 'display_event', 'target' => 'display_target', 'ip' => 'ip', 'details' => 'display_details'] as $attribute => $column) {
            $sort[$attribute] = ['asc' => [$column => SORT_ASC, 'id' => SORT_ASC], 'desc' => [$column => SORT_DESC, 'id' => SORT_DESC]];
        }
        $provider = new ActiveDataProvider([
            'query' => $query, 'key' => 'id',
            'pagination' => ['pageSize' => 30, 'pageSizeLimit' => [10, 100]],
            'sort' => ['attributes' => $sort, 'defaultOrder' => ['created_at' => SORT_DESC]],
        ]);
        $this->load($params);
        if (!$this->validate()) {
            $query->andWhere('0=1');
            return $provider;
        }
        foreach (['username', 'target', 'details'] as $attribute) $query->andFilterWhere(['like', 'display_' . $attribute, $this->$attribute]);
        $query->andFilterWhere(['like', 'ip', $this->ip])->andFilterWhere(['event' => $this->event]);
        if ($this->search !== '') {
            $or = ['or'];
            foreach (['display_username', 'display_event', 'display_target', 'display_details', 'ip'] as $column) $or[] = ['like', $column, $this->search];
            $query->andWhere($or);
        }
        \webvimark\modules\UserManagement\components\DateFilter::apply($query, $this, 'created_at', 'created_at');
        $timezone = new \DateTimeZone(\Yii::$app->timeZone);
        if ($this->from !== '') $query->andWhere(['>=', 'created_at', (new \DateTimeImmutable($this->from, $timezone))->getTimestamp()]);
        if ($this->to !== '') $query->andWhere(['<', 'created_at', (new \DateTimeImmutable($this->to, $timezone))->modify('+1 day')->getTimestamp()]);
        return $provider;
    }
}
