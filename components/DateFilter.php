<?php
namespace webvimark\modules\UserManagement\components;

use Yii;
use yii\base\Model;
use yii\db\Query;

/** Calendar dates are interpreted in the application's zone, including DST boundaries. */
final class DateFilter
{
    public static function bounds(string $value): ?array
    {
        $parts = explode(' - ', $value);
        if (count($parts) > 2) return null;
        $zone = new \DateTimeZone(Yii::$app->timeZone);
        $dates = [];
        foreach ($parts as $part) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $part, $zone);
            if (!$date || $date->format('Y-m-d') !== $part) return null;
            $dates[] = $date;
        }
        $end = $dates[count($dates) - 1];
        return $end < $dates[0] ? null : [$dates[0]->getTimestamp(), $end->modify('+1 day')->getTimestamp()];
    }

    public static function apply(Query $query, Model $model, string $attribute, string $column): void
    {
        $value = $model->$attribute;
        if ($value === '' || $value === null) return;
        $bounds = is_string($value) ? self::bounds($value) : null;
        if ($bounds === null) {
            $model->addError($attribute, Yii::t('yii', 'The format of {attribute} is invalid.', ['attribute' => $model->getAttributeLabel($attribute)]));
            $query->andWhere('0=1');
            return;
        }
        $query->andWhere(['>=', $column, $bounds[0]])->andWhere(['<', $column, $bounds[1]]);
    }
}
