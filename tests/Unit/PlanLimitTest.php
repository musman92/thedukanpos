<?php

namespace Tests\Unit;

use App\Support\PlanLimit;
use PHPUnit\Framework\TestCase;

class PlanLimitTest extends TestCase
{
    public function test_a_blank_limit_does_not_block(): void
    {
        $this->assertFalse(PlanLimit::exceeded(null, 500));
    }

    public function test_the_next_record_is_blocked_once_the_cap_is_reached(): void
    {
        $this->assertFalse(PlanLimit::exceeded(10, 9));
        $this->assertTrue(PlanLimit::exceeded(10, 10));
        $this->assertSame(
            'The Free plan allows 10 products. This shop already has 10.',
            PlanLimit::message('Free', 'products', 10, 10),
        );
    }
}
