<?php

namespace Webrek\Outbox\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webrek\Outbox\Contracts\Publisher;
use Webrek\Outbox\MessageStatus;
use Webrek\Outbox\Models\OutboxMessage;
use Webrek\Outbox\OutboxRelay;
use Webrek\Outbox\Tests\Support\RecordingPublisher;
use Webrek\Outbox\Tests\TestCase;

class RetryCommandTest extends TestCase
{
    public function test_retry_all_resets_discarded_messages_to_pending(): void
    {
        $this->seedFailed('a');
        $this->seedFailed('b');
        $this->seedPublished('done');

        $this->artisan('outbox:retry', ['--all' => true])
            ->expectsOutputToContain('Reset 2 message(s) for retry.')
            ->assertExitCode(0);

        $this->assertSame(2, OutboxMessage::query()->where('status', MessageStatus::Pending)->count());
        $this->assertSame(1, OutboxMessage::query()->where('status', MessageStatus::Published)->count());

        $reset = OutboxMessage::query()->where('type', 'a')->firstOrFail();
        $this->assertSame(0, $reset->attempts);
        $this->assertNull($reset->failed_at);
        $this->assertNull($reset->last_error);

        // A reset message is delivered by the next relay pass.
        $this->app->instance(Publisher::class, $publisher = new RecordingPublisher);
        $this->app->make(OutboxRelay::class)->drain(100);
        $this->assertCount(2, $publisher->delivered);
    }

    public function test_retry_targets_specific_ids(): void
    {
        $a = $this->seedFailed('a');
        $this->seedFailed('b');

        $this->artisan('outbox:retry', ['id' => [$a]])->assertExitCode(0);

        $this->assertSame(MessageStatus::Pending, OutboxMessage::query()->findOrFail($a)->status);
        $this->assertSame(MessageStatus::Failed, OutboxMessage::query()->where('type', 'b')->firstOrFail()->status);
    }

    public function test_retry_without_a_target_is_rejected(): void
    {
        $this->seedFailed('a');

        $this->artisan('outbox:retry')
            ->expectsOutputToContain('Specify one or more message IDs, or pass --all.')
            ->assertExitCode(Command::INVALID);

        $this->assertSame(MessageStatus::Failed, OutboxMessage::query()->firstOrFail()->status);
    }

    private function seedFailed(string $type): string
    {
        $id = (string) Str::uuid();

        DB::table('outbox_messages')->insert([
            'id' => $id,
            'type' => $type,
            'payload' => json_encode([]),
            'status' => MessageStatus::Failed->value,
            'attempts' => 10,
            'available_at' => Carbon::now(),
            'failed_at' => Carbon::now(),
            'last_error' => 'boom',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return $id;
    }

    private function seedPublished(string $type): void
    {
        DB::table('outbox_messages')->insert([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'payload' => json_encode([]),
            'status' => MessageStatus::Published->value,
            'attempts' => 1,
            'available_at' => Carbon::now(),
            'dispatched_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
