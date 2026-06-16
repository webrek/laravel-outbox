<?php

namespace Webrek\Outbox\Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Webrek\Outbox\Models\OutboxMessage;
use Webrek\Outbox\Outbox;
use Webrek\Outbox\Tests\TestCase;

class TransactionalTest extends TestCase
{
    public function test_a_committed_transaction_stages_the_message(): void
    {
        DB::transaction(function (): void {
            DB::table('widgets')->insert(['name' => 'kept']);
            (new Outbox)->publish('widget.created', ['name' => 'kept']);
        });

        $this->assertSame(1, DB::table('widgets')->count());
        $this->assertSame(1, OutboxMessage::query()->count());
    }

    public function test_a_rolled_back_transaction_stages_nothing(): void
    {
        try {
            DB::transaction(function (): void {
                DB::table('widgets')->insert(['name' => 'doomed']);
                (new Outbox)->publish('widget.created', ['name' => 'doomed']);

                throw new RuntimeException('business rule failed');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, DB::table('widgets')->count());
        $this->assertSame(0, OutboxMessage::query()->count());
    }
}
