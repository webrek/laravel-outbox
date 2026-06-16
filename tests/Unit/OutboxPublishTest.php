<?php

namespace Webrek\Outbox\Tests\Unit;

use Webrek\Outbox\MessageStatus;
use Webrek\Outbox\Models\OutboxMessage;
use Webrek\Outbox\Outbox;
use Webrek\Outbox\Tests\TestCase;

class OutboxPublishTest extends TestCase
{
    public function test_publish_stages_a_pending_message(): void
    {
        $message = (new Outbox)->publish('order.placed', ['id' => 7], ['tenant' => 'acme']);

        $this->assertDatabaseCount('outbox_messages', 1);

        $fresh = $message->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame('order.placed', $fresh->type);
        $this->assertSame(['id' => 7], $fresh->payload);
        $this->assertSame(['tenant' => 'acme'], $fresh->headers);
        $this->assertSame(MessageStatus::Pending, $fresh->status);
        $this->assertSame(0, $fresh->attempts);
        $this->assertNotNull($fresh->available_at);
        $this->assertNull($fresh->dispatched_at);
    }

    public function test_message_id_is_a_uuid(): void
    {
        $message = (new Outbox)->publish('order.placed');

        $this->assertIsString($message->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $message->id,
        );
    }

    public function test_empty_headers_are_stored_as_null(): void
    {
        (new Outbox)->publish('order.placed', ['id' => 1]);

        $message = OutboxMessage::query()->firstOrFail();

        $this->assertNull($message->headers);
    }

    public function test_payload_defaults_to_an_empty_array(): void
    {
        $message = (new Outbox)->publish('ping');

        $this->assertSame([], $message->fresh()?->payload);
    }
}
