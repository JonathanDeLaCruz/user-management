<?php
namespace webvimark\modules\UserManagement\components;

use Yii;
use yii\helpers\Url;
use yii\web\{ForbiddenHttpException, Request};
use yii\base\InvalidConfigException;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule as UM;

/** A single local destination, resolved only after the complete authentication flow. */
final class LoginDestination
{
    public static function resolve(UM $module, bool $legacyPasswordLogin = false): string
    {
        if (User::component()->isGuest) throw new ForbiddenHttpException();
        if ($module->respectReturnUrl !== null && !is_bool($module->respectReturnUrl)) throw new InvalidConfigException('respectReturnUrl must be true, false or null.');
        $route = (array)$module->afterLoginRoute;
        if (!isset($route[0]) || !is_string($route[0]) || !preg_match('~^/[a-zA-Z0-9_/-]*$~D', $route[0])) {
            throw new InvalidConfigException('afterLoginRoute must be an absolute application route, optionally followed by route parameters.');
        }
        $fallback = Url::toRoute($route);
        if (!self::local($fallback, $module)) throw new InvalidConfigException('afterLoginRoute must resolve to a local page outside the authentication flow.');
        if ($module->respectReturnUrl ?? $legacyPasswordLogin) {
            // Read only Yii's server-side return URL; never a request's returnUrl/redirect parameter.
            $stored = Yii::$app->session->get(User::component()->returnUrlParam);
            try { $return = is_string($stored) || (is_array($stored) && isset($stored[0]) && is_string($stored[0])) ? User::component()->getReturnUrl() : null; }
            catch (\yii\base\InvalidArgumentException|\TypeError $error) { $return = null; }
            if (($module->respectReturnUrl !== null || $return !== Yii::$app->homeUrl) && self::local($return, $module)) return $return;
        }
        return $fallback;
    }

    private static function local($url, UM $module): bool
    {
        // Root-relative URLs work without trusting Host or forwarded headers for a same-origin check.
        if (!is_string($url) || $url === '' || !str_starts_with($url, '/') || str_starts_with($url, '//')
            || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|7f|5c)/i', $url)) return false;
        $parts = parse_url($url);
        if ($parts === false || isset($parts['host']) || isset($parts['scheme']) || isset($parts['user'])) return false;
        $path = $parts['path'] ?? '';
        // Reject encoded separators, dot segments and double encoding rather than relying on web-server normalization.
        if (preg_match('/%(?:2f|2e|25)/i', $path) || str_contains($path, '//') || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path)) return false;
        $path = rawurldecode($path);
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $path)) return false;
        parse_str($parts['query'] ?? '', $query);
        $request = new class extends Request { public function getMethod() { return 'GET'; } };
        $source = Yii::$app->request;
        $request->setHostInfo($source->getHostInfo());
        $request->setScriptUrl($source->getScriptUrl());
        $request->setBaseUrl($source->getBaseUrl());
        $request->setUrl($url);
        $request->setQueryParams($query);
        $pathInfo = $path;
        foreach ([$source->getScriptUrl(), $source->getBaseUrl()] as $prefix) {
            if ($prefix !== '' && ($pathInfo === $prefix || str_starts_with($pathInfo, $prefix . '/'))) {
                $pathInfo = substr($pathInfo, strlen($prefix));
                break;
            }
        }
        $request->setPathInfo(ltrim($pathInfo, '/'));
        try { $parsed = Yii::$app->urlManager->parseRequest($request); }
        catch (\yii\base\InvalidArgumentException|\yii\web\BadRequestHttpException|\yii\web\UrlNormalizerRedirectException|\TypeError $error) { return false; }
        if ($parsed === false || !is_string($parsed[0])) return false;
        $resolved = strtolower(trim($parsed[0], '/'));
        if ($resolved === '') $resolved = strtolower(trim(Yii::$app->defaultRoute, '/'));
        if ($resolved === strtolower($module->getUniqueId())) $resolved .= '/' . strtolower(trim($module->defaultRoute, '/'));
        $login = (array)User::component()->loginUrl;
        $loginRoute = isset($login[0]) && is_string($login[0]) ? strtolower(trim($login[0], '/')) : '';
        if ($loginRoute !== '' && $resolved === $loginRoute) return false;
        $prefix = $module->getUniqueId() . '/';
        foreach ([$resolved, trim($pathInfo, '/')] as $candidate) {
            $candidate = strtolower(rtrim(preg_replace('~/+~', '/', $candidate), '/'));
            if (str_starts_with($candidate, $prefix . 'external-auth/')) return false;
            if (in_array($candidate, array_map(fn($route) => $prefix . $route, [
                'auth/login', 'auth/logout', 'security/challenge', 'security/passkey-options',
                'security/passkey-finish', 'security/totp-setup', 'security/totp-confirm',
            ]), true)) return false;
        }
        return true;
    }
}
