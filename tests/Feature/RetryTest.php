<?php

namespace Webrek\Outbox\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Webrek\Outbox\Contracts\Publisher;
use Webrek\Outbox\Events\OutboxMessageFailed;
use Webrek\Outbox\MessageStatus;
use Webrek\Outbox\Models\OutboxMessage;
use Webrek\Outbox\Outbox;
use Webrek\Outbox\OutboxRelay;
use Webrek\Outbox\Tests\Support\RecordingPublisher;
use Webrek\Outbox\Tests\TestCase;

class RetryTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_failed_attempt_reschedules_with_backoff(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');
        Event::fake([OutboxMessageFailed::class]);

        $this->app->instance(Publisher::class, new RecordingPublisher(failTimes: 1));

        (new Outbox)->publish('order.placed');

        $this->app->make(OutboxRelay::class)->drain(100);

        $message = OutboxMessage::query()->firstOrFail();

        $this->assertSame(MessageStatus::Pending, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertNotNull($message->last_error);
        // base 10s * 2^(1-1) = 10s in the future.
        $this->assertTrue($message->available_at->equalTo(Carbon::now()->addSeconds(10)));

        Event::assertDispatched(OutboxMessageFailed::class);
    }

    public function test_a_backing_off_message_is_skipped_until_it_is_due(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $this->app->instance(Publisher::class, new RecordingPublisher(failTimes: 1));

        (new Outbox)->publish('order.placed');

        $relay = $this->app->make(OutboxRelay::class);
        $relay->drain(100); // first attempt fails, schedules +10s

        $this->assertSame(0, $relay->drain(100)); // still backing off

        Carbon::setTestNow(Carbon::now()->addSeconds(11));

        $this->assertSame(1, $relay->drain(100)); // now due, succeeds

        $message = OutboxMessage::query()->firstOrFail();
        $this->assertSame(MessageStatus::Published, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertNotNull($message->dispatched_at);
    }
}
