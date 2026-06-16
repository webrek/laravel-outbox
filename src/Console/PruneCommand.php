<?php

namespace Webrek\Outbox\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Webrek\Outbox\MessageStatus;
use Webrek\Outbox\Models\OutboxMessage;

class PruneCommand extends Command
{
    protected $signature = 'outbox:prune {--hours= : Delete published messages older than this many hours}';

    protected $description = 'Delete published outbox messages past their retention window';

    public function handle(): int
    {
        $hours = (int) ($this->option('hours') ?: config('outbox.prune.retention_hours', 168));

        $deleted = OutboxMessage::query()
            ->where('status', MessageStatus::Published)
            ->where('dispatched_at', '<=', Carbon::now()->subHours($hours))
            ->delete();

        $this->info("Pruned {$deleted} published outbox message(s).");

        return self::SUCCESS;
    }
}
