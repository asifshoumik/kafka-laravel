# Kafka Queue Driver for Laravel

A robust and feature-rich Kafka queue driver for Laravel microservices with comprehensive error handling, delayed jobs, retry mechanisms, and dead letter queue support.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/asifshoumik/kafka-laravel.svg?style=flat-square)](https://packagist.org/packages/asifshoumik/kafka-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/asifshoumik/kafka-laravel.svg?style=flat-square)](https://packagist.org/packages/asifshoumik/kafka-laravel)

## Features

- ✅ **Full Laravel Queue Integration** - Works with Laravel's built-in queue system
- ✅ **Error Handling & Retries** - Comprehensive error handling with configurable retry mechanisms
- ✅ **Dead Letter Queue** - Failed jobs are automatically moved to a dead letter queue
- ✅ **Delayed Jobs** - Support for delayed job execution
- ✅ **Multiple Security Protocols** - PLAINTEXT, SSL/TLS, SASL authentication
- ✅ **Configurable Timeouts** - Fine-tuned timeout configurations for different scenarios
- ✅ **Batch Processing** - Optimized batch processing for better performance
- ✅ **Monitoring & Logging** - Comprehensive logging and monitoring capabilities
- ✅ **Console Commands** - Artisan commands for queue management
- ✅ **Topic Mapping** - Route different job types to different Kafka topics

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- rdkafka PHP extension
- Kafka 2.1.0 or higher

## Installation

> 📋 **Important:** The rdkafka PHP extension is required. See [INSTALLATION.md](INSTALLATION.md) for detailed installation instructions.

### 1. Install rdkafka Extension First

**Important:** The rdkafka PHP extension must be installed before installing this package.

**Ubuntu/Debian:**
```bash
sudo apt-get install librdkafka-dev
sudo pecl install rdkafka
# Add extension=rdkafka.so to your php.ini file
```

**macOS (with Homebrew):**
```bash
brew install librdkafka
pecl install rdkafka
# Add extension=rdkafka.so to your php.ini file
```

**Windows:**
1. Download the appropriate DLL from the [PECL rdkafka page](https://pecl.php.net/package/rdkafka)
2. Place the DLL in your PHP extensions directory
3. Add `extension=rdkafka` to your php.ini file
4. Restart your web server

**Verify Installation:**
```bash
php -m | grep rdkafka
```

### 2. Install the Package

```bash
composer require asifshoumik/kafka-laravel
```

### 3. Publish Configuration

```bash
php artisan vendor:publish --tag=kafka-queue-config
```

### 4. Configure Environment Variables

Add the following to your `.env` file:

```env
# Queue Configuration
QUEUE_CONNECTION=kafka

# Kafka Configuration
KAFKA_BOOTSTRAP_SERVERS=localhost:9092
KAFKA_GROUP_ID=laravel-consumer-group
KAFKA_DEFAULT_TOPIC=laravel-jobs
KAFKA_DEAD_LETTER_QUEUE=laravel-failed-jobs

# Security (if needed)
KAFKA_SECURITY_PROTOCOL=PLAINTEXT
KAFKA_SASL_MECHANISMS=PLAIN
KAFKA_SASL_USERNAME=
KAFKA_SASL_PASSWORD=

# Performance Tuning
KAFKA_BATCH_SIZE=16384
KAFKA_LINGER_MS=5
KAFKA_COMPRESSION_TYPE=none
KAFKA_MAX_ATTEMPTS=3
```

### 5. Update Queue Configuration

In `config/queue.php`, add the Kafka connection:

```php
'connections' => [
    // ... other connections
    
    'kafka' => [
        'driver' => 'kafka',
        'bootstrap_servers' => env('KAFKA_BOOTSTRAP_SERVERS', 'localhost:9092'),
        'group_id' => env('KAFKA_GROUP_ID', 'laravel-consumer-group'),
        'default_topic' => env('KAFKA_DEFAULT_TOPIC', 'laravel-jobs'),
        'dead_letter_queue' => env('KAFKA_DEAD_LETTER_QUEUE', 'laravel-failed-jobs'),
        'security_protocol' => env('KAFKA_SECURITY_PROTOCOL', 'PLAINTEXT'),
        'max_attempts' => env('KAFKA_MAX_ATTEMPTS', 3),
    ],
],
```

## Usage

### Basic Job Dispatching

```php
use App\Jobs\ProcessPodcast;

// Dispatch to default topic
ProcessPodcast::dispatch($podcast);

// Dispatch to specific topic
ProcessPodcast::dispatch($podcast)->onQueue('emails');

// Dispatch with delay
ProcessPodcast::dispatch($podcast)->delay(now()->addMinutes(10));
```

### Creating Jobs

```php
<?php

namespace App\Jobs;

use Asifshoumik\KafkaLaravel\Jobs\KafkaJob;

class ProcessPodcast extends KafkaJob
{
    public $podcast;

    public function __construct($podcast)
    {
        $this->podcast = $podcast;
    }

    public function handle(): void
    {
        // Process the podcast
        $this->processPayload([
            'podcast_id' => $this->podcast->id,
            'action' => 'process'
        ]);
    }

    protected function processPayload(array $payload): void
    {
        // Your custom processing logic here
        logger()->info('Processing podcast', $payload);
    }
}
```

### Consuming Messages

#### Using Artisan Command

```bash
# Consume from specific topic
php artisan kafka:consume laravel-jobs

# With options
php artisan kafka:consume laravel-jobs --timeout=60 --memory=256 --sleep=3

# Stop when queue is empty
php artisan kafka:consume laravel-jobs --stopWhenEmpty

# Force run in maintenance mode
php artisan kafka:consume laravel-jobs --force
```

#### Using Queue Worker

```bash
# Standard Laravel queue worker
php artisan queue:work kafka --queue=laravel-jobs

# With specific options
php artisan queue:work kafka --queue=laravel-jobs --timeout=60 --memory=256
```

### Topic Mapping

Configure topic mapping in `config/kafka-queue.php`:

```php
'topic_mapping' => [
    'default' => 'laravel-jobs',
    'emails' => 'laravel-email-jobs',
    'notifications' => 'laravel-notification-jobs',
    'processing' => 'laravel-processing-jobs',
],
```

Then use specific queues:

```php
// Will go to 'laravel-email-jobs' topic
SendEmailJob::dispatch($user)->onQueue('emails');

// Will go to 'laravel-notification-jobs' topic
SendNotificationJob::dispatch($notification)->onQueue('notifications');
```

### Error Handling and Retries

Jobs are automatically retried based on configuration. Failed jobs exceeding max attempts are moved to the dead letter queue.

```php
class RiskyJob extends KafkaJob
{
    public $tries = 5; // Override default max attempts
    public $timeout = 120; // Job timeout in seconds

    public function handle(): void
    {
        // Risky operation that might fail
        if ($this->shouldFail()) {
            throw new \Exception('Job failed');
        }
    }

    public function failed(\Exception $exception): void
    {
        // Handle job failure
        Log::error('RiskyJob failed', [
            'exception' => $exception->getMessage(),
            'payload' => $this->getPayload()
        ]);
    }
}
```

### Advanced Configuration

#### SSL/TLS Configuration

```env
KAFKA_SECURITY_PROTOCOL=SSL
KAFKA_SSL_CA_LOCATION=/path/to/ca-cert
KAFKA_SSL_CERTIFICATE_LOCATION=/path/to/client-cert
KAFKA_SSL_KEY_LOCATION=/path/to/client-key
```

#### SASL Authentication

```env
KAFKA_SECURITY_PROTOCOL=SASL_SSL
KAFKA_SASL_MECHANISMS=SCRAM-SHA-256
KAFKA_SASL_USERNAME=your-username
KAFKA_SASL_PASSWORD=your-password
```

#### Performance Optimization

```env
# Increase batch size for better throughput
KAFKA_BATCH_SIZE=65536

# Enable compression
KAFKA_COMPRESSION_TYPE=snappy

# Tune timeouts
KAFKA_MESSAGE_TIMEOUT_MS=300000
KAFKA_REQUEST_TIMEOUT_MS=30000
```

### Monitoring and Logging

The package provides comprehensive logging. Monitor your application logs for:

- Job processing information
- Connection status
- Error details
- Performance metrics

Example log entry:
```json
{
    "level": "info",
    "message": "Kafka job processed",
    "context": {
        "topic": "laravel-jobs",
        "job_id": "uuid-here",
        "job_name": "App\\Jobs\\ProcessPodcast",
        "attempts": 1,
        "processing_time": 1.5
    }
}
```

## Troubleshooting

### rdkafka Extension Not Found

If you get an error like this when running `composer require asifshoumik/kafka-laravel`:

```
Package asifshoumik/kafka-laravel has requirements incompatible with your PHP version, PHP extensions and Composer version:
- asifshoumik/kafka-laravel v1.0.0 requires ext-rdkafka * but it is not present.
```

This means the rdkafka PHP extension is not installed. Follow these steps:

1. **Install the extension first** (see [INSTALLATION.md](INSTALLATION.md) for detailed instructions)
2. **Verify installation**: `php -m | grep rdkafka`
3. **Check php.ini**: Ensure `extension=rdkafka` is added to your php.ini
4. **Restart web server** after installing the extension

### Installation with Missing Extensions

If you need to install the package without rdkafka (for development purposes only):

```bash
composer require asifshoumik/kafka-laravel --ignore-platform-req=ext-rdkafka
```

**Warning:** The package will not work without the rdkafka extension in production.

### Connection Issues

- Verify Kafka is running: `telnet localhost 9092`
- Check firewall settings for Kafka ports
- Verify broker addresses in configuration
- Check authentication credentials if using SASL

### Performance Issues

- Increase `batch_size` for higher throughput
- Enable compression: `KAFKA_COMPRESSION_TYPE=snappy`
- Tune consumer fetch settings
- Monitor partition lag

## Testing

```bash
composer test
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## Security

If you discover any security-related issues, please email the maintainer instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Asif Khan Pathan](https://github.com/asifshoumik)
- [All Contributors](../../contributors)

## Support

- 📖 [Documentation](https://github.com/asifshoumik/kafka-laravel/wiki)
- 🐛 [Issue Tracker](https://github.com/asifshoumik/kafka-laravel/issues)
- 💬 [Discussions](https://github.com/asifshoumik/kafka-laravel/discussions)
