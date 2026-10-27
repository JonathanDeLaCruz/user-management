<?php
namespace webvimark\modules\UserManagement\components;

use Firebase\JWT\{JWT, JWK};

/** Signature verification is delegated to firebase/php-jwt; claims are bound to this application and transaction. */
final class ExternalIdentityToken
{
    public static function validate(string $jwt, array $jwks, string $provider, array $config, string $nonce): array
    {
        if (strlen($jwt) > 32768) throw new \UnexpectedValueException('Invalid identity token.');
        $keys = [];
        foreach ($jwks['keys'] ?? [] as $key) {
            if (($key['kty'] ?? '') === 'RSA' && ($key['alg'] ?? 'RS256') === 'RS256'
                && ($key['use'] ?? 'sig') === 'sig' && isset($key['kid'])) $keys[] = $key + ['alg' => 'RS256'];
        }
        $claims = (array)JWT::decode($jwt, JWK::parseKeySet(['keys' => $keys], 'RS256'));
        foreach (['iss', 'sub', 'nonce'] as $field) {
            if (!isset($claims[$field]) || !is_string($claims[$field]) || $claims[$field] === '') throw new \UnexpectedValueException('Missing identity claim.');
        }
        if (!isset($claims['exp'], $claims['iat']) || !is_int($claims['exp']) || !is_int($claims['iat'])
            || $claims['exp'] <= time() || $claims['iat'] > time() || $claims['iat'] < time() - 3600
            || (isset($claims['nbf']) && (!is_int($claims['nbf']) || $claims['nbf'] > time()))
            || $claims['exp'] <= $claims['iat'] || strlen($claims['sub']) > 255
            || !hash_equals($nonce, $claims['nonce'])) throw new \UnexpectedValueException('Expired or mismatched identity token.');
        $audience = $claims['aud'] ?? null;
        if (!(is_string($audience) && hash_equals($config['clientId'], $audience))
            && !(is_array($audience) && in_array($config['clientId'], $audience, true))) throw new \UnexpectedValueException('Wrong audience.');
        if ((is_array($audience) && count($audience) > 1 && !isset($claims['azp']))
            || (isset($claims['azp']) && $claims['azp'] !== $config['clientId'])) throw new \UnexpectedValueException('Wrong authorized party.');
        if ($provider === 'google') {
            if (!in_array($claims['iss'], ['https://accounts.google.com', 'accounts.google.com'], true)) throw new \UnexpectedValueException('Wrong issuer.');
            $claims['iss'] = 'https://accounts.google.com';
        } elseif ($provider === 'microsoft') {
            $tenant = $claims['tid'] ?? '';
            if (!is_string($tenant) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $tenant)
                || $claims['iss'] !== 'https://login.microsoftonline.com/' . $tenant . '/v2.0'
                || ($claims['ver'] ?? '') !== '2.0') throw new \UnexpectedValueException('Wrong Microsoft issuer.');
            $consumer = '9188040d-6c67-4c5b-b112-36a304b66dad';
            if (($config['tenant'] === 'consumers' && $tenant !== $consumer)
                || ($config['tenant'] === 'organizations' && $tenant === $consumer)
                || (!in_array($config['tenant'], ['common', 'consumers', 'organizations'], true) && $tenant !== $config['tenant'])) throw new \UnexpectedValueException('Tenant not allowed.');
            // Microsoft signing keys can be restricted to a specific issuer.
            $header = json_decode(JWT::urlsafeB64Decode(explode('.', $jwt)[0]), true, 16, JSON_THROW_ON_ERROR);
            foreach ($keys as $key) {
                if ($key['kid'] === ($header['kid'] ?? null) && isset($key['issuer'])
                    && str_replace('{tenantid}', $tenant, $key['issuer']) !== $claims['iss']) throw new \UnexpectedValueException('Signing key issuer mismatch.');
            }
        } else throw new \UnexpectedValueException('Unknown provider.');
        return $claims;
    }
}
