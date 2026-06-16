<?php

namespace Webrek\Outbox\Events;

use Throwable;
use Webrek\Outbox\Models\OutboxMessage;

/**
 * Fired when a message has exhausted its retry budget and will not be retried.
 */
class OutboxMessageDiscarded
{
    public function __construct(
        public readonly OutboxMessage $message,
        public readonly Throwable $exception,
    ) {}
}
