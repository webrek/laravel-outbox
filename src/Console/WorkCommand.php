<?php

namespace Webrek\Outbox\Console;

use Illuminate\Console\Command;
use Webrek\Outbox\OutboxRelay;

class WorkCommand extends Command
{
    protected $signature = 'outbox:work
        {--once : Process a single batch and exit}
        {--limit= : Messages to claim per batch (defaults to outbox.batch_size)}
        {--sleep=1 : Seconds to wait when the outbox is empty}';

    protected $description = 'Relay staged outbox messages to their destination';

    public function handle(OutboxRelay $relay): int
    {
        $limit = (int) ($this->option('limit') ?: config('outbox.batch_size', 100));
        $sleep = (int) $this->option('sleep');

        do {
            $processed = $relay->drain($limit);

            if ($this->option('once')) {
                $this->info("Relayed {$processed} message(s).");

                return self::SUCCESS;
            }

            if ($processed === 0) {
                sleep(max(1, $sleep));
            }
        } while (true);
    }
}
