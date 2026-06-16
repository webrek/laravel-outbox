<?php

namespace Webrek\Outbox\Tests\Support;

use RuntimeException;
use Webrek\Outbox\Contracts\Publisher;
use Webrek\Outbox\Models\OutboxMessage;

/**
 * Test publisher that records delivered messages and can be told to fail a set
 * number of times (or always) before succeeding.
 */
class RecordingPublisher implements Publisher
{
    /** @var list<string> */
    public array $delivered = [];

    public int $calls = 0;

    public function __construct(
        private int $failTimes = 0,
        private readonly bool $alwaysFail = false,
    ) {}

    public function publish(OutboxMessage $message): void
    {
        $this->calls++;

        if ($this->alwaysFail) {
            throw new RuntimeException('permanent failure');
        }

        if ($this->failTimes > 0) {
            $this->failTimes--;

            throw new RuntimeException('transient failure');
        }

        $this->delivered[] = $message->id;
    }
}
