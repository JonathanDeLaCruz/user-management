<?php
namespace webvimark\modules\UserManagement\components;

final class RuntimeRequirements
{
    public static function assertSupported(): void
    {
        $minimum = ['8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];
        $branch = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        if (!isset($minimum[$branch]) || version_compare(PHP_VERSION, $minimum[$branch], '<')) {
            throw new \yii\base\InvalidConfigException('Update the web and console PHP runtimes: minimum 8.2.35, 8.3.36, 8.4.27 or 8.5.12.');
        }
        if (PHP_OS_FAMILY === 'Windows' && extension_loaded('curl')) {
            $brotli = curl_version()['brotli_version'] ?? '';
            if ($brotli && version_compare(str_replace('brotli/', '', $brotli), '1.2.1', '<')) {
                throw new \yii\base\InvalidConfigException('Update the Windows PHP build to revision -1 or later, with Brotli >= 1.2.1.');
            }
        }
    }
}
