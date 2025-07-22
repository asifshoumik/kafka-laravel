<?php

namespace Asifshoumik\KafkaLaravel;

use Asifshoumik\KafkaLaravel\Console\Commands\KafkaConsumeCommand;
use Asifshoumik\KafkaLaravel\Console\Commands\KafkaWorkCommand;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

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

        $this->registerKafkaQueueConnection();
        $this->registerQueueConnector();
        $this->registerCommands();
    }

    /**
     * Register the Kafka queue connection automatically.
     */
    protected function registerKafkaQueueConnection(): void
    {
        // If already configured in queue.php, respect that configuration
        if (Config::get('queue.connections.kafka')) {
            return;
        }
        
        // Auto-register from kafka-queue.php configuration
        $kafkaConfig = Config::get('kafka-queue.connections.kafka');
        if ($kafkaConfig) {
            // Merge the main connection config
            Config::set('queue.connections.kafka', $kafkaConfig);
            
            // Also register the complete kafka-queue configuration
            // This ensures topic_mapping, consumer, and monitoring configs are available
            $completeKafkaConfig = Config::get('kafka-queue');
            
            // Merge topic mapping into the connection config for easy access
            if (isset($completeKafkaConfig['topic_mapping'])) {
                Config::set('queue.connections.kafka.topic_mapping', $completeKafkaConfig['topic_mapping']);
            }
            
            // Merge consumer config into the connection config for easy access  
            if (isset($completeKafkaConfig['consumer'])) {
                Config::set('queue.connections.kafka.consumer', $completeKafkaConfig['consumer']);
            }
            
            // Merge monitoring config into the connection config for easy access
            if (isset($completeKafkaConfig['monitoring'])) {
                Config::set('queue.connections.kafka.monitoring', $completeKafkaConfig['monitoring']);
            }
        }
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
            ]);
        }
    }
}
