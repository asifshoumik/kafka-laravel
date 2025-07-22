# Configuration Architecture

## Overview

The Kafka Laravel package now uses an auto-registration system that automatically merges all configurations from `config/kafka-queue.php` into Laravel's queue system, making them available to all package components.

## Configuration Flow

```
config/kafka-queue.php
    ↓ (mergeConfigFrom)
Laravel Config System
    ↓ (auto-registration)
queue.connections.kafka.*
    ↓ (Laravel Queue Manager)
KafkaConnector::connect($config)
    ↓ (full config passed)
KafkaQueue($producer, $consumer, $config)
```

## Available Configurations

### 1. Main Connection Configuration
**Location:** `config/kafka-queue.php` → `connections.kafka`
**Auto-registered to:** `queue.connections.kafka`

Includes all broker settings, security, SSL, timeouts, batch settings, etc.

### 2. Topic Mapping
**Location:** `config/kafka-queue.php` → `topic_mapping`
**Auto-registered to:** `queue.connections.kafka.topic_mapping`

Maps queue names to Kafka topics:
```php
'topic_mapping' => [
    'default' => 'laravel-jobs',
    'emails' => 'laravel-email-jobs',
    'notifications' => 'laravel-notification-jobs',
    'processing' => 'laravel-processing-jobs',
]
```

### 3. Consumer Configuration  
**Location:** `config/kafka-queue.php` → `consumer`
**Auto-registered to:** `queue.connections.kafka.consumer`

Console command worker settings:
```php
'consumer' => [
    'memory_limit' => 128,
    'timeout' => 60,
    'sleep' => 3,
    'max_jobs' => 0,
    'max_time' => 0,
    'force' => false,
    'stop_when_empty' => false,
]
```

### 4. Monitoring Configuration
**Location:** `config/kafka-queue.php` → `monitoring`  
**Auto-registered to:** `queue.connections.kafka.monitoring`

Logging and monitoring settings:
```php
'monitoring' => [
    'enabled' => true,
    'log_level' => 'info',
    'metrics_enabled' => false,
]
```

## How Components Access Configuration

### KafkaQueue Class
Receives the complete merged configuration in its constructor:
```php
public function __construct(Producer $producer, KafkaConsumer $consumer, array $config = [])
{
    $this->config = $config; // Contains ALL configurations
}
```

Access patterns:
```php
// Main connection settings
$bootstrapServers = $this->config['bootstrap_servers'];
$maxAttempts = $this->config['max_attempts'];

// Topic mapping
$emailTopic = $this->config['topic_mapping']['emails'] ?? $this->config['default_topic'];

// Consumer settings  
$memoryLimit = $this->config['consumer']['memory_limit'];

// Monitoring settings
$monitoringEnabled = $this->config['monitoring']['enabled'];
```

### Console Commands
Access via Queue Manager:
```php
/** @var KafkaQueue $queue */
$queue = app('queue')->connection('kafka');
$config = $queue->getConfig();

// Access any configuration
$consumerConfig = $config['consumer'];
$topicMapping = $config['topic_mapping'];
```

### Jobs
Access via queue connection or direct config:
```php
// Via queue
$queue = app('queue')->connection('kafka');
$config = $queue->getConfig();

// Via config (when needed)
$topicMapping = config('queue.connections.kafka.topic_mapping');
```

## Benefits

1. **Single Source of Truth**: All configuration in `config/kafka-queue.php`
2. **Automatic Registration**: No manual queue.php configuration required
3. **Complete Access**: All components can access all configurations
4. **Backward Compatibility**: Existing configurations continue to work
5. **Environment Variables**: All settings can be overridden via .env
6. **Laravel Standards**: Follows Laravel package configuration patterns

## Migration from Manual Setup

### Before (Manual Setup)
```php
// config/queue.php - Manual configuration required
'connections' => [
    'kafka' => [
        'driver' => 'kafka',
        'bootstrap_servers' => env('KAFKA_BOOTSTRAP_SERVERS', 'localhost:9092'),
        // ... manual configuration
    ],
],
```

### After (Auto-Registration)
```php
// config/queue.php - No manual configuration needed!
// Everything is auto-registered from config/kafka-queue.php
```

The auto-registration system automatically handles:
- ✅ Main connection configuration
- ✅ Topic mapping for queue routing
- ✅ Consumer settings for worker processes  
- ✅ Monitoring and logging configuration
- ✅ SSL and security settings
- ✅ All advanced options
