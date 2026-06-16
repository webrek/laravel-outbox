<?php

namespace Webrek\Outbox\Events;

use Webrek\Outbox\Models\OutboxMessage;

/**
 * Dispatched by the default EventPublisher for each message the relay delivers.
 * Listen for it to perform the actual outbound work; throwing from a listener
 * reschedules the message for another attempt.
 */
class OutboxMessageReady
{
    public function __construct(
        public readonly OutboxMessage $message,
    ) {}
}
