<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\components\VisitLogStatistics;
use webvimark\modules\UserManagement\models\UserVisitLog;

class VisitLogStatisticsTest extends TestCase
{
    public function testDaysWithoutActivityAndDaylightSavingBoundaries(): void
    {
        $transaction = Yii::$app->db->beginTransaction();
        $timezone = Yii::$app->timeZone;
        try {
            Yii::$app->timeZone = 'America/New_York';
            $now = new DateTimeImmutable('2026-11-02 12:00:00', new DateTimeZone(Yii::$app->timeZone));
            foreach ([['2026-10-30T23:59:59-04:00', 9], ['2026-10-31T23:59:59-04:00', 1], ['2026-11-01T01:30:00-04:00', 1], ['2026-11-01T01:30:00-05:00', 2], ['2026-11-03T00:00:00-05:00', 9]] as [$time, $user]) {
                Yii::$app->db->createCommand()->insert(UserVisitLog::tableName(), ['token'=>bin2hex(random_bytes(8)), 'ip'=>'127.0.0.1', 'language'=>'es', 'user_agent'=>'Test', 'browser'=>'Test', 'os'=>'Test', 'user_id'=>$user, 'visit_time'=>(new DateTimeImmutable($time))->getTimestamp()])->execute();
            }
            $result = VisitLogStatistics::daily(3, $now);
            self::assertSame(['2026-10-31','2026-11-01','2026-11-02'], array_column($result['series'], 'date'));
            self::assertSame([1, 2, 0], array_column($result['series'], 'count'));
            self::assertSame(3, $result['total']);
            self::assertSame(2, $result['users']);
            self::assertSame(2, $result['peak']);
        } finally {
            $transaction->rollBack();
            Yii::$app->timeZone = $timezone;
        }
    }

    public function testEmptyPeriodKeepsThirtyDaysWithZeroValues(): void
    {
        $result = VisitLogStatistics::daily(30, new DateTimeImmutable('2100-01-01'));
        self::assertCount(30, $result['series']);
        self::assertSame(0, $result['total']);
        self::assertSame(0, $result['users']);
        self::assertSame(0, $result['peak']);
        self::assertSame(array_fill(0, 30, 0), array_column($result['series'], 'count'));
    }
}
