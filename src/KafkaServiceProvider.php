<?php

namespace Asifshoumik\KafkaLaravel;

use Asifshoumik\KafkaLaravel\Console\Commands\KafkaConsumeCommand;
use Asifshoumik\KafkaLaravel\Console\Commands\KafkaWorkCommand;
use Asifshoumik\KafkaLaravel\Console\Commands\KafkaSetupCommand;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;

class KafkaServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/kafka-queue.php',
            'kafka-queue'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/kafka-queue.php' => config_path('kafka-queue.php'),
        ], 'kafka-queue-config');

        $this->registerQueueConnector();
        $this->registerCommands();
    }

    /**
     * Register the Kafka queue connector.
     */
    protected function registerQueueConnector(): void
    {
        /** @var QueueManager $manager */
        $manager = $this->app['queue'];

        $manager->addConnector('kafka', function () {
            return new KafkaConnector();
        });
    }

    /**
     * Register the package commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                KafkaConsumeCommand::class,
                KafkaWorkCommand::class,
                KafkaSetupCommand::class,
            ]);
        }
    }
}
