<?php
use PHPUnit\Framework\TestCase;
class RuntimeSecurityTest extends TestCase
{
    public function testRuntimeContainsTheCriticalPhpSecurityFixes(): void
    {
        $minimum = ['8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];
        $branch = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        self::assertArrayHasKey($branch, $minimum);
        self::assertTrue(version_compare(PHP_VERSION, $minimum[$branch], '>='), 'Use a PHP release with the October 2026 security fixes.');
        if (PHP_OS_FAMILY === 'Windows' && extension_loaded('curl') && !empty(curl_version()['brotli_version'])) {
            self::assertTrue(version_compare(str_replace('brotli/', '', curl_version()['brotli_version']), '1.2.1', '>='), 'Use a patched Windows build (-1 or later).');
        }
    }
}
