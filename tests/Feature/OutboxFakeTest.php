<?php

namespace Webrek\Outbox\Tests\Feature;

use PHPUnit\Framework\AssertionFailedError;
use Webrek\Outbox\Facades\Outbox;
use Webrek\Outbox\Models\OutboxMessage;
use Webrek\Outbox\Outbox as OutboxManager;
use Webrek\Outbox\Testing\OutboxFake;
use Webrek\Outbox\Tests\TestCase;

class OutboxFakeTest extends TestCase
{
    public function test_fake_records_messages_without_touching_the_database(): void
    {
        Outbox::fake();

        Outbox::publish('order.placed', ['id' => 7], ['tenant' => 'acme']);

        $this->assertSame(0, OutboxMessage::query()->count());
    }

    public function test_fake_replaces_the_resolved_manager(): void
    {
        Outbox::fake();

        $this->assertInstanceOf(OutboxFake::class, $this->app->make(OutboxManager::class));
    }

    public function test_assert_published_matches_type_and_callback(): void
    {
        Outbox::fake();

        Outbox::publish('order.placed', ['id' => 7]);

        Outbox::assertPublished('order.placed');
        Outbox::assertPublished('order.placed', fn (OutboxMessage $m): bool => $m->payload['id'] === 7);
        Outbox::assertPublishedTimes('order.placed', 1);
        Outbox::assertNotPublished('order.cancelled');
    }

    public function test_assert_published_fails_when_the_callback_does_not_match(): void
    {
        Outbox::fake();

        Outbox::publish('order.placed', ['id' => 7]);

        $this->expectException(AssertionFailedError::class);

        Outbox::assertPublished('order.placed', fn (OutboxMessage $m): bool => $m->payload['id'] === 999);
    }

    public function test_assert_nothing_published(): void
    {
        Outbox::fake();

        Outbox::assertNothingPublished();
    }

    public function test_published_returns_matching_messages(): void
    {
        $fake = Outbox::fake();

        Outbox::publish('a');
        Outbox::publish('a');
        Outbox::publish('b');

        $this->assertCount(2, $fake->published('a'));
        Outbox::assertPublishedTimes('a', 2);
        Outbox::assertPublishedTimes('b', 1);
    }
}
