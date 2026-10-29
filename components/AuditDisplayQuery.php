<?php
namespace webvimark\modules\UserManagement\components;

use Yii;
use yii\db\{Expression, Query};
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule as UM;

/** SQL projection of the visible audit text. Filtering, sorting and pagination stay in the database. */
final class AuditDisplayQuery
{
    private $db;

    public function __construct() { $this->db = Yii::$app->db; }

    private function quote(string $value): string { return $this->db->quoteValue($value); }
    private function text(string $label): string { return $this->quote(UM::t('front', $label)); }
    private function concat(string ...$parts): string
    {
        return $this->db->driverName === 'mysql' ? 'CONCAT(' . implode(', ', $parts) . ')' : '(' . implode(' || ', $parts) . ')';
    }
    private function map(string $column, array $values, string $fallback): string
    {
        $sql = 'CASE';
        foreach ($values as $key => $value) $sql .= ' WHEN ' . $column . ' = ' . $this->quote((string)$key) . ' THEN ' . $this->quote($value);
        return $sql . ' ELSE ' . $fallback . ' END';
    }
    private function json(string $value, string $path): string
    {
        // Invalid historic JSON must not break the whole listing.
        $safe = "CASE WHEN JSON_VALID($value) THEN $value ELSE '{}' END";
        $extract = 'JSON_EXTRACT(' . $safe . ', ' . $this->quote($path) . ')';
        return $this->db->driverName === 'mysql' ? 'NULLIF(JSON_UNQUOTE(' . $extract . "), 'null')" : $extract;
    }
    private function part(string $value, string $delimiter, int $index): string
    {
        $delimiter = $this->quote($delimiter);
        if ($this->db->driverName === 'mysql') return "SUBSTRING_INDEX(SUBSTRING_INDEX($value, $delimiter, $index), $delimiter, -1)";
        for ($i = 1; $i < $index; ++$i) $value = "CASE WHEN INSTR($value, $delimiter) > 0 THEN SUBSTR($value, INSTR($value, $delimiter) + 1) ELSE '' END";
        return "CASE WHEN INSTR($value, $delimiter) > 0 THEN SUBSTR($value, 1, INSTR($value, $delimiter) - 1) ELSE $value END";
    }
    private function digits(string $value): string
    {
        return $this->db->driverName === 'mysql' ? "$value REGEXP '^[0-9]+$'" : "($value <> '' AND $value NOT GLOB '*[^0-9]*')";
    }

    public function query(): Query
    {
        $target = "COALESCE([[a.target]], '')";
        $class = $this->part($target, ':', 1);
        $key = "SUBSTR($target, INSTR($target, ':') + 1)";
        if ($this->db->driverName === 'mysql') $key = "SUBSTR($target, LOCATE(':', $target) + 1)";
        $decoded = $this->json($key, '$');
        $isUser = '(' . $class . ' = ' . $this->quote(User::class) . ' OR SUBSTR(' . $class . ', -5) = ' . $this->quote('\\User') . ')';
        $numericTarget = $this->digits($target);
        $userKey = "CASE WHEN $numericTarget THEN $target WHEN $isUser THEN $decoded ELSE NULL END";
        $userName = 'COALESCE([[target_user.username]], ' . $this->text('Deleted user') . ')';
        $targetText = 'CASE WHEN ' . $target . " = '' THEN " . $this->text('Not recorded') . " WHEN $numericTarget THEN $userName";
        $targetText .= " WHEN $target LIKE '%:%' THEN CASE WHEN $isUser AND $decoded IS NOT NULL THEN $userName ELSE ";
        $typeLabel = 'CASE';
        foreach (['Role' => 'Role', 'Permission' => 'Permission', 'AuthItemGroup' => 'Permission group'] as $type => $label) {
            $suffix = '\\' . $type;
            $typeLabel .= ' WHEN SUBSTR(' . $class . ', -' . strlen($suffix) . ') = ' . $this->quote($suffix) . ' THEN ' . $this->text($label);
        }
        $typeLabel .= ' ELSE ' . $this->text('Record') . ' END';
        $targetText .= $this->concat($typeLabel, "CASE WHEN $decoded IS NOT NULL THEN " . $this->concat($this->quote(': '), $decoded) . " ELSE '' END") . ' END';
        $route = $this->db->driverName === 'mysql' ? "TRIM(BOTH '/' FROM $target)" : "TRIM($target, '/')";
        $controller = $this->part($route, '/', 2);
        $action = $this->part($route, '/', 3);
        $controllers = [];
        foreach (['user', 'role', 'permission', 'user-permission', 'auth-item-group', 'auth', 'user-visit-log'] as $name) $controllers[$name] = SecurityPresentation::target(['target' => '/user-management/' . $name . '/index']);
        $actions = [];
        foreach (['create', 'update', 'delete', 'bulk-delete', 'bulk-activate', 'bulk-deactivate', 'set', 'set-roles', 'set-permissions', 'change-password', 'refresh-routes'] as $name) {
            $label = SecurityPresentation::target(['target' => '/user-management/user/' . $name]);
            $actions[$name] = ' · ' . explode(' · ', $label, 2)[1];
        }
        $targetText .= " WHEN SUBSTR($target, 1, 1) = '/' THEN " . $this->concat($this->map($controller, $controllers, $this->text('Administration')), $this->map($action, $actions, "''"));
        $targetText .= ' ELSE ' . $this->text('Record') . ' END';

        $projection = (new Query())->select(['a.*', 'username' => 'actor.username',
            'display_username' => new Expression('COALESCE([[actor.username]], CASE WHEN [[a.actor_id]] IS NULL THEN ' . $this->text('System or visitor') . ' ELSE ' . $this->text('Deleted user') . ' END)'),
            'display_event' => new Expression($this->map('[[a.event]]', SecurityPresentation::events(), $this->text('Other security event'))),
            'display_target' => new Expression($targetText), 'display_details' => new Expression($this->details()),
        ])->from(['a' => '{{%um_audit_log}}'])
            ->leftJoin(['actor' => User::tableName()], '[[actor.id]] = [[a.actor_id]]')
            ->leftJoin(['target_user' => User::tableName()], "[[target_user.id]] = ($userKey)");
        return (new Query())->from(['audit' => $projection]);
    }

    private function details(): string
    {
        $event = '[[a.event]]';
        $row = fn($event, $details = []) => SecurityPresentation::details(['event' => $event, 'details' => json_encode($details)]);
        $sql = 'CASE';
        $providers = [];
        foreach (['google', 'microsoft'] as $provider) $providers[$provider] = $row('external.login', ['provider' => $provider]);
        $sql .= " WHEN $event LIKE 'external.%' THEN " . $this->map($this->json('[[a.details]]', '$.provider'), $providers, $this->quote($row('external.login')));
        $methods = [];
        foreach (['password', 'totp', 'passkey', 'google', 'microsoft'] as $method) $methods[$method] = $row('session.started', ['method' => $method]);
        $sql .= " WHEN $event = 'session.started' THEN " . $this->map($this->json('[[a.details]]', '$.method'), $methods, $this->quote($row('session.started')));

        // Preserve the order of the allowlisted field names, without exposing raw JSON or secrets.
        $fields = ['username', 'status', 'superadmin', 'name', 'description', 'group_code', 'rule_name', 'email_confirmed'];
        $labels = [];
        $template = UM::t('front', 'Affected fields: {fields}.', ['fields' => '{fields}']);
        [$prefix, $suffix] = explode('{fields}', $template, 2);
        foreach ($fields as $field) {
            $text = $row('model.afterUpdate', ['fields' => [$field]]);
            $labels[$field] = mb_substr($text, mb_strlen($prefix), mb_strlen($text) - mb_strlen($prefix) - mb_strlen($suffix));
        }
        $json = $this->json('[[a.details]]', '$.fields');
        $safeJson = "CASE WHEN JSON_VALID($json) THEN $json ELSE 'null' END";
        $safeArray = $this->db->driverName === 'mysql' ? "CASE WHEN JSON_TYPE($json) = 'ARRAY' THEN $json ELSE '[]' END" : "CASE WHEN JSON_TYPE($safeJson) = 'array' THEN $safeJson ELSE '[]' END";
        if ($this->db->driverName === 'mysql') {
            $label = $this->map('[[fields.value]]', $labels, 'NULL');
            $names = "(SELECT GROUP_CONCAT($label ORDER BY [[fields.field_position]] SEPARATOR ', ') FROM JSON_TABLE($safeArray, '$[*]' COLUMNS (field_position FOR ORDINALITY, value VARCHAR(255) PATH '$' NULL ON ERROR)) AS fields)";
        } else {
            $label = $this->map('[[fields.value]]', $labels, 'NULL');
            $names = "(SELECT GROUP_CONCAT($label, ', ') FROM JSON_EACH($safeArray) AS fields)";
        }
        $sql .= " WHEN $event LIKE 'model.%' THEN CASE WHEN $names IS NOT NULL THEN " . $this->concat($this->quote($prefix), $names, $this->quote($suffix)) . ' ELSE ' . $this->quote($row('model.afterUpdate')) . ' END';
        $days = 'COALESCE(' . $this->json('[[a.details]]', '$.days') . ', 0)';
        [$prefix, $suffix] = explode('{days}', UM::t('front', 'Records older than {days} days were removed.', ['days' => '{days}']), 2);
        $sql .= " WHEN $event = 'operator.retention' THEN " . $this->concat($this->quote($prefix), 'CAST(' . $days . ($this->db->driverName === 'mysql' ? ' AS SIGNED)' : ' AS INTEGER)'), $this->quote($suffix));
        foreach (array_keys(SecurityPresentation::events()) as $code) {
            if ($code === 'session.started' || $code === 'operator.retention' || str_starts_with($code, 'external.') || str_starts_with($code, 'model.')) continue;
            $sql .= " WHEN $event = " . $this->quote($code) . ' THEN ' . $this->quote($row($code));
        }
        return $sql . ' ELSE ' . $this->quote($row('unknown')) . ' END';
    }
}
