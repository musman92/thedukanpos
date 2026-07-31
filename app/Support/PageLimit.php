<?php

namespace App\Support;

use App\Models\CompanySetting;

class PageLimit
{
    /** @var list<int> */
    public const ALLOWED = [10, 15, 25, 50];

    public const DEFAULT = 15;

    public static function companyDefault(): int
    {
        return once(function () {
            $limit = (int) CompanySetting::getValue('list_page_limit', self::DEFAULT);

            return in_array($limit, self::ALLOWED, true) ? $limit : self::DEFAULT;
        });
    }

    public static function resolve(mixed $requested, ?int $fallback = null): int
    {
        $fallback ??= self::companyDefault();
        $perPage = (int) ($requested ?? $fallback);

        return in_array($perPage, self::ALLOWED, true) ? $perPage : $fallback;
    }
}
