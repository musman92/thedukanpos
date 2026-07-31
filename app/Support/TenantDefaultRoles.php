<?php

namespace App\Support;

/**
 * Built-in tenant roles. Protected names cannot be deleted or renamed.
 */
final class TenantDefaultRoles
{
    public const ADMINISTRATOR = 'Administrator';

    public const MANAGER = 'Manager';

    public const CASHIER = 'Cashier';

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return [
            self::ADMINISTRATOR,
            self::MANAGER,
            self::CASHIER,
        ];
    }

    public static function isProtected(?string $name): bool
    {
        return $name !== null && in_array($name, self::names(), true);
    }
}
