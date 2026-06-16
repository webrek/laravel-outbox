<?php

namespace Webrek\Outbox;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Webrek\Outbox\Models\OutboxMessage;

class Outbox
{
    /**
     * Stage a message in the outbox. Call this inside the same database
     * transaction as your business write so the two commit atomically — either
     * both land or neither does.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function publish(string $type, array $payload = [], array $headers = []): OutboxMessage
    {
        return OutboxMessage::create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'payload' => $payload,
            'headers' => $headers === [] ? null : $headers,
            'status' => MessageStatus::Pending,
            'attempts' => 0,
            'available_at' => Carbon::now(),
        ]);
    }
}
