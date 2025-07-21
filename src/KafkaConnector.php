<?php

namespace Asifs\KafkaQueueLaravel;

use Asifs\KafkaQueueLaravel\Exceptions\KafkaException;
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
            $conf = $this->buildConfiguration($config);
            $producer = new Producer($conf);
            
            // Add consumer-specific configurations
            $consumerConf = clone $conf;
            $consumerConf->set('group.id', $config['group_id']);
            $consumerConf->set('auto.offset.reset', $config['auto_offset_reset'] ?? 'earliest');
            $consumerConf->set('enable.auto.commit', $config['enable_auto_commit'] ?? 'true');
            
            // Set session timeout and heartbeat interval for better consumer management
            $consumerConf->set('session.timeout.ms', $config['session_timeout_ms'] ?? 30000);
            $consumerConf->set('heartbeat.interval.ms', $config['heartbeat_interval_ms'] ?? 3000);
            
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

        // Basic configuration
        $conf->set('bootstrap.servers', $config['bootstrap_servers']);
        $conf->set('client.id', $config['client_id'] ?? 'laravel-kafka-client');
        
        // Message delivery configuration
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

        // Security configuration
        $securityProtocol = $config['security_protocol'] ?? 'PLAINTEXT';
        $conf->set('security.protocol', $securityProtocol);
        
        if ($securityProtocol !== 'PLAINTEXT') {
            $conf->set('sasl.mechanisms', $config['sasl_mechanisms'] ?? 'PLAIN');
            $conf->set('sasl.username', $config['sasl_username']);
            $conf->set('sasl.password', $config['sasl_password']);
            
            // SSL configuration if needed
            if (str_contains($securityProtocol, 'SSL')) {
                if (isset($config['ssl_ca_location'])) {
                    $conf->set('ssl.ca.location', $config['ssl_ca_location']);
                }
                if (isset($config['ssl_certificate_location'])) {
                    $conf->set('ssl.certificate.location', $config['ssl_certificate_location']);
                }
                if (isset($config['ssl_key_location'])) {
                    $conf->set('ssl.key.location', $config['ssl_key_location']);
                }
            }
        }

        return $conf;
    }
}
