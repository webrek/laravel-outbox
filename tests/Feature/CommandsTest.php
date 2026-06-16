<?php

namespace Webrek\Outbox\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webrek\Outbox\Contracts\Publisher;
use Webrek\Outbox\MessageStatus;
use Webrek\Outbox\Models\OutboxMessage;
use Webrek\Outbox\Outbox;
use Webrek\Outbox\Tests\Support\RecordingPublisher;
use Webrek\Outbox\Tests\TestCase;

class CommandsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_work_once_relays_pending_messages(): void
    {
        $publisher = new RecordingPublisher;
        $this->app->instance(Publisher::class, $publisher);

        $outbox = new Outbox;
        $outbox->publish('a');
        $outbox->publish('b');

        $this->artisan('outbox:work', ['--once' => true])->assertExitCode(0);

        $this->assertCount(2, $publisher->delivered);
        $this->assertSame(2, OutboxMessage::query()->where('status', MessageStatus::Published)->count());
    }

    public function test_prune_deletes_only_old_published_messages(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $this->seedPublished('old', Carbon::now()->subHours(48));
        $this->seedPublished('recent', Carbon::now()->subHour());
        (new Outbox)->publish('pending');

        $this->artisan('outbox:prune', ['--hours' => 24])->assertExitCode(0);

        $this->assertSame(2, OutboxMessage::query()->count());
        $this->assertNull(OutboxMessage::query()->where('type', 'old')->first());
        $this->assertNotNull(OutboxMessage::query()->where('type', 'recent')->first());
    }

    public function test_prune_uses_the_configured_retention_by_default(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');
        config()->set('outbox.prune.retention_hours', 168);

        $this->seedPublished('ancient', Carbon::now()->subHours(200));
        $this->seedPublished('within-window', Carbon::now()->subHours(100));

        $this->artisan('outbox:prune')
            ->expectsOutputToContain('Pruned 1 published outbox message(s).')
            ->assertExitCode(0);

        $this->assertNull(OutboxMessage::query()->where('type', 'ancient')->first());
        $this->assertNotNull(OutboxMessage::query()->where('type', 'within-window')->first());
    }

    private function seedPublished(string $type, Carbon $dispatchedAt): void
    {
        DB::table('outbox_messages')->insert([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'payload' => json_encode([]),
            'status' => MessageStatus::Published->value,
            'attempts' => 1,
            'available_at' => $dispatchedAt,
            'dispatched_at' => $dispatchedAt,
            'created_at' => $dispatchedAt,
            'updated_at' => $dispatchedAt,
        ]);
    }
}
