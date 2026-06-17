<?php

use Webrek\Outbox\Publishers\EventPublisher;

return [

    /*
    |--------------------------------------------------------------------------
    | Database connection
    |--------------------------------------------------------------------------
    |
    | The connection the outbox table lives on. Leave null to use the default.
    | For the transactional guarantee to hold, this MUST be the same connection
    | as the business data you stage messages alongside — atomicity only spans
    | a single connection's transaction.
    |
    */

    'connection' => env('OUTBOX_CONNECTION'),

    'table' => 'outbox_messages',

    /*
    |--------------------------------------------------------------------------
    | Publisher
    |--------------------------------------------------------------------------
    |
    | The class that actually delivers a message. It must implement
    | Webrek\Outbox\Contracts\Publisher. The default turns each message into an
    | OutboxMessageReady event you can listen for; swap it for your own to push
    | to a broker, an HTTP endpoint, etc.
    |
    */

    'publisher' => EventPublisher::class,

    /*
    |--------------------------------------------------------------------------
    | Delivery budget
    |--------------------------------------------------------------------------
    |
    | max_attempts  — total delivery attempts before a message is discarded.
    | batch_size    — messages claimed per relay pass.
    | claim_timeout — seconds before a message stuck in "processing" (e.g. a
    |                 crashed worker) is reclaimed by another relay.
    |
    */

    'max_attempts' => 10,

    'batch_size' => 100,

    'claim_timeout' => 300,

    /*
    |--------------------------------------------------------------------------
    | Retry backoff
    |--------------------------------------------------------------------------
    |
    | Exponential: delay = base_seconds * multiplier^(attempt - 1), capped at
    | max_seconds. `jitter` (0–1) adds up to that fraction of the delay at
    | random so a burst of messages that failed together does not all retry at
    | the same instant. Leave it at 0 to disable.
    |
    */

    'retry' => [
        'base_seconds' => 10,
        'max_seconds' => 3600,
        'multiplier' => 2,
        'jitter' => 0.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | How long published messages are kept before `outbox:prune` removes them.
    |
    */

    'prune' => [
        'retention_hours' => 168,
    ],

];
