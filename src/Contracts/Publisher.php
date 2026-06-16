<?php

namespace Webrek\Outbox\Contracts;

use Webrek\Outbox\Models\OutboxMessage;

interface Publisher
{
    /**
     * Deliver a staged message to its destination.
     *
     * Implementations must be synchronous: throwing leaves the message in the
     * outbox to be retried, returning normally marks it as published.
     */
    public function publish(OutboxMessage $message): void;
}
