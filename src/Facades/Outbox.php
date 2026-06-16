<?php

namespace Webrek\Outbox\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Webrek\Outbox\Models\OutboxMessage publish(string $type, array<string, mixed> $payload = [], array<string, mixed> $headers = [])
 *
 * @see \Webrek\Outbox\Outbox
 */
class Outbox extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'outbox';
    }
}
