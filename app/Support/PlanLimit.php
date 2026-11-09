<?php

namespace App\Support;

final class PlanLimit
{
    public static function exceeded(?int $limit, int $count, int $adding = 1): bool
    {
        return $limit !== null && ($count + $adding) > $limit;
    }

    public static function message(string $planName, string $noun, int $limit, int $count): string
    {
        $plan = trim($planName) !== '' ? trim($planName) : 'current';

        return "The {$plan} plan allows {$limit} {$noun}. This shop already has {$count}.";
    }
}
