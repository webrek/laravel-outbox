<?php

namespace Webrek\Outbox\Events;

use Throwable;
use Webrek\Outbox\Models\OutboxMessage;

/**
 * Fired when a delivery attempt fails but the message will be retried.
 */
class OutboxMessageFailed
{
    public function __construct(
        public readonly OutboxMessage $message,
        public readonly Throwable $exception,
    ) {}
}
