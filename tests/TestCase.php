<?php

namespace Webrek\Outbox\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Webrek\Outbox\OutboxServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type')->index();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at']);
        });

        // A stand-in for application business data used by the atomicity tests.
        Schema::create('widgets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
    }

    protected function getPackageProviders($app): array
    {
        return [
            OutboxServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
