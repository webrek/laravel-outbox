<?php

namespace Webrek\Outbox\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webrek\Outbox\Contracts\Publisher;
use Webrek\Outbox\MessageStatus;
use Webrek\Outbox\Models\OutboxMessage;
use Webrek\Outbox\OutboxRelay;
use Webrek\Outbox\Tests\Support\RecordingPublisher;
use Webrek\Outbox\Tests\TestCase;

class StaleReclaimTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_reclaims_messages_left_processing_by_a_dead_worker(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');
        config()->set('outbox.claim_timeout', 300);

        $this->seedProcessing('stale', Carbon::now()->subMinutes(10));
        $this->seedProcessing('fresh', Carbon::now());

        $publisher = new RecordingPublisher;
        $this->app->instance(Publisher::class, $publisher);

        $processed = $this->app->make(OutboxRelay::class)->drain(100);

        $this->assertSame(1, $processed);
        $this->assertSame(['stale'], array_map(
            fn (string $id): string => OutboxMessage::query()->findOrFail($id)->type,
            $publisher->delivered,
        ));

        $this->assertSame(MessageStatus::Published, OutboxMessage::query()->where('type', 'stale')->firstOrFail()->status);
        $this->assertSame(MessageStatus::Processing, OutboxMessage::query()->where('type', 'fresh')->firstOrFail()->status);
    }

    private function seedProcessing(string $type, Carbon $updatedAt): void
    {
        DB::table('outbox_messages')->insert([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'payload' => json_encode([]),
            'headers' => null,
            'status' => MessageStatus::Processing->value,
            'attempts' => 0,
            'available_at' => Carbon::now()->subMinute(),
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);
    }
}
