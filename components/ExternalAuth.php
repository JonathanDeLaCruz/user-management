<?php
namespace webvimark\modules\UserManagement\components;

use Yii;
use yii\db\{Query, Expression};
use yii\web\{ForbiddenHttpException, NotFoundHttpException};
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule as UM;

class ExternalAuth
{
    public static function enabled(): array
    {
        $result = [];
        foreach (['google' => 'Google', 'microsoft' => 'Microsoft'] as $id => $label) {
            if ((UM::instance()->externalAuth[$id]['enabled'] ?? false) === true) $result[$id] = $label;
        }
        return $result;
    }

    public static function config(string $provider): array
    {
        if (!isset(self::enabled()[$provider])) throw new NotFoundHttpException();
        $config = UM::instance()->externalAuth[$provider] + ['clientId' => '', 'clientSecret' => '', 'redirectUri' => '',
            'allowRegistration' => false, 'roles' => [], 'tenant' => 'common'];
        foreach (['clientId', 'clientSecret', 'redirectUri'] as $field) {
            if (!is_string($config[$field]) || trim($config[$field]) === '') throw new \yii\base\InvalidConfigException('Configure externalAuth credentials and redirectUri.');
        }
        $uri = parse_url($config['redirectUri']);
        if (!$uri || isset($uri['user']) || isset($uri['pass']) || isset($uri['fragment'])
            || !(($uri['scheme'] ?? '') === 'https' || (($uri['scheme'] ?? '') === 'http' && in_array($uri['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true)))) throw new \yii\base\InvalidConfigException('External authentication requires HTTPS (HTTP localhost only for development).');
        if (strlen($config['clientId']) > 255 || !is_bool($config['allowRegistration']) || !is_array($config['roles'])) throw new \yii\base\InvalidConfigException('Invalid externalAuth options.');
        foreach ($config['roles'] as $role) if (!is_string($role) || $role === '') throw new \yii\base\InvalidConfigException('External registration roles must be role names.');
        if ($provider === 'microsoft' && (!is_string($config['tenant']) || !preg_match('/^(?:common|organizations|consumers|[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$/D', $config['tenant']))) throw new \yii\base\InvalidConfigException('Use a Microsoft tenant ID, common, organizations or consumers.');
        return $config;
    }

    private static function fingerprint(array $config): string
    {
        return hash('sha256', json_encode($config, JSON_THROW_ON_ERROR));
    }

    public static function links(User $user): array
    {
        if (!Yii::$app->db->getTableSchema('{{%um_external_identity}}')) return [];
        return (new Query())->from('{{%um_external_identity}}')->where(['user_id' => $user->id])->indexBy('provider')->all();
    }

    public static function proof(User $user): bool
    {
        $proof = Yii::$app->session->get('um_external_reauth', []);
        return ($proof['expires'] ?? 0) >= time() && ($proof['user'] ?? null) === $user->id
            && hash_equals(hash('sha256', $user->auth_key), $proof['key'] ?? '');
    }

    public function start(string $provider, string $intent): string
    {
        $config = self::config($provider);
        if (!in_array($intent, ['login', 'link', 'reauth'], true)) throw new ForbiddenHttpException();
        $user = User::component()->identity;
        if (($intent === 'login') !== ($user === null)) throw new ForbiddenHttpException();
        if ($intent === 'link' && !AccountSecurity::recent($user)) throw new ForbiddenHttpException(UM::t('front', 'Verify your identity before making changes.'));
        if ($intent === 'reauth' && !isset(self::links($user)[$provider])) throw new ForbiddenHttpException();
        if (!UM::instance()->checkAttempts($provider . ':' . ($user?->id ?? Yii::$app->request->userIP), 'external-start')) throw new ForbiddenHttpException(UM::t('front', 'Too many attempts'));
        $state = Yii::$app->security->generateRandomString(48);
        $nonce = Yii::$app->security->generateRandomString(48);
        $verifier = Yii::$app->security->generateRandomString(64);
        Yii::$app->session->set('um_external_flow', [
            'provider' => $provider, 'intent' => $intent, 'state' => $state, 'nonce' => $nonce, 'verifier' => $verifier,
            'expires' => time() + 300, 'config' => self::fingerprint($config), 'user' => $user?->id,
            'key' => $user ? hash('sha256', $user->auth_key) : null, 'session' => SecuritySessions::currentId(),
        ]);
        $params = ['client_id' => $config['clientId'], 'redirect_uri' => $config['redirectUri'], 'response_type' => 'code',
            'scope' => 'openid profile email', 'state' => $state, 'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256',
            'prompt' => $intent === 'login' ? 'select_account' : 'login'];
        // Google supports select_account/consent, not Microsoft's prompt=login.
        if ($provider === 'google' && $intent !== 'login') { $params['prompt'] = 'select_account'; $params['max_age'] = 0; }
        if ($intent === 'reauth') $params['max_age'] = 0;
        return $this->endpoints($provider, $config)['authorize'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function callback(string $provider, array $query): string
    {
        $config = self::config($provider); // Disabling a provider also rejects callbacks already in flight.
        $flow = Yii::$app->session->get('um_external_flow', []);
        $state = $query['state'] ?? null;
        if (!is_string($state) || !hash_equals($flow['state'] ?? '', $state) || $state === '') throw new ForbiddenHttpException();
        Yii::$app->session->remove('um_external_flow'); // Consume before any network call, including provider errors.
        $user = User::component()->identity;
        if (($flow['expires'] ?? 0) < time() || ($flow['provider'] ?? '') !== $provider
            || !hash_equals($flow['config'] ?? '', self::fingerprint($config)) || ($flow['user'] ?? null) !== $user?->id
            || ($user && !hash_equals($flow['key'] ?? '', hash('sha256', $user->auth_key)))
            || ($flow['session'] ?? '') !== SecuritySessions::currentId() || isset($query['error'])) throw new ForbiddenHttpException();
        if (!is_string($query['code'] ?? null) || $query['code'] === '' || strlen($query['code']) > 8192) throw new ForbiddenHttpException();
        $endpoints = $this->endpoints($provider, $config);
        $tokens = $this->request($endpoints['token'], [
            'grant_type' => 'authorization_code', 'code' => $query['code'], 'redirect_uri' => $config['redirectUri'],
            'client_id' => $config['clientId'], 'client_secret' => $config['clientSecret'], 'code_verifier' => $flow['verifier'],
        ]);
        if (!is_string($tokens['id_token'] ?? null)) throw new ForbiddenHttpException();
        $cacheKey = ['um-oidc-jwks', $endpoints['keys']];
        $jwks = Yii::$app->cache->get($cacheKey);
        if (!is_array($jwks)) { $jwks = $this->request($endpoints['keys']); Yii::$app->cache->set($cacheKey, $jwks, 3600); }
        try { $claims = ExternalIdentityToken::validate($tokens['id_token'], $jwks, $provider, $config, $flow['nonce']); }
        catch (\UnexpectedValueException $e) {
            // Providers rotate keys; one refresh is allowed, never a URL supplied by the JWT.
            $jwks = $this->request($endpoints['keys']); Yii::$app->cache->set($cacheKey, $jwks, 3600);
            $claims = ExternalIdentityToken::validate($tokens['id_token'], $jwks, $provider, $config, $flow['nonce']);
        }
        if ($flow['intent'] === 'reauth' && (!is_int($claims['auth_time'] ?? null) || $claims['auth_time'] < time() - 300 || $claims['auth_time'] > time())) throw new ForbiddenHttpException();
        return $this->accept($provider, $config, $claims, $flow['intent']);
    }

    private function accept(string $provider, array $config, array $claims, string $intent): string
    {
        $id = hash('sha256', implode("\0", [$provider, $config['clientId'], $claims['iss'], $claims['sub']]));
        $link = (new Query())->from('{{%um_external_identity}}')->where(['id' => $id])->one();
        if ($intent === 'reauth') {
            $user = User::component()->identity;
            if (!$link || (int)$link['user_id'] !== (int)$user->id) throw new ForbiddenHttpException();
            Yii::$app->session->set('um_external_reauth', ['user' => $user->id, 'key' => hash('sha256', $user->auth_key), 'expires' => time() + 300]);
            if (!AccountSecurity::requiresMfa($user)) AccountSecurity::markRecent($user, $provider);
            Audit::record('external.reauthenticated', (string)$user->id, ['provider' => $provider]);
            return 'security';
        }
        if ($intent === 'link') {
            $user = User::component()->identity;
            if (!AccountSecurity::recent($user) || ($link && (int)$link['user_id'] !== (int)$user->id)) throw new ForbiddenHttpException();
            $this->link($user, $provider, $config, $claims, $id);
            return 'security';
        }
        if ($link) $user = User::findIdentity($link['user_id']);
        else {
            if (!$config['allowRegistration']) throw new ForbiddenHttpException(UM::t('front', 'Sign in with your existing account and link this provider in Account security.'));
            $user = $this->register($provider, $config, $claims, $id);
        }
        if (!$user || !AccountSecurity::ipAllowed($user)) throw new ForbiddenHttpException();
        Audit::record('external.login', (string)$user->id, ['provider' => $provider]);
        if (AccountSecurity::requiresMfa($user)) { AccountSecurity::begin($user); return 'mfa'; }
        if (!User::component()->completeExternalLogin($user, $provider)) throw new ForbiddenHttpException();
        return 'dashboard';
    }

    private function link(User $user, string $provider, array $config, array $claims, string $id): void
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            Yii::$app->db->createCommand()->delete('{{%um_external_identity}}', ['user_id' => $user->id, 'provider' => $provider])->execute();
            Yii::$app->db->createCommand()->insert('{{%um_external_identity}}', ['id' => $id, 'user_id' => $user->id,
                'provider' => $provider, 'issuer' => $claims['iss'], 'subject' => $claims['sub'], 'client_id' => $config['clientId'], 'created_at' => time()])->execute();
            Audit::record('external.linked', (string)$user->id, ['provider' => $provider]);
            $transaction->commit();
        } catch (\Throwable $e) { $transaction->rollBack(); throw $e; }
    }

    private function register(string $provider, array $config, array $claims, string $id): User
    {
        $email = $provider === 'google' && ($claims['email_verified'] ?? false) === true ? ($claims['email'] ?? null) : null;
        if ($email !== null && (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 128)) throw new ForbiddenHttpException();
        if ($email && User::find()->where(['or', ['=', new Expression('LOWER([[email]])'), mb_strtolower($email)], ['=', new Expression('LOWER([[username]])'), mb_strtolower($email)]])->exists()) throw new ForbiddenHttpException(UM::t('front', 'Sign in with your existing account and link this provider in Account security.'));
        if (UM::instance()->useEmailAsLogin && !$email) throw new ForbiddenHttpException(UM::t('front', 'A verified email is required. Create a local account and link this provider.'));
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $user = new User(['username' => UM::instance()->useEmailAsLogin ? $email : $provider . '-' . bin2hex(random_bytes(8)),
                'email' => $email, 'email_confirmed' => $email ? 1 : 0, 'status' => User::STATUS_ACTIVE, 'superadmin' => 0,
                'password_hash' => Yii::$app->security->generatePasswordHash(Yii::$app->security->generateRandomString(48))]);
            if (!$user->save()) throw new ForbiddenHttpException();
            foreach ($config['roles'] as $roleName) {
                $role = Yii::$app->authManager->getRole($roleName);
                if (!$role) throw new \yii\base\InvalidConfigException('Create the external registration roles before enabling registration.');
                Yii::$app->authManager->assign($role, $user->id);
            }
            $this->link($user, $provider, $config, $claims, $id);
            Audit::record('external.registered', (string)$user->id, ['provider' => $provider]);
            $transaction->commit();
            return $user;
        } catch (\Throwable $e) { $transaction->rollBack(); throw $e; }
    }

    public function unlink(User $user, string $provider, string $password): void
    {
        if (!in_array($provider, ['google', 'microsoft'], true) || !AccountSecurity::recent($user)) throw new ForbiddenHttpException();
        AccountSecurity::rate($user, 'external-unlink');
        $tx = Yii::$app->db->beginTransaction();
        try {
            // Serialize removal across the user's two possible providers.
            Yii::$app->db->createCommand()->update(User::tableName(), ['auth_key' => new Expression('[[auth_key]]')], ['id' => $user->id])->execute();
            $links = self::links($user); $alternative = false;
            foreach ($links as $name => $link) {
                if ($name !== $provider && isset(self::enabled()[$name]) && self::config($name)['clientId'] === $link['client_id']) $alternative = true;
            }
            if (!$alternative && ($password === '' || !$user->validatePassword($password))) throw new ForbiddenHttpException(UM::t('front', 'Confirm your local password or link another enabled provider before unlinking this account.'));
            if (Yii::$app->db->createCommand()->delete('{{%um_external_identity}}', ['user_id' => $user->id, 'provider' => $provider])->execute() !== 1) throw new ForbiddenHttpException();
            SecuritySessions::revoke($user->id, null, true);
            Audit::record('external.unlinked', (string)$user->id, ['provider' => $provider]);
            $tx->commit();
        } catch (\Throwable $e) { $tx->rollBack(); throw $e; }
    }

    protected function endpoints(string $provider, array $config): array
    {
        if ($provider === 'google') return ['authorize' => 'https://accounts.google.com/o/oauth2/v2/auth', 'token' => 'https://oauth2.googleapis.com/token', 'keys' => 'https://www.googleapis.com/oauth2/v3/certs'];
        $base = 'https://login.microsoftonline.com/' . $config['tenant'];
        return ['authorize' => $base . '/oauth2/v2.0/authorize', 'token' => $base . '/oauth2/v2.0/token', 'keys' => $base . '/discovery/v2.0/keys'];
    }

    protected function request(string $url, ?array $form = null): array
    {
        $client = new class extends \yii\httpclient\Client {
            public function createRequestLogToken($method, $url, $headers, $content) { return strtoupper($method) . ' ' . $url . ' [OIDC body redacted]'; }
        };
        $request = $client->createRequest()->setUrl($url)->setMethod($form === null ? 'GET' : 'POST')
            ->setFormat(\yii\httpclient\Client::FORMAT_URLENCODED)
            ->setOptions(['timeout' => 15, 'followLocation' => 0, 'sslVerifyPeer' => true, 'sslVerifyPeerName' => true, 'sslAllowSelfSigned' => false]);
        if ($form !== null) $request->setData($form);
        $response = $request->send();
        if (!$response->isOk || strlen($response->content) > 1048576) throw new \RuntimeException('Identity provider request failed.');
        $data = json_decode($response->content, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new \RuntimeException('Invalid identity provider response.');
        return $data;
    }
}
