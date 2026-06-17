<?php

namespace Webrek\Outbox\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Webrek\Outbox\Support\Backoff;

class BackoffTest extends TestCase
{
    public function test_grows_exponentially_from_the_base(): void
    {
        $backoff = new Backoff(base: 10, max: 10000, multiplier: 2.0);

        $this->assertSame(10, $backoff->secondsFor(1));
        $this->assertSame(20, $backoff->secondsFor(2));
        $this->assertSame(40, $backoff->secondsFor(3));
        $this->assertSame(80, $backoff->secondsFor(4));
    }

    public function test_is_capped_at_the_maximum(): void
    {
        $backoff = new Backoff(base: 10, max: 50, multiplier: 2.0);

        $this->assertSame(50, $backoff->secondsFor(10));
    }

    public function test_clamps_attempt_numbers_below_one(): void
    {
        $backoff = new Backoff(base: 7, max: 1000, multiplier: 2.0);

        $this->assertSame(7, $backoff->secondsFor(0));
        $this->assertSame(7, $backoff->secondsFor(-5));
    }

    public function test_honours_a_custom_multiplier(): void
    {
        $backoff = new Backoff(base: 5, max: 10000, multiplier: 3.0);

        $this->assertSame(5, $backoff->secondsFor(1));
        $this->assertSame(15, $backoff->secondsFor(2));
        $this->assertSame(45, $backoff->secondsFor(3));
    }

    public function test_always_returns_an_integer(): void
    {
        $backoff = new Backoff(base: 10, max: 10000, multiplier: 2.0);

        $this->assertIsInt($backoff->secondsFor(1));
        $this->assertIsInt($backoff->secondsFor(6));
    }

    public function test_builds_from_config_with_defaults(): void
    {
        $backoff = Backoff::fromConfig([]);

        $this->assertSame(10, $backoff->secondsFor(1));
        $this->assertSame(20, $backoff->secondsFor(2));
        // Default ceiling of 3600s kicks in for large attempt numbers.
        $this->assertSame(3600, $backoff->secondsFor(20));
    }

    public function test_builds_from_explicit_config(): void
    {
        $backoff = Backoff::fromConfig([
            'base_seconds' => 30,
            'max_seconds' => 100,
            'multiplier' => 2,
        ]);

        $this->assertSame(30, $backoff->secondsFor(1));
        $this->assertSame(60, $backoff->secondsFor(2));
        $this->assertSame(100, $backoff->secondsFor(3));
    }

    public function test_jitter_stays_within_bounds_and_varies(): void
    {
        $backoff = new Backoff(base: 100, max: 100000, multiplier: 2.0, jitter: 0.5);

        $results = [];
        for ($i = 0; $i < 50; $i++) {
            $results[] = $backoff->secondsFor(1);
        }

        foreach ($results as $value) {
            $this->assertGreaterThanOrEqual(100, $value);   // never below the base delay
            $this->assertLessThanOrEqual(150, $value);       // base + up to 50%
        }

        // Jitter actually moved the values around rather than returning a constant.
        $this->assertGreaterThan(1, count(array_unique($results)));
    }

    public function test_jitter_never_exceeds_the_ceiling(): void
    {
        $backoff = new Backoff(base: 1000, max: 1000, multiplier: 2.0, jitter: 1.0);

        for ($i = 0; $i < 25; $i++) {
            $this->assertSame(1000, $backoff->secondsFor(1));
        }
    }
}
