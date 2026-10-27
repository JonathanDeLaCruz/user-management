<?php
namespace webvimark\modules\UserManagement\components;

use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule as UM;

/** Display labels only; original audit records remain unchanged. */
final class SecurityPresentation
{
    public static function events(): array
    {
        $labels = [
            'external.linked' => 'External account linked', 'external.unlinked' => 'External account unlinked',
            'external.login' => 'External sign-in verified', 'external.registered' => 'Account created through an external provider',
            'external.reauthenticated' => 'External account reverified', 'external.failed' => 'External sign-in failed',
            'session.started' => 'Signed in', 'session.revoked' => 'Sessions signed out',
            'login.failed' => 'Sign-in attempt rejected', 'mfa.challenge' => 'Additional verification requested',
            'mfa.failed' => 'Additional verification failed', 'passkey.failed' => 'Passkey verification failed',
            'permissions.changed' => 'Access permissions refreshed',
            'model.afterInsert' => 'Record created', 'model.afterUpdate' => 'Record updated', 'model.afterDelete' => 'Record deleted',
            'action.completed' => 'Administrative request processed',
            'notification.failed' => 'Security email could not be sent',
            'operator.mfa_reset' => 'Operator reset account verification', 'operator.retention' => 'Old security records removed',
        ];
        foreach (['Passkey registered', 'Authenticator enabled', 'Recovery codes regenerated', 'Recovery code used',
            'Authentication factor removed', 'Authentication factors reset by operator', 'Password changed'] as $label) $labels['security.' . $label] = $label;
        return array_map(fn($label) => UM::t('front', $label), $labels);
    }

    public static function actor(array $row): string
    {
        return $row['username'] ?? UM::t('front', $row['actor_id'] === null ? 'System or visitor' : 'Deleted user');
    }

    public static function target(array $row): string
    {
        $target = (string)($row['target'] ?? '');
        if ($target === '') return UM::t('front', 'Not recorded');
        if (ctype_digit($target)) return self::user($target);
        if (str_contains($target, ':')) {
            [$class, $key] = explode(':', $target, 2);
            $key = json_decode($key, true);
            $type = substr($class, strrpos($class, '\\') + 1);
            if ($type === 'User' && is_numeric($key)) return self::user((string)$key);
            $label = ['Role' => 'Role', 'Permission' => 'Permission', 'AuthItemGroup' => 'Permission group'][$type] ?? 'Record';
            return UM::t('front', $label) . (is_scalar($key) ? ': ' . (string)$key : '');
        }
        if ($target[0] === '/') {
            $parts = explode('/', trim($target, '/'));
            $labels = ['user' => 'User', 'role' => 'Role', 'permission' => 'Permission', 'user-permission' => 'Access permissions',
                'auth-item-group' => 'Permission group', 'auth' => 'Account', 'user-visit-log' => 'Visit history'];
            $actions = ['create' => 'Create', 'update' => 'Update', 'delete' => 'Delete', 'bulk-delete' => 'Delete selected records',
                'bulk-activate' => 'Activate selected accounts', 'bulk-deactivate' => 'Deactivate selected accounts',
                'set' => 'Edit access permissions', 'set-roles' => 'Assign roles', 'set-permissions' => 'Assign permissions',
                'change-password' => 'Change password', 'refresh-routes' => 'Refresh routes'];
            $label = UM::t('front', $labels[$parts[1] ?? ''] ?? 'Administration');
            return isset($actions[$parts[2] ?? '']) ? $label . ' · ' . UM::t('front', $actions[$parts[2]]) : $label;
        }
        return UM::t('front', 'Record');
    }

    private static function user(string $id): string
    {
        $name = (new \yii\db\Query())->select('username')->from(User::tableName())->where(['id' => $id])->scalar();
        return $name !== false ? (string)$name : UM::t('front', 'Deleted user');
    }

    public static function details(array $row): string
    {
        $data = json_decode($row['details'] ?? '', true);
        $data = is_array($data) ? $data : [];
        $t = fn($label, $params = []) => UM::t('front', $label, $params);
        if (str_starts_with($row['event'], 'external.')) return $t('Identity provider: {provider}.', ['provider' => ['google' => 'Google', 'microsoft' => 'Microsoft'][$data['provider'] ?? ''] ?? $t('Not recorded')]);
        if ($row['event'] === 'session.started') {
            $method = ['password' => 'Password', 'totp' => 'Authenticator code', 'passkey' => 'Passkey', 'google' => 'Google', 'microsoft' => 'Microsoft'][$data['method'] ?? ''] ?? 'Verification method';
            return $t('Signed in using: {method}.', ['method' => $t($method)]);
        }
        if ($row['event'] === 'session.revoked') return $t('Sessions were revoked. A new sign-in is required on the affected devices.');
        if ($row['event'] === 'action.completed') return $t('An administrative request was processed. Consult the related record changes to confirm what was saved.');
        if ($row['event'] === 'permissions.changed') return $t('The access permission cache was refreshed so new rules take effect.');
        if ($row['event'] === 'operator.retention') return $t('Records older than {days} days were removed.', ['days' => (int)($data['days'] ?? 0)]);
        if (str_starts_with($row['event'], 'model.')) {
            $fields = ['username' => 'Username', 'status' => 'Account status', 'superadmin' => 'Superadmin access', 'name' => 'Name',
                'description' => 'Description', 'group_code' => 'Permission group', 'rule_name' => 'Access rule', 'email_confirmed' => 'Email confirmation'];
            $names = [];
            foreach (($data['fields'] ?? []) as $field) if (is_string($field) && isset($fields[$field])) $names[] = $t($fields[$field]);
            if ($names) return $t('Affected fields: {fields}.', ['fields' => implode(', ', $names)]);
            return $t('The record was created or removed. Field values are not stored in this log.');
        }
        $descriptions = [
            'login.failed' => 'The sign-in did not pass the account checks. No password is recorded.',
            'mfa.challenge' => 'The account must complete an additional identity check before continuing.',
            'mfa.failed' => 'The additional identity check was rejected or expired.',
            'passkey.failed' => 'The device could not complete passkey verification.',
            'notification.failed' => 'Check the mail service configuration and delivery logs.',
            'operator.mfa_reset' => 'An authorized operator reset the account authentication factors.',
        ];
        return $t($descriptions[$row['event']] ?? 'No additional details recorded.');
    }

    public static function device(string $agent): string
    {
        $browser = 'Unknown browser';
        foreach (['Edg' => 'Microsoft Edge', 'OPR' => 'Opera', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'Version' => 'Safari'] as $token => $label) {
            if (preg_match('~' . $token . '/([0-9]+)~', $agent, $match)) { $browser = $label . ' ' . $match[1]; break; }
        }
        $os = 'Unknown device';
        foreach (['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Macintosh' => 'macOS', 'Linux' => 'Linux'] as $token => $label) {
            if (str_contains($agent, $token)) { $os = $label; break; }
        }
        return UM::t('front', $browser) . ' · ' . UM::t('front', $os);
    }
}
