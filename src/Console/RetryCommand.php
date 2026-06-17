<?php

namespace Webrek\Outbox\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Webrek\Outbox\MessageStatus;
use Webrek\Outbox\Models\OutboxMessage;

class RetryCommand extends Command
{
    protected $signature = 'outbox:retry
        {id?* : One or more message IDs to retry}
        {--all : Retry every discarded message}';

    protected $description = 'Reset discarded outbox messages so the relay tries them again';

    public function handle(): int
    {
        /** @var list<string> $ids */
        $ids = $this->argument('id');

        if (! $this->option('all') && $ids === []) {
            $this->error('Specify one or more message IDs, or pass --all.');

            return self::INVALID;
        }

        $query = OutboxMessage::query()->where('status', MessageStatus::Failed);

        if (! $this->option('all')) {
            $query->whereIn('id', $ids);
        }

        $count = $query->update([
            'status' => MessageStatus::Pending,
            'attempts' => 0,
            'available_at' => Carbon::now(),
            'failed_at' => null,
            'last_error' => null,
        ]);

        $this->info("Reset {$count} message(s) for retry.");

        return self::SUCCESS;
    }
}
