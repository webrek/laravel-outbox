<?php

namespace Webrek\Outbox\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Webrek\Outbox\Contracts\Publisher;
use Webrek\Outbox\Events\OutboxMessagePublished;
use Webrek\Outbox\MessageStatus;
use Webrek\Outbox\Models\OutboxMessage;
use Webrek\Outbox\Outbox;
use Webrek\Outbox\OutboxRelay;
use Webrek\Outbox\Tests\Support\RecordingPublisher;
use Webrek\Outbox\Tests\TestCase;

class RelayTest extends TestCase
{
    public function test_it_delivers_pending_messages_and_marks_them_published(): void
    {
        $publisher = new RecordingPublisher;
        $this->app->instance(Publisher::class, $publisher);

        Event::fake([OutboxMessagePublished::class]);

        $outbox = new Outbox;
        $outbox->publish('a');
        $outbox->publish('b');
        $outbox->publish('c');

        $processed = $this->app->make(OutboxRelay::class)->drain(100);

        $this->assertSame(3, $processed);
        $this->assertCount(3, $publisher->delivered);
        $this->assertSame(3, OutboxMessage::query()->where('status', MessageStatus::Published)->count());

        foreach (OutboxMessage::all() as $message) {
            $this->assertSame(0, $message->attempts);
            $this->assertNotNull($message->dispatched_at);
        }

        Event::assertDispatchedTimes(OutboxMessagePublished::class, 3);
    }

    public function test_draining_an_empty_outbox_processes_nothing(): void
    {
        $this->app->instance(Publisher::class, new RecordingPublisher);

        $this->assertSame(0, $this->app->make(OutboxRelay::class)->drain(100));
    }

    public function test_claim_respects_the_batch_limit(): void
    {
        $this->app->instance(Publisher::class, new RecordingPublisher);

        $outbox = new Outbox;
        foreach (range(1, 5) as $i) {
            $outbox->publish("m{$i}");
        }

        $relay = $this->app->make(OutboxRelay::class);

        $this->assertSame(2, $relay->drain(2));
        $this->assertSame(3, OutboxMessage::query()->where('status', MessageStatus::Pending)->count());
    }
}
