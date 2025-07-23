<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kafka Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Kafka connections for Laravel queues.
    | The settings here will be used by the Kafka queue driver.
    |
    */

    'connections' => [
        'kafka' => [
            'driver' => 'kafka',
            
            // Kafka brokers
            'bootstrap_servers' => env('KAFKA_BOOTSTRAP_SERVERS', 'localhost:9092'),
            
            // Consumer group
            'group_id' => env('KAFKA_GROUP_ID', 'laravel-consumer-group'),
            
            // Default topic for queues
            'default_topic' => env('KAFKA_DEFAULT_TOPIC', 'laravel-jobs'),
            
            // Dead letter queue topic
            'dead_letter_queue' => env('KAFKA_DEAD_LETTER_QUEUE', 'laravel-failed-jobs'),
            
            // Security settings
            'security_protocol' => env('KAFKA_SECURITY_PROTOCOL', 'PLAINTEXT'),
            'sasl_mechanisms' => env('KAFKA_SASL_MECHANISMS', 'PLAIN'),
            'sasl_username' => env('KAFKA_SASL_USERNAME'),
            'sasl_password' => env('KAFKA_SASL_PASSWORD'),
            
            // SSL settings (if using SSL)
            // Option 1: File paths to certificates
            'ssl_ca_location' => env('KAFKA_SSL_CA_LOCATION'),
            'ssl_certificate_location' => env('KAFKA_SSL_CERTIFICATE_LOCATION'),
            'ssl_key_location' => env('KAFKA_SSL_KEY_LOCATION'),
            
            // Option 2: Certificate content as strings (useful for Kubernetes secrets)
            'ssl_ca_pem' => env('KAFKA_SSL_CA_PEM'),
            'ssl_certificate_pem' => env('KAFKA_SSL_CERTIFICATE_PEM'),
            'ssl_key_pem' => env('KAFKA_SSL_KEY_PEM'),
            
            // SSL verification options
            'ssl_verify_hostname' => env('KAFKA_SSL_VERIFY_HOSTNAME', true),
            'ssl_check_hostname' => env('KAFKA_SSL_CHECK_HOSTNAME', true),
            
            // Client settings
            'client_id' => env('KAFKA_CLIENT_ID', 'laravel-kafka-client'),
            
            // Timeout settings (in milliseconds)
            'message_timeout_ms' => env('KAFKA_MESSAGE_TIMEOUT_MS', 300000),
            'request_timeout_ms' => env('KAFKA_REQUEST_TIMEOUT_MS', 30000),
            'delivery_timeout_ms' => env('KAFKA_DELIVERY_TIMEOUT_MS', 300000),
            'session_timeout_ms' => env('KAFKA_SESSION_TIMEOUT_MS', 30000),
            'heartbeat_interval_ms' => env('KAFKA_HEARTBEAT_INTERVAL_MS', 3000),
            'consume_timeout_ms' => env('KAFKA_CONSUME_TIMEOUT_MS', 3000),
            'flush_timeout_ms' => env('KAFKA_FLUSH_TIMEOUT_MS', 10000),
            
            // Retry settings
            'retries' => env('KAFKA_RETRIES', 2147483647),
            'retry_backoff_ms' => env('KAFKA_RETRY_BACKOFF_MS', 100),
            
            // Batch settings
            'batch_size' => env('KAFKA_BATCH_SIZE', 16384),
            'linger_ms' => env('KAFKA_LINGER_MS', 5),
            
            // Compression
            'compression_type' => env('KAFKA_COMPRESSION_TYPE', 'none'), // none, gzip, snappy, lz4, zstd
            
            // Producer reliability settings
            'acks' => env('KAFKA_ACKS', 'all'), // 0, 1, all (or -1)
            'enable_idempotence' => env('KAFKA_ENABLE_IDEMPOTENCE', 'true'),
            'max_in_flight' => env('KAFKA_MAX_IN_FLIGHT', 5),
            
            // Consumer settings
            'auto_offset_reset' => env('KAFKA_AUTO_OFFSET_RESET', 'earliest'), // earliest, latest
            'enable_auto_commit' => env('KAFKA_ENABLE_AUTO_COMMIT', 'true'),
            'fetch_wait_max_ms' => env('KAFKA_FETCH_WAIT_MAX_MS', 500),
            'fetch_min_bytes' => env('KAFKA_FETCH_MIN_BYTES', 1),
            
            // Error handling
            'sleep_on_error' => env('KAFKA_SLEEP_ON_ERROR', 5),
            'max_attempts' => env('KAFKA_MAX_ATTEMPTS', 3),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Topic Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure specific topics for different queue names.
    | This allows you to route different job types to different Kafka topics.
    |
    */

    'topic_mapping' => [
        'default' => env('KAFKA_DEFAULT_TOPIC', 'laravel-jobs'),
        'emails' => env('KAFKA_EMAILS_TOPIC', 'laravel-email-jobs'),
        'notifications' => env('KAFKA_NOTIFICATIONS_TOPIC', 'laravel-notification-jobs'),
        'processing' => env('KAFKA_PROCESSING_TOPIC', 'laravel-processing-jobs'),
        // Add more topic mappings as needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Consumer Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Kafka consumer workers.
    |
    */

    'consumer' => [
        'memory_limit' => env('KAFKA_CONSUMER_MEMORY_LIMIT', 128),
        'timeout' => env('KAFKA_CONSUMER_TIMEOUT', 60),
        'sleep' => env('KAFKA_CONSUMER_SLEEP', 3),
        'max_jobs' => env('KAFKA_CONSUMER_MAX_JOBS', 0),
        'max_time' => env('KAFKA_CONSUMER_MAX_TIME', 0),
        'force' => env('KAFKA_CONSUMER_FORCE', false),
        'stop_when_empty' => env('KAFKA_CONSUMER_STOP_WHEN_EMPTY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring and logging Kafka operations.
    |
    */

    'monitoring' => [
        'enabled' => env('KAFKA_MONITORING_ENABLED', true),
        'log_level' => env('KAFKA_LOG_LEVEL', 'info'),
        'metrics_enabled' => env('KAFKA_METRICS_ENABLED', false),
    ],
];
