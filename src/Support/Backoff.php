<?php

namespace Webrek\Outbox\Support;

/**
 * Exponential backoff with a ceiling: delay = base * multiplier^(attempt - 1),
 * capped at max. Attempt numbers are 1-based (the first retry is attempt 1).
 */
class Backoff
{
    public function __construct(
        private readonly int $base,
        private readonly int $max,
        private readonly float $multiplier = 2.0,
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
        );
    }

    public function secondsFor(int $attempt): int
    {
        $attempt = max(1, $attempt);

        $delay = $this->base * ($this->multiplier ** ($attempt - 1));

        return (int) min($delay, $this->max);
    }
}
