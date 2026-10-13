<?php
namespace webvimark\modules\UserManagement\components;
class Singleton
{
    private static array $data = [];
    public static function getData($key) { return self::$data[$key] ?? null; }
    public static function setData($key, $value) { self::$data[$key] = $value; }
}
