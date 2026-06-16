<?php

namespace Webrek\Outbox\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Webrek\Outbox\Contracts\Publisher;
use Webrek\Outbox\MessageStatus;
use Webrek\Outbox\Models\OutboxMessage;
use Webrek\Outbox\Outbox;
use Webrek\Outbox\OutboxRelay;
use Webrek\Outbox\Tests\Support\RecordingPublisher;
use Webrek\Outbox\Tests\TestCase;

class MutationCoverageTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_oldest_due_message_is_claimed_first(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $this->seedPending('newer', Carbon::now());
        $this->seedPending('older', Carbon::now()->subSeconds(30));

        $publisher = new RecordingPublisher;
        $this->app->instance(Publisher::class, $publisher);

        $this->app->make(OutboxRelay::class)->drain(1);

        $delivered = OutboxMessage::query()->findOrFail($publisher->delivered[0]);
        $this->assertSame('older', $delivered->type);
    }

    public function test_a_message_scheduled_for_the_future_is_not_claimed(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $this->seedPending('future', Carbon::now()->addMinutes(5));

        $this->app->instance(Publisher::class, new RecordingPublisher);

        $this->assertSame(0, $this->app->make(OutboxRelay::class)->drain(100));
        $this->assertSame(MessageStatus::Pending, OutboxMessage::query()->firstOrFail()->status);
    }

    public function test_long_errors_are_truncated(): void
    {
        $this->app->instance(Publisher::class, new class implements Publisher
        {
            public function publish(OutboxMessage $message): void
            {
                throw new RuntimeException(str_repeat('x', 5000));
            }
        });

        (new Outbox)->publish('order.placed');

        $this->app->make(OutboxRelay::class)->drain(100);

        $error = OutboxMessage::query()->firstOrFail()->last_error;

        $this->assertNotNull($error);
        $this->assertSame(1000, strlen($error));
    }

    public function test_prune_leaves_pending_and_failed_messages_untouched(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $this->seedPending('still-pending', Carbon::now()->subYear());
        DB::table('outbox_messages')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'dead',
            'payload' => json_encode([]),
            'status' => MessageStatus::Failed->value,
            'attempts' => 10,
            'available_at' => Carbon::now()->subYear(),
            'failed_at' => Carbon::now()->subYear(),
            'created_at' => Carbon::now()->subYear(),
            'updated_at' => Carbon::now()->subYear(),
        ]);

        $this->artisan('outbox:prune', ['--hours' => 1])->assertExitCode(0);

        $this->assertSame(2, OutboxMessage::query()->count());
    }

    private function seedPending(string $type, Carbon $availableAt): void
    {
        DB::table('outbox_messages')->insert([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'payload' => json_encode([]),
            'status' => MessageStatus::Pending->value,
            'attempts' => 0,
            'available_at' => $availableAt,
            'created_at' => $availableAt,
            'updated_at' => $availableAt,
        ]);
    }
}
