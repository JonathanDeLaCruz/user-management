<?php
namespace webvimark\modules\UserManagement\components;

use Yii;
use Webauthn\{PublicKeyCredential, PublicKeyCredentialCreationOptions, PublicKeyCredentialRequestOptions,
    PublicKeyCredentialRpEntity, PublicKeyCredentialUserEntity, PublicKeyCredentialParameters,
    PublicKeyCredentialDescriptor, AuthenticatorSelectionCriteria, AuthenticatorAttestationResponse,
    AuthenticatorAssertionResponse, AuthenticatorAttestationResponseValidator,
    AuthenticatorAssertionResponseValidator, CredentialRecord};
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use yii\db\Query;
use yii\web\ForbiddenHttpException;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule as UM;

final class Passkeys
{
    public static function origin(): string
    {
        $origin = rtrim((string)(UM::instance()->webauthnOrigin ?: getenv('UM_WEBAUTHN_ORIGIN')), '/');
        $p = parse_url($origin);
        if (!$p || !isset($p['host'], $p['scheme']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) || !empty($p['path'])
            || ($p['scheme'] !== 'https' && !($p['scheme'] === 'http' && $p['host'] === 'localhost'))) {
            throw new \yii\base\InvalidConfigException('Configure UM_WEBAUTHN_ORIGIN as the exact HTTPS origin (http://localhost is allowed for development).');
        }
        return $origin;
    }

    public static function serializer(): \Symfony\Component\Serializer\SerializerInterface
    {
        return (new WebauthnSerializerFactory(new AttestationStatementSupportManager()))->create();
    }

    private static function factory(): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins([self::origin()], false);
        return $factory;
    }

    public static function options(User $user, string $operation): array
    {
        if (!in_array($operation, ['register', 'login', 'reauth'], true)) throw new ForbiddenHttpException();
        AccountSecurity::rate($user, 'passkey-options');
        if ($operation === 'register' && !AccountSecurity::mayEnroll($user)) throw new ForbiddenHttpException();
        $rp = parse_url(self::origin(), PHP_URL_HOST);
        $serializer = self::serializer();
        $keys = array_map(fn($row) => $serializer->deserialize($row['record'], CredentialRecord::class, 'json'), AccountSecurity::passkeys($user));
        $descriptors = array_map(fn(CredentialRecord $key) => new PublicKeyCredentialDescriptor('public-key', $key->publicKeyCredentialId, $key->transports), $keys);
        if ($operation === 'register') {
            $handle = hex2bin(AccountSecurity::profile($user)['user_handle']);
            $options = new PublicKeyCredentialCreationOptions(
                rp: new PublicKeyCredentialRpEntity(Yii::$app->name, $rp),
                user: new PublicKeyCredentialUserEntity($user->username, $handle, $user->username),
                challenge: random_bytes(32),
                pubKeyCredParams: [new PublicKeyCredentialParameters('public-key', -7), new PublicKeyCredentialParameters('public-key', -257)],
                authenticatorSelection: new AuthenticatorSelectionCriteria(userVerification: 'required', residentKey: 'preferred'),
                attestation: 'none', excludeCredentials: $descriptors, timeout: 60000,
            );
        } else {
            if (!$descriptors) throw new ForbiddenHttpException(UM::t('front', 'No passkey registered'));
            $options = new PublicKeyCredentialRequestOptions(random_bytes(32), $rp, $descriptors, 'required', 60000);
        }
        $json = $serializer->serialize($options, 'json');
        Yii::$app->session->set('um_ceremony', ['user' => $user->id, 'key' => hash('sha256', $user->auth_key),
            'operation' => $operation, 'options' => $json, 'origin' => self::origin(), 'expires' => time() + 180]);
        return json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    }

    public static function decode(string $encoded): string
    {
        if (strlen($encoded) > 100000 || !preg_match('/^[a-zA-Z0-9_-]*$/D', $encoded)) throw new ForbiddenHttpException();
        $data = base64_decode(strtr($encoded, '-_', '+/') . str_repeat('=', (4 - strlen($encoded) % 4) % 4), true);
        if ($data === false) throw new ForbiddenHttpException();
        return $data;
    }

    public static function verify(User $user, string $operation, array $data, string $name = ''): void
    {
        AccountSecurity::rate($user, 'passkey');
        $state = Yii::$app->session->remove('um_ceremony'); // Challenges are single-use, including failures.
        if (!is_array($state) || ($state['user'] ?? null) !== $user->id || ($state['operation'] ?? '') !== $operation
            || ($state['expires'] ?? 0) < time() || !hash_equals($state['key'], hash('sha256', $user->auth_key))
            || $state['origin'] !== self::origin()) throw new ForbiddenHttpException(UM::t('front', 'Verification expired. Try again.'));
        $client = json_decode(self::decode((string)($data['response']['clientDataJSON'] ?? '')), true, 16, JSON_THROW_ON_ERROR);
        // Exact origin and no cross-origin frames: no trusting a user-supplied RP or Host header.
        if (($client['origin'] ?? '') !== self::origin() || ($client['crossOrigin'] ?? false) !== false || isset($client['topOrigin'])
            || isset($client['tokenBinding'])) throw new ForbiddenHttpException();
        $serializer = self::serializer();
        $credential = $serializer->deserialize(json_encode($data, JSON_THROW_ON_ERROR), PublicKeyCredential::class, 'json');
        $host = parse_url(self::origin(), PHP_URL_HOST);
        $id = hash('sha256', $credential->rawId);
        if ($operation === 'register') {
            if (!AccountSecurity::mayEnroll($user) || !$credential->response instanceof AuthenticatorAttestationResponse) throw new ForbiddenHttpException();
            $options = $serializer->deserialize($state['options'], PublicKeyCredentialCreationOptions::class, 'json');
            $record = (new AuthenticatorAttestationResponseValidator(self::factory()->creationCeremony()))->check($credential->response, $options, $host);
            Yii::$app->db->createCommand()->insert('{{%um_passkey}}', [
                'id' => $id, 'user_id' => $user->id, 'name' => mb_substr(trim($name) ?: UM::t('front', 'Passkey'), 0, 100),
                'record' => $serializer->serialize($record, 'json'), 'created_at' => time(),
            ])->execute();
            AccountSecurity::notify($user, 'Passkey registered');
        } else {
            if (!$credential->response instanceof AuthenticatorAssertionResponse) throw new ForbiddenHttpException();
            $row = (new Query())->from('{{%um_passkey}}')->where(['id' => $id, 'user_id' => $user->id])->one();
            if (!$row) throw new ForbiddenHttpException();
            $record = $serializer->deserialize($row['record'], CredentialRecord::class, 'json');
            $options = $serializer->deserialize($state['options'], PublicKeyCredentialRequestOptions::class, 'json');
            $updated = (new AuthenticatorAssertionResponseValidator(self::factory()->requestCeremony()))->check(
                $record, $credential->response, $options, $host, hex2bin(AccountSecurity::profile($user)['user_handle'])
            );
            $count = Yii::$app->db->createCommand()->update('{{%um_passkey}}', [
                'record' => $serializer->serialize($updated, 'json'), 'version' => (int)$row['version'] + 1, 'last_used_at' => time(),
            ], ['id' => $id, 'user_id' => $user->id, 'version' => $row['version']])->execute();
            if ($count !== 1) throw new ForbiddenHttpException();
        }
        AccountSecurity::markRecent($user, 'passkey');
    }
}
