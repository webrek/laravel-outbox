<?php

namespace Webrek\Outbox\Tests\Feature;

use Illuminate\Support\Facades\Event;
use RuntimeException;
use Webrek\Outbox\Events\OutboxMessageReady;
use Webrek\Outbox\MessageStatus;
use Webrek\Outbox\Models\OutboxMessage;
use Webrek\Outbox\Outbox;
use Webrek\Outbox\OutboxRelay;
use Webrek\Outbox\Tests\TestCase;

class EventPublisherTest extends TestCase
{
    public function test_the_default_publisher_emits_a_ready_event_per_message(): void
    {
        $seen = [];
        Event::listen(OutboxMessageReady::class, function (OutboxMessageReady $event) use (&$seen): void {
            $seen[] = $event->message->type;
        });

        (new Outbox)->publish('order.placed', ['id' => 1]);

        $this->app->make(OutboxRelay::class)->drain(100);

        $this->assertSame(['order.placed'], $seen);
        $this->assertSame(MessageStatus::Published, OutboxMessage::query()->firstOrFail()->status);
    }

    public function test_a_throwing_listener_reschedules_the_message(): void
    {
        Event::listen(OutboxMessageReady::class, function (): void {
            throw new RuntimeException('listener blew up');
        });

        (new Outbox)->publish('order.placed');

        $this->app->make(OutboxRelay::class)->drain(100);

        $message = OutboxMessage::query()->firstOrFail();

        $this->assertSame(MessageStatus::Pending, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertSame('listener blew up', $message->last_error);
    }
}
