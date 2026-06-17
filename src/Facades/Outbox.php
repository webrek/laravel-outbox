<?php

namespace Webrek\Outbox\Facades;

use Illuminate\Support\Facades\Facade;
use Webrek\Outbox\Testing\OutboxFake;

/**
 * @method static \Webrek\Outbox\Models\OutboxMessage publish(string $type, array<string, mixed> $payload = [], array<string, mixed> $headers = [])
 * @method static void assertPublished(string $type, \Closure|null $callback = null)
 * @method static void assertNotPublished(string $type, \Closure|null $callback = null)
 * @method static void assertPublishedTimes(string $type, int $times = 1)
 * @method static void assertNothingPublished()
 *
 * @see \Webrek\Outbox\Outbox
 */
class Outbox extends Facade
{
    public static function fake(): OutboxFake
    {
        static::swap($fake = new OutboxFake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'outbox';
    }
}
