<?php

namespace Webrek\Outbox\Support;

/**
 * Exponential backoff with a ceiling: delay = base * multiplier^(attempt - 1),
 * capped at max. Attempt numbers are 1-based (the first retry is attempt 1).
 *
 * An optional jitter factor (0–1) spreads retries out by adding up to that
 * fraction of the delay at random, so a burst of messages that failed together
 * does not stampede the downstream when they all become due at the same instant.
 */
class Backoff
{
    public function __construct(
        private readonly int $base,
        private readonly int $max,
        private readonly float $multiplier = 2.0,
        private readonly float $jitter = 0.0,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            (int) ($config['base_seconds'] ?? 10),
            (int) ($config['max_seconds'] ?? 3600),
            (float) ($config['multiplier'] ?? 2.0),
            (float) ($config['jitter'] ?? 0.0),
        );
    }

    public function secondsFor(int $attempt): int
    {
        $delay = $this->baseDelay($attempt);

        if ($this->jitter <= 0.0) {
            return $delay;
        }

        $spread = (int) ($delay * $this->jitter);

        return (int) min($delay + random_int(0, max(0, $spread)), $this->max);
    }

    private function baseDelay(int $attempt): int
    {
        $attempt = max(1, $attempt);

        $delay = $this->base * ($this->multiplier ** ($attempt - 1));

        return (int) min($delay, $this->max);
    }
}
