<?php

namespace Webrek\Outbox;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;
use Webrek\Outbox\Contracts\Publisher;
use Webrek\Outbox\Events\OutboxMessageDiscarded;
use Webrek\Outbox\Events\OutboxMessageFailed;
use Webrek\Outbox\Events\OutboxMessagePublished;
use Webrek\Outbox\Models\OutboxMessage;
use Webrek\Outbox\Support\Backoff;

class OutboxRelay
{
    public function __construct(
        private readonly Publisher $publisher,
        private readonly Dispatcher $events,
        private readonly int $maxAttempts,
        private readonly int $claimTimeout,
        private readonly Backoff $backoff,
    ) {}

    /**
     * Claim and deliver a batch of ready messages. Returns the number processed.
     */
    public function drain(int $limit): int
    {
        $messages = $this->claim($limit);

        foreach ($messages as $message) {
            $this->process($message);
        }

        return $messages->count();
    }

    /**
     * Atomically reserve up to $limit ready messages so concurrent relay
     * workers never pick up the same row.
     *
     * @return Collection<int, OutboxMessage>
     */
    private function claim(int $limit): Collection
    {
        $connection = (new OutboxMessage)->getConnection();

        return $connection->transaction(function () use ($limit): Collection {
            /** @var Collection<int, OutboxMessage> $ready */
            $ready = OutboxMessage::query()
                ->ready($this->maxAttempts, $this->claimTimeout)
                ->orderBy('available_at')
                ->orderBy('created_at')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            foreach ($ready as $message) {
                $message->forceFill([
                    'status' => MessageStatus::Processing,
                    'updated_at' => Carbon::now(),
                ])->save();
            }

            return $ready;
        });
    }

    private function process(OutboxMessage $message): void
    {
        try {
            $this->publisher->publish($message);

            $message->forceFill([
                'status' => MessageStatus::Published,
                'dispatched_at' => Carbon::now(),
                'last_error' => null,
            ])->save();

            $this->events->dispatch(new OutboxMessagePublished($message));
        } catch (Throwable $exception) {
            $this->reschedule($message, $exception);
        }
    }

    private function reschedule(OutboxMessage $message, Throwable $exception): void
    {
        $attempts = $message->attempts + 1;

        if ($attempts >= $this->maxAttempts) {
            $message->forceFill([
                'status' => MessageStatus::Failed,
                'attempts' => $attempts,
                'failed_at' => Carbon::now(),
                'last_error' => $this->errorMessage($exception),
            ])->save();

            $this->events->dispatch(new OutboxMessageDiscarded($message, $exception));

            return;
        }

        $message->forceFill([
            'status' => MessageStatus::Pending,
            'attempts' => $attempts,
            'available_at' => Carbon::now()->addSeconds($this->backoff->secondsFor($attempts)),
            'last_error' => $this->errorMessage($exception),
        ])->save();

        $this->events->dispatch(new OutboxMessageFailed($message, $exception));
    }

    private function errorMessage(Throwable $exception): string
    {
        return Str::limit($exception->getMessage(), 1000, '');
    }
}
