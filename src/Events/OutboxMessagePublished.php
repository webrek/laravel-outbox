<?php

namespace Webrek\Outbox\Events;

use Webrek\Outbox\Models\OutboxMessage;

/**
 * Fired after a message has been delivered successfully.
 */
class OutboxMessagePublished
{
    public function __construct(
        public readonly OutboxMessage $message,
    ) {}
}
