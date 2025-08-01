<?php

namespace Asifshoumik\KafkaLaravel;

use Asifshoumik\KafkaLaravel\Exceptions\KafkaException;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;

class KafkaConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param array $config
     * @return KafkaQueue
     * @throws KafkaException
     */
    public function connect(array $config): KafkaQueue
    {
        $this->validateConfiguration($config);

        try {
            // Create producer configuration with producer-specific settings
            $producerConf = $this->buildProducerConfiguration($config);
            $producer = new Producer($producerConf);
            
            // Create consumer configuration with consumer-specific settings
            $consumerConf = $this->buildConsumerConfiguration($config);
            $consumer = new KafkaConsumer($consumerConf);

            Log::info('Kafka connection established', [
                'bootstrap_servers' => $config['bootstrap_servers'],
                'group_id' => $config['group_id'],
                'security_protocol' => $config['security_protocol'] ?? 'PLAINTEXT'
            ]);

            return new KafkaQueue($producer, $consumer, $config);
            
        } catch (\Exception $e) {
            Log::error('Failed to establish Kafka connection', [
                'error' => $e->getMessage(),
                'config' => Arr::except($config, ['sasl_password'])
            ]);
            
            throw KafkaException::connectionFailed($e->getMessage());
        }
    }

    /**
     * Validate the Kafka configuration.
     *
     * @param array $config
     * @throws KafkaException
     */
    private function validateConfiguration(array $config): void
    {
        $required = ['bootstrap_servers', 'group_id'];
        
        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw KafkaException::configurationInvalid("Missing required configuration: {$key}");
            }
        }

        if (isset($config['security_protocol']) && 
            $config['security_protocol'] !== 'PLAINTEXT' && 
            (empty($config['sasl_username']) || empty($config['sasl_password']))) {
            throw KafkaException::configurationInvalid('SASL credentials required for non-PLAINTEXT security protocol');
        }
    }

    /**
     * Build the RdKafka configuration.
     *
     * @param array $config
     * @return Conf
     */
    private function buildConfiguration(array $config): Conf
    {
        $conf = new Conf();

        // Basic configuration (common for both producer and consumer)
        $conf->set('bootstrap.servers', $config['bootstrap_servers']);
        $conf->set('client.id', $config['client_id'] ?? 'laravel-kafka-client');
        
        // Security configuration
        $securityProtocol = $config['security_protocol'] ?? 'PLAINTEXT';
        $conf->set('security.protocol', $securityProtocol);
        
        if ($securityProtocol !== 'PLAINTEXT') {
            $conf->set('sasl.mechanisms', $config['sasl_mechanisms'] ?? 'PLAIN');
            $conf->set('sasl.username', $config['sasl_username']);
            $conf->set('sasl.password', $config['sasl_password']);
            
            // SSL configuration if needed
            if (str_contains($securityProtocol, 'SSL')) {
                $this->configureSslCertificates($conf, $config);
            }
        }

        return $conf;
    }

    /**
     * Build producer-specific configuration.
     *
     * @param array $config
     * @return Conf
     */
    private function buildProducerConfiguration(array $config): Conf
    {
        $conf = $this->buildConfiguration($config);
        
        // Producer-specific configuration
        $conf->set('message.timeout.ms', $config['message_timeout_ms'] ?? 300000);
        $conf->set('request.timeout.ms', $config['request_timeout_ms'] ?? 30000);
        $conf->set('delivery.timeout.ms', $config['delivery_timeout_ms'] ?? 300000);
        
        // Retry configuration
        $conf->set('retries', $config['retries'] ?? 2147483647);
        $conf->set('retry.backoff.ms', $config['retry_backoff_ms'] ?? 100);
        
        // Batch configuration for better performance
        $conf->set('batch.size', $config['batch_size'] ?? 16384);
        $conf->set('linger.ms', $config['linger_ms'] ?? 5);
        
        // Compression
        if (isset($config['compression_type'])) {
            $conf->set('compression.type', $config['compression_type']);
        }

        // Producer reliability settings
        $conf->set('acks', $config['acks'] ?? 'all'); // Wait for all replicas
        $conf->set('enable.idempotence', $config['enable_idempotence'] ?? 'true');
        $conf->set('max.in.flight.requests.per.connection', $config['max_in_flight'] ?? 5);
        
        // Set delivery report callback for better error handling
        $conf->setDrMsgCb(function ($kafka, $message) {
            if ($message->err) {
                Log::error('Kafka message delivery failed', [
                    'topic' => $message->topic_name,
                    'partition' => $message->partition,
                    'error' => $message->errstr(),
                    'error_code' => $message->err
                ]);
            } else {
                Log::debug('Kafka message delivered successfully', [
                    'topic' => $message->topic_name,
                    'partition' => $message->partition,
                    'offset' => $message->offset
                ]);
            }
        });

        return $conf;
    }

    /**
     * Build consumer-specific configuration.
     *
     * @param array $config
     * @return Conf
     */
    private function buildConsumerConfiguration(array $config): Conf
    {
        $conf = $this->buildConfiguration($config);
        
        // Consumer-specific configuration
        $conf->set('group.id', $config['group_id']);
        $conf->set('auto.offset.reset', $config['auto_offset_reset'] ?? 'earliest');
        $conf->set('enable.auto.commit', $config['enable_auto_commit'] ?? 'true');
        
        // Session management for better consumer reliability
        $conf->set('session.timeout.ms', $config['session_timeout_ms'] ?? 30000);
        $conf->set('heartbeat.interval.ms', $config['heartbeat_interval_ms'] ?? 3000);
        
        // Consumer-specific timeouts
        $conf->set('fetch.wait.max.ms', $config['fetch_wait_max_ms'] ?? 500);
        $conf->set('fetch.min.bytes', $config['fetch_min_bytes'] ?? 1);

        return $conf;
    }

    /**
     * Configure SSL certificates from either file paths or PEM strings.
     */
    protected function configureSslCertificates(Conf $conf, array $config): void
    {
        // CA Certificate configuration
        if (isset($config['ssl_ca_pem']) && !empty($config['ssl_ca_pem'])) {
            // Use PEM string - create temporary file
            $caFile = $this->createTempCertFile($config['ssl_ca_pem'], 'ca');
            $conf->set('ssl.ca.location', $caFile);
        } elseif (isset($config['ssl_ca_location']) && !empty($config['ssl_ca_location'])) {
            // Use file path
            $conf->set('ssl.ca.location', $config['ssl_ca_location']);
        }

        // Client Certificate configuration
        if (isset($config['ssl_certificate_pem']) && !empty($config['ssl_certificate_pem'])) {
            // Use PEM string - create temporary file
            $certFile = $this->createTempCertFile($config['ssl_certificate_pem'], 'cert');
            $conf->set('ssl.certificate.location', $certFile);
        } elseif (isset($config['ssl_certificate_location']) && !empty($config['ssl_certificate_location'])) {
            // Use file path
            $conf->set('ssl.certificate.location', $config['ssl_certificate_location']);
        }

        // Client Private Key configuration
        if (isset($config['ssl_key_pem']) && !empty($config['ssl_key_pem'])) {
            // Use PEM string - create temporary file
            $keyFile = $this->createTempCertFile($config['ssl_key_pem'], 'key');
            $conf->set('ssl.key.location', $keyFile);
        } elseif (isset($config['ssl_key_location']) && !empty($config['ssl_key_location'])) {
            // Use file path
            $conf->set('ssl.key.location', $config['ssl_key_location']);
        }

        // SSL verification options
        if (isset($config['ssl_verify_hostname'])) {
            $conf->set('enable.ssl.certificate.verification', $config['ssl_verify_hostname'] ? 'true' : 'false');
        }

        if (isset($config['ssl_check_hostname'])) {
            $conf->set('ssl.endpoint.identification.algorithm', $config['ssl_check_hostname'] ? 'https' : 'none');
        }
    }

    /**
     * Create a temporary file for certificate PEM content.
     */
    protected function createTempCertFile(string $pemContent, string $type): string
    {
        $tempDir = sys_get_temp_dir();
        $filename = sprintf('kafka_%s_%s.pem', $type, uniqid());
        $filepath = $tempDir . DIRECTORY_SEPARATOR . $filename;

        if (file_put_contents($filepath, $pemContent) === false) {
            throw KafkaException::configurationInvalid("Failed to create temporary certificate file for {$type}");
        }

        // Set restrictive permissions for security
        chmod($filepath, 0600);

        // Register for cleanup on shutdown
        register_shutdown_function(function() use ($filepath) {
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        });

        return $filepath;
    }
}
