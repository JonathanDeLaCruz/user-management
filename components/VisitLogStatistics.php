<?php
namespace webvimark\modules\UserManagement\components;

use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\db\Expression;
use webvimark\modules\UserManagement\models\UserVisitLog;

class VisitLogStatistics
{
    /** Counts recorded logins by local calendar day, including days without activity. */
    public static function daily(int $days = 30, ?DateTimeImmutable $now = null): array
    {
        $days = max(1, min(366, $days));
        $timezone = new DateTimeZone(Yii::$app->timeZone);
        $today = ($now ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone)->setTime(0, 0);
        $start = $today->modify('-' . ($days - 1) . ' days');
        $end = $today->modify('+1 day');
        $series = [];
        $cases = [];
        $params = [];
        for ($index = 0; $index < $days; $index++) {
            $date = $start->modify('+' . $index . ' days');
            $series[$date->format('Y-m-d')] = ['date' => $date->format('Y-m-d'), 'count' => 0];
            $cases[] = 'WHEN [[visit_time]] < :end' . $index . ' THEN :day' . $index;
            $params[':end' . $index] = $date->modify('+1 day')->getTimestamp();
            $params[':day' . $index] = $date->format('Y-m-d');
        }
        $range = ['and', ['>=', 'visit_time', $start->getTimestamp()], ['<', 'visit_time', $end->getTimestamp()]];
        $bucket = new Expression('CASE ' . implode(' ', $cases) . ' END', $params);
        $rows = UserVisitLog::find()->select(['day' => $bucket, 'count' => new Expression('COUNT(*)')])->where($range)->groupBy('day')->asArray()->all();
        foreach ($rows as $row) {
            $series[$row['day']]['count'] = (int)$row['count'];
        }
        return [
            'series' => array_values($series),
            'total' => array_sum(array_column($series, 'count')),
            'users' => (int)UserVisitLog::find()->where($range)->select(new Expression('COUNT(DISTINCT [[user_id]])'))->scalar(),
            'peak' => max(array_column($series, 'count')),
        ];
    }
}
