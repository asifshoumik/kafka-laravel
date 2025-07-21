<?php

namespace Asifshoumik\KafkaLaravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class KafkaSetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'kafka:setup 
                            {--force : Overwrite existing configuration}';

    /**
     * The console command description.
     */
    protected $description = 'Set up Kafka queue configuration automatically';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Setting up Kafka queue configuration...');

        // Check if kafka-queue.php exists
        $kafkaConfigPath = config_path('kafka-queue.php');
        if (!file_exists($kafkaConfigPath)) {
            $this->warn('kafka-queue.php not found. Publishing configuration...');
            $this->call('vendor:publish', ['--tag' => 'kafka-queue-config']);
        }

        // Check queue.php configuration
        $queueConfigPath = config_path('queue.php');
        if (!file_exists($queueConfigPath)) {
            $this->error('queue.php configuration file not found!');
            return self::FAILURE;
        }

        $queueConfig = file_get_contents($queueConfigPath);
        
        // Check if Kafka connection already exists
        if (Str::contains($queueConfig, "'kafka' =>") && !$this->option('force')) {
            $this->info('Kafka connection already exists in queue.php');
            $this->info('Use --force to overwrite existing configuration');
            return self::SUCCESS;
        }

        // Add Kafka connection to queue.php
        $this->addKafkaConnectionToQueue($queueConfigPath, $queueConfig);

        $this->info('✅ Kafka configuration setup complete!');
        $this->info('');
        $this->info('Next steps:');
        $this->info('1. Update your .env file with Kafka settings');
        $this->info('2. Set QUEUE_CONNECTION=kafka in your .env');
        $this->info('3. Test with: php artisan kafka:consume');

        return self::SUCCESS;
    }

    /**
     * Add Kafka connection to queue configuration.
     */
    protected function addKafkaConnectionToQueue(string $path, string $content): void
    {
        $kafkaConnection = $this->getKafkaConnectionConfig();

        // Find the connections array and add Kafka connection
        $pattern = "/('connections'\s*=>\s*\[[\s\S]*?)(\n\s*\],)/";
        
        if (preg_match($pattern, $content)) {
            $replacement = "$1\n\n        // Kafka Queue Connection\n        'kafka' => [\n$kafkaConnection\n        ],$2";
            $newContent = preg_replace($pattern, $replacement, $content);
            
            if ($newContent !== $content) {
                file_put_contents($path, $newContent);
                $this->info('✅ Added Kafka connection to config/queue.php');
            } else {
                $this->warn('Could not automatically add Kafka connection. Please add it manually.');
                $this->displayManualInstructions();
            }
        } else {
            $this->warn('Could not find connections array in queue.php. Please add Kafka connection manually.');
            $this->displayManualInstructions();
        }
    }

    /**
     * Get the Kafka connection configuration as a string.
     */
    protected function getKafkaConnectionConfig(): string
    {
        return "            'driver' => 'kafka',
            'bootstrap_servers' => env('KAFKA_BOOTSTRAP_SERVERS', 'localhost:9092'),
            'group_id' => env('KAFKA_GROUP_ID', 'laravel-consumer-group'),
            'default_topic' => env('KAFKA_DEFAULT_TOPIC', 'laravel-jobs'),
            'dead_letter_queue' => env('KAFKA_DEAD_LETTER_QUEUE', 'laravel-failed-jobs'),
            'security_protocol' => env('KAFKA_SECURITY_PROTOCOL', 'PLAINTEXT'),
            'sasl_mechanisms' => env('KAFKA_SASL_MECHANISMS', 'PLAIN'),
            'sasl_username' => env('KAFKA_SASL_USERNAME'),
            'sasl_password' => env('KAFKA_SASL_PASSWORD'),
            'ssl_ca_location' => env('KAFKA_SSL_CA_LOCATION'),
            'ssl_certificate_location' => env('KAFKA_SSL_CERTIFICATE_LOCATION'),
            'ssl_key_location' => env('KAFKA_SSL_KEY_LOCATION'),
            'ssl_ca_pem' => env('KAFKA_SSL_CA_PEM'),
            'ssl_certificate_pem' => env('KAFKA_SSL_CERTIFICATE_PEM'),
            'ssl_key_pem' => env('KAFKA_SSL_KEY_PEM'),
            'ssl_verify_hostname' => env('KAFKA_SSL_VERIFY_HOSTNAME', true),
            'ssl_check_hostname' => env('KAFKA_SSL_CHECK_HOSTNAME', true),
            'client_id' => env('KAFKA_CLIENT_ID', 'laravel-kafka-client'),
            'message_timeout_ms' => env('KAFKA_MESSAGE_TIMEOUT_MS', 300000),
            'request_timeout_ms' => env('KAFKA_REQUEST_TIMEOUT_MS', 30000),
            'batch_size' => env('KAFKA_BATCH_SIZE', 16384),
            'compression_type' => env('KAFKA_COMPRESSION_TYPE', 'none'),
            'auto_offset_reset' => env('KAFKA_AUTO_OFFSET_RESET', 'earliest'),
            'max_attempts' => env('KAFKA_MAX_ATTEMPTS', 3)";
    }

    /**
     * Display manual configuration instructions.
     */
    protected function displayManualInstructions(): void
    {
        $this->info('');
        $this->info('Manual setup required:');
        $this->info('Add this to your config/queue.php connections array:');
        $this->info('');
        $this->line("'kafka' => [");
        $this->line($this->getKafkaConnectionConfig());
        $this->line('],');
    }
}
