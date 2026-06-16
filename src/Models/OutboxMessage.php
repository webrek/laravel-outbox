<?php

namespace Webrek\Outbox\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Webrek\Outbox\MessageStatus;

/**
 * @property string $id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $headers
 * @property MessageStatus $status
 * @property int $attempts
 * @property Carbon $available_at
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $failed_at
 * @property string|null $last_error
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OutboxMessage extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'status' => MessageStatus::class,
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('outbox.table', 'outbox_messages');
    }

    public function getConnectionName(): ?string
    {
        return $this->connection ?? config('outbox.connection');
    }

    /**
     * Messages eligible for delivery: still within budget, due, and either
     * pending or claimed by a worker that has since gone away.
     *
     * @param  Builder<OutboxMessage>  $query
     * @return Builder<OutboxMessage>
     */
    public function scopeReady(Builder $query, int $maxAttempts, int $claimTimeout): Builder
    {
        $now = Carbon::now();

        return $query
            ->where('attempts', '<', $maxAttempts)
            ->where('available_at', '<=', $now)
            ->where(function (Builder $query) use ($now, $claimTimeout): void {
                $query
                    ->where('status', MessageStatus::Pending)
                    ->orWhere(function (Builder $query) use ($now, $claimTimeout): void {
                        $query
                            ->where('status', MessageStatus::Processing)
                            ->where('updated_at', '<=', $now->copy()->subSeconds($claimTimeout));
                    });
            });
    }
}
