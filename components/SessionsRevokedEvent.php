<?php
namespace webvimark\modules\UserManagement\components;

/** Public notification; contains identifiers only, never authentication material. */
final class SessionsRevokedEvent extends \yii\base\Event
{
    public int $userId;
    /** @var string[] SHA-256 session identifiers, not browser cookies or bearer tokens. */
    public array $sessionIds = [];
    public string $reason;
}
