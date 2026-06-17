<?php

namespace Webrek\Outbox\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Webrek\Outbox\MessageStatus;
use Webrek\Outbox\Models\OutboxMessage;

class StatusCommand extends Command
{
    protected $signature = 'outbox:status';

    protected $description = 'Summarise outbox messages by status';

    public function handle(): int
    {
        $counts = OutboxMessage::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $rows = [];
        foreach (MessageStatus::cases() as $status) {
            $rows[] = [$status->value, (int) ($counts[$status->value] ?? 0)];
        }

        $this->table(['Status', 'Count'], $rows);

        $oldest = OutboxMessage::query()
            ->where('status', MessageStatus::Pending)
            ->orderBy('available_at')
            ->value('available_at');

        $this->line('Oldest pending: ' . ($oldest !== null
            ? Carbon::parse($oldest)->diffForHumans()
            : 'none'));

        return self::SUCCESS;
    }
}
