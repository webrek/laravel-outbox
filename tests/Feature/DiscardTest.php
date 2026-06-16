<?php

namespace Webrek\Outbox\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Webrek\Outbox\Contracts\Publisher;
use Webrek\Outbox\Events\OutboxMessageDiscarded;
use Webrek\Outbox\MessageStatus;
use Webrek\Outbox\Models\OutboxMessage;
use Webrek\Outbox\Outbox;
use Webrek\Outbox\OutboxRelay;
use Webrek\Outbox\Tests\Support\RecordingPublisher;
use Webrek\Outbox\Tests\TestCase;

class DiscardTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_message_is_discarded_after_exhausting_its_attempts(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');
        config()->set('outbox.max_attempts', 3);

        Event::fake([OutboxMessageDiscarded::class]);

        $this->app->instance(Publisher::class, new RecordingPublisher(alwaysFail: true));

        (new Outbox)->publish('order.placed');

        $relay = $this->app->make(OutboxRelay::class);

        foreach (range(1, 3) as $i) {
            $relay->drain(100);
            Carbon::setTestNow(Carbon::now()->addHour());
        }

        $message = OutboxMessage::query()->firstOrFail();

        $this->assertSame(MessageStatus::Failed, $message->status);
        $this->assertSame(3, $message->attempts);
        $this->assertNotNull($message->failed_at);

        // A discarded message is never claimed again.
        $this->assertSame(0, $relay->drain(100));

        Event::assertDispatchedTimes(OutboxMessageDiscarded::class, 1);
    }
}
