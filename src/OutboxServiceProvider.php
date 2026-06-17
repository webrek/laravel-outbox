<?php

namespace Webrek\Outbox;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Webrek\Outbox\Console\PruneCommand;
use Webrek\Outbox\Console\RetryCommand;
use Webrek\Outbox\Console\StatusCommand;
use Webrek\Outbox\Console\WorkCommand;
use Webrek\Outbox\Contracts\Publisher;
use Webrek\Outbox\Publishers\EventPublisher;
use Webrek\Outbox\Support\Backoff;

class OutboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/outbox.php', 'outbox');

        $this->app->singleton('outbox', fn (): Outbox => new Outbox);
        $this->app->alias('outbox', Outbox::class);

        $this->app->bind(Publisher::class, fn (Application $app): Publisher => $app->make(
            $app['config']->get('outbox.publisher', EventPublisher::class),
        ));

        $this->app->bind(OutboxRelay::class, function (Application $app): OutboxRelay {
            $config = $app['config']->get('outbox');

            return new OutboxRelay(
                $app->make(Publisher::class),
                $app['events'],
                (int) ($config['max_attempts'] ?? 10),
                (int) ($config['claim_timeout'] ?? 300),
                Backoff::fromConfig($config['retry'] ?? []),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/outbox.php' => $this->app->configPath('outbox.php'),
            ], 'outbox-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/create_outbox_messages_table.php.stub' => $this->migrationPath(),
            ], 'outbox-migrations');

            $this->commands([
                WorkCommand::class,
                PruneCommand::class,
                RetryCommand::class,
                StatusCommand::class,
            ]);
        }
    }

    private function migrationPath(): string
    {
        return $this->app->databasePath('migrations/' . date('Y_m_d_His') . '_create_outbox_messages_table.php');
    }
}
