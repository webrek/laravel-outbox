# Laravel Outbox

[![Latest Version on Packagist](https://img.shields.io/packagist/v/webrek/laravel-outbox.svg?style=flat-square)](https://packagist.org/packages/webrek/laravel-outbox)
[![Total Downloads](https://img.shields.io/packagist/dt/webrek/laravel-outbox.svg?style=flat-square)](https://packagist.org/packages/webrek/laravel-outbox)
[![Tests](https://img.shields.io/github/actions/workflow/status/webrek/laravel-outbox/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/webrek/laravel-outbox/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/webrek/laravel-outbox.svg?style=flat-square)](https://php.net)
[![License](https://img.shields.io/packagist/l/webrek/laravel-outbox.svg?style=flat-square)](LICENSE)

A transactional outbox for Laravel. Stage a message **inside the same database
transaction** as your business write, and a relay delivers it afterwards with
retries and backoff. The write and the message commit together — either both
land or neither does — so you never publish an event for a change that rolled
back, and you never lose an event for a change that committed.

This is the producer half of *exactly-once*. Pair it with
[webrek/laravel-idempotency](https://github.com/webrek/laravel-idempotency) on
the consumer to get end-to-end exactly-once effects over at-least-once
infrastructure.

## Why

Dispatching a queued job, firing a webhook, or publishing to a broker *after*
saving a model is a dual-write: if the process dies between the commit and the
dispatch, the side effect is lost. Doing it *before* the commit is worse — the
effect fires even if the transaction rolls back. The outbox pattern removes the
gap by writing the intent to the same database, in the same transaction, and
delivering it from there.

```php
use Illuminate\Support\Facades\DB;
use Webrek\Outbox\Facades\Outbox;

DB::transaction(function () use ($request) {
    $order = Order::create($request->validated());

    // Commits atomically with the order. No order, no message — and vice versa.
    Outbox::publish('order.placed', ['order_id' => $order->id]);
});
```

## Install

```bash
composer require webrek/laravel-outbox
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag=outbox-migrations
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=outbox-config
```

> The outbox table must live on the **same connection** as the data you stage
> messages alongside — atomicity only spans a single connection's transaction.
> Set `outbox.connection` accordingly (it defaults to your default connection).

## Relaying messages

Run the relay as a long-lived worker (like `queue:work`):

```bash
php artisan outbox:work
```

It claims due messages with a row lock — safe to run several workers in
parallel — hands each to a **publisher**, and marks it published. A failed
delivery is retried with exponential backoff up to `max_attempts`, after which
the message is discarded. A message left `processing` by a crashed worker is
reclaimed once `claim_timeout` passes.

Process a single batch and exit (handy for scheduling or tests):

```bash
php artisan outbox:work --once
```

Trim delivered messages on a schedule:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('outbox:prune')->daily();   // keeps the last `prune.retention_hours`
```

## Delivering messages

How a message reaches the outside world is up to a **publisher**. Out of the box
the package ships `EventPublisher`, which turns every message into an
`OutboxMessageReady` event you can listen for:

```php
use Webrek\Outbox\Events\OutboxMessageReady;

Event::listen(OutboxMessageReady::class, function (OutboxMessageReady $event) {
    $message = $event->message;

    Http::post('https://example.test/hooks', $message->payload)->throw();
});
```

Delivery is synchronous: if the listener throws, the message is rescheduled; if
it returns, the message is marked published.

Prefer a dedicated class? Implement the contract and point the config at it:

```php
use Webrek\Outbox\Contracts\Publisher;
use Webrek\Outbox\Models\OutboxMessage;

class BrokerPublisher implements Publisher
{
    public function publish(OutboxMessage $message): void
    {
        // push to Kafka / RabbitMQ / SNS / an HTTP endpoint…
        // throw to retry, return to acknowledge.
    }
}
```

```php
// config/outbox.php
'publisher' => App\Outbox\BrokerPublisher::class,
```

## Observability

The relay fires lifecycle events you can hook into for metrics and alerting:

| Event | When |
| --- | --- |
| `OutboxMessagePublished` | A message was delivered successfully. |
| `OutboxMessageFailed` | An attempt failed; the message will be retried. |
| `OutboxMessageDiscarded` | The retry budget was exhausted; the message is given up on. |

Each carries the `OutboxMessage`; the failure events also carry the `Throwable`.

## Recovering discarded messages

A message that exhausts its retry budget is marked `failed` and left in the
table for inspection — never silently dropped. Once you have fixed the
downstream, reset messages back to `pending` so the relay tries them again with
a fresh budget:

```bash
php artisan outbox:retry --all          # every discarded message
php artisan outbox:retry <id> <id> …    # specific messages
```

To spread the retries of a large backlog so they do not all fire at once, raise
`retry.jitter` (0–1) before reprocessing.

## Inspecting the outbox

See how many messages sit in each state — and how stale the oldest pending one
is — at a glance:

```bash
php artisan outbox:status
```

## Faking it in tests

`Outbox::fake()` swaps the outbox for an in-memory recorder, so your application
tests can assert what would be published without writing to the database or
running the relay:

```php
use Webrek\Outbox\Facades\Outbox;

Outbox::fake();

$this->post('/orders', [...]);

Outbox::assertPublished('order.placed', fn ($message) => $message->payload['id'] === $order->id);
Outbox::assertPublishedTimes('order.placed', 1);
Outbox::assertNothingPublished();   // or assert nothing leaked
```

## Configuration

```php
return [
    'connection' => env('OUTBOX_CONNECTION'),   // same connection as your business data
    'table' => 'outbox_messages',
    'publisher' => Webrek\Outbox\Publishers\EventPublisher::class,
    'max_attempts' => 10,                        // attempts before discarding
    'batch_size' => 100,                         // messages claimed per relay pass
    'claim_timeout' => 300,                       // seconds before a stuck message is reclaimed
    'retry' => [
        'base_seconds' => 10,                     // delay = base * multiplier^(attempt - 1)
        'max_seconds' => 3600,
        'multiplier' => 2,
        'jitter' => 0.0,                          // 0–1: spread retries to avoid a thundering herd
    ],
    'prune' => [
        'retention_hours' => 168,
    ],
];
```

## Requirements

| Component | Version |
| --------- | ------- |
| PHP | 8.2+ |
| Laravel | 12.x / 13.x |
| Database | Any with transactions (PostgreSQL, MySQL/MariaDB, SQLite, SQL Server) |

## Testing

```bash
composer install
composer test
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

Please review the [security policy](SECURITY.md) before reporting a
vulnerability.

## License

Released under the [MIT license](LICENSE).
