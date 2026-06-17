<?php

namespace Webrek\Outbox\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webrek\Outbox\MessageStatus;
use Webrek\Outbox\Outbox;
use Webrek\Outbox\Tests\TestCase;

class StatusCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_status_reports_counts_and_the_oldest_pending(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $outbox = new Outbox;
        $outbox->publish('a');
        $outbox->publish('b');
        $this->seedMessage(MessageStatus::Published);
        $this->seedMessage(MessageStatus::Failed);

        $code = Artisan::call('outbox:status');
        $output = Artisan::output();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('pending', $output);
        $this->assertStringContainsString('published', $output);
        $this->assertStringContainsString('Oldest pending:', $output);
        // Two pending messages should be reflected in the table.
        $this->assertStringContainsString('2', $output);
    }

    public function test_status_runs_against_an_empty_outbox(): void
    {
        $code = Artisan::call('outbox:status');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('none', Artisan::output());
    }

    private function seedMessage(MessageStatus $status): void
    {
        DB::table('outbox_messages')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'seeded',
            'payload' => json_encode([]),
            'status' => $status->value,
            'attempts' => 1,
            'available_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
