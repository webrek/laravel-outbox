<?php

namespace Webrek\Outbox\Publishers;

use Illuminate\Contracts\Events\Dispatcher;
use Webrek\Outbox\Contracts\Publisher;
use Webrek\Outbox\Events\OutboxMessageReady;
use Webrek\Outbox\Models\OutboxMessage;

/**
 * Default publisher: turns each message into an `OutboxMessageReady` event so
 * the application can deliver it from a listener without writing a Publisher.
 * Delivery is synchronous — a listener that throws reschedules the message.
 */
class EventPublisher implements Publisher
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function publish(OutboxMessage $message): void
    {
        $this->events->dispatch(new OutboxMessageReady($message));
    }
}
