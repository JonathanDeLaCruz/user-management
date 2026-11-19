<?php
namespace webvimark\modules\UserManagement\components;

/** Isolated test clock; excluded from the Composer distribution. */
final class SessionTouchClock
{
    public static ?int $now = null;
}

function time(): int
{
    return SessionTouchClock::$now ?? \time();
}
