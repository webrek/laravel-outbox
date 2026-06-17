<?php

namespace Webrek\Outbox\Testing;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert as PHPUnit;
use Webrek\Outbox\MessageStatus;
use Webrek\Outbox\Models\OutboxMessage;
use Webrek\Outbox\Outbox;

/**
 * Test double swapped in by `Outbox::fake()`. Records staged messages in memory
 * instead of writing to the database, so application tests can assert what would
 * be published without running the relay.
 */
class OutboxFake extends Outbox
{
    /** @var list<OutboxMessage> */
    public array $messages = [];

    public function publish(string $type, array $payload = [], array $headers = []): OutboxMessage
    {
        $message = new OutboxMessage([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'payload' => $payload,
            'headers' => $headers === [] ? null : $headers,
            'status' => MessageStatus::Pending,
            'attempts' => 0,
            'available_at' => Carbon::now(),
        ]);

        $this->messages[] = $message;

        return $message;
    }

    public function assertPublished(string $type, ?Closure $callback = null): void
    {
        PHPUnit::assertTrue(
            count($this->published($type, $callback)) > 0,
            "The expected outbox message [{$type}] was not published.",
        );
    }

    public function assertNotPublished(string $type, ?Closure $callback = null): void
    {
        PHPUnit::assertCount(
            0,
            $this->published($type, $callback),
            "The unexpected outbox message [{$type}] was published.",
        );
    }

    public function assertPublishedTimes(string $type, int $times = 1): void
    {
        $count = count($this->published($type));

        PHPUnit::assertSame(
            $times,
            $count,
            "The outbox message [{$type}] was published {$count} time(s) instead of {$times}.",
        );
    }

    public function assertNothingPublished(): void
    {
        PHPUnit::assertCount(
            0,
            $this->messages,
            'Expected no outbox messages, but some were published.',
        );
    }

    /**
     * @return list<OutboxMessage>
     */
    public function published(string $type, ?Closure $callback = null): array
    {
        return array_values(array_filter(
            $this->messages,
            fn (OutboxMessage $message): bool => $message->type === $type
                && ($callback === null || $callback($message)),
        ));
    }
}
