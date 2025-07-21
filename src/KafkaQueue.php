<?php

namespace Asifs\KafkaQueueLaravel;

use Asifs\KafkaQueueLaravel\Exceptions\KafkaException;
use Asifs\KafkaQueueLaravel\Jobs\KafkaJob;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use RdKafka\Message;

class KafkaQueue extends Queue implements QueueContract
{
    protected Producer $producer;
    protected KafkaConsumer $consumer;
    protected array $config;
    protected int $sleepOnError;

    public function __construct(Producer $producer, KafkaConsumer $consumer, array $config = [])
    {
        $this->producer = $producer;
        $this->consumer = $consumer;
        $this->config = $config;
        $this->sleepOnError = $config['sleep_on_error'] ?? 5;
    }

    /**
     * Get the size of the queue.
     * Note: Kafka doesn't provide a direct way to get queue size.
     */
    public function size($queue = null): int
    {
        // Kafka doesn't provide a direct way to get the size of a topic
        // You would need to implement this using Kafka's consumer API
        // to get the lag information, which is complex and not always reliable
        return 0;
    }

    /**
     * Push a new job onto the queue.
     */
    public function push($job, $data = '', $queue = null): ?string
    {
        return $this->pushRaw($this->createPayload($job, $queue ?? $this->getDefaultQueue(), $data), $queue);
    }

    /**
     * Push a raw payload onto the queue.
     */
    public function pushRaw($payload, $queue = null, array $options = []): ?string
    {
        $topic = $queue ?? $this->getDefaultQueue();
        
        try {
            $kafkaTopic = $this->producer->newTopic($topic);
            
            $messageId = Str::uuid()->toString();
            $headers = array_merge([
                'id' => $messageId,
                'timestamp' => time(),
                'attempts' => 0,
            ], $options['headers'] ?? []);

            $kafkaTopic->producev(
                RD_KAFKA_PARTITION_UA,
                0,
                $payload,
                null,
                $headers
            );

            // Wait for message delivery confirmation
            $result = $this->producer->flush($this->config['flush_timeout_ms'] ?? 10000);
            
            if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                throw KafkaException::producerError("Failed to deliver message to topic: {$topic}");
            }

            Log::debug('Message pushed to Kafka', [
                'topic' => $topic,
                'message_id' => $messageId,
                'payload_size' => strlen($payload)
            ]);

            return $messageId;
            
        } catch (\Exception $e) {
            Log::error('Failed to push message to Kafka', [
                'topic' => $topic,
                'error' => $e->getMessage(),
                'payload_size' => strlen($payload)
            ]);
            
            throw KafkaException::producerError($e->getMessage());
        }
    }

    /**
     * Push a new job onto the queue after a delay.
     */
    public function later($delay, $job, $data = '', $queue = null): ?string
    {
        // Kafka doesn't have native delayed message support
        // We'll store the delay information in the message headers
        // and handle it in the consumer
        $delayUntil = time() + (is_numeric($delay) ? $delay : 0);
        
        return $this->pushRaw(
            $this->createPayload($job, $queue ?? $this->getDefaultQueue(), $data),
            $queue,
            ['headers' => ['delay_until' => $delayUntil]]
        );
    }

    /**
     * Pop the next job off of the queue.
     */
    public function pop($queue = null): ?KafkaJobContainer
    {
        $topic = $queue ?? $this->getDefaultQueue();
        
        try {
            if (!$this->isSubscribed($topic)) {
                $this->consumer->subscribe([$topic]);
                Log::debug("Subscribed to Kafka topic: {$topic}");
            }

            $message = $this->consumer->consume($this->config['consume_timeout_ms'] ?? 3000);

            return $this->handleMessage($message, $topic);
            
        } catch (\Exception $e) {
            Log::error('Failed to consume message from Kafka', [
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);
            
            sleep($this->sleepOnError);
            return null;
        }
    }

    /**
     * Handle a Kafka message.
     */
    protected function handleMessage(Message $message, string $topic): ?KafkaJobContainer
    {
        switch ($message->err) {
            case RD_KAFKA_RESP_ERR_NO_ERROR:
                return $this->createJobFromMessage($message, $topic);
                
            case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                Log::debug('Reached end of partition', ['topic' => $topic]);
                return null;
                
            case RD_KAFKA_RESP_ERR__TIMED_OUT:
                Log::debug('Consumer timed out', ['topic' => $topic]);
                return null;
                
            default:
                Log::error('Kafka consumer error', [
                    'topic' => $topic,
                    'error_code' => $message->err,
                    'error_message' => $message->errstr()
                ]);
                
                throw KafkaException::consumerError($message->errstr());
        }
    }

    /**
     * Create a job container from a Kafka message.
     */
    protected function createJobFromMessage(Message $message, string $topic): ?KafkaJobContainer
    {
        try {
            $headers = $message->headers ?? [];
            
            // Check if message is delayed
            if (isset($headers['delay_until']) && time() < $headers['delay_until']) {
                Log::debug('Message is delayed, skipping', [
                    'topic' => $topic,
                    'delay_until' => $headers['delay_until'],
                    'current_time' => time()
                ]);
                return null;
            }

            $payload = json_decode($message->payload, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw KafkaException::serializationError('Failed to decode JSON payload: ' . json_last_error_msg());
            }

            $attempts = (int) ($headers['attempts'] ?? 0);
            $maxAttempts = $this->config['max_attempts'] ?? 3;

            if ($attempts >= $maxAttempts) {
                Log::warning('Message exceeded max attempts, moving to dead letter queue', [
                    'topic' => $topic,
                    'attempts' => $attempts,
                    'max_attempts' => $maxAttempts,
                    'message_id' => $headers['id'] ?? 'unknown'
                ]);
                
                $this->handleFailedJob($message, $topic);
                return null;
            }

            return new KafkaJobContainer($this, $message, $topic, $payload);
            
        } catch (\Exception $e) {
            Log::error('Failed to create job from Kafka message', [
                'topic' => $topic,
                'error' => $e->getMessage(),
                'payload' => $message->payload
            ]);
            
            return null;
        }
    }

    /**
     * Handle a failed job by sending it to a dead letter queue.
     */
    protected function handleFailedJob(Message $message, string $topic): void
    {
        if (!isset($this->config['dead_letter_queue'])) {
            return;
        }

        try {
            $deadLetterTopic = $this->producer->newTopic($this->config['dead_letter_queue']);
            $headers = array_merge($message->headers ?? [], [
                'original_topic' => $topic,
                'failed_at' => time(),
                'failure_reason' => 'max_attempts_exceeded'
            ]);

            $deadLetterTopic->producev(
                RD_KAFKA_PARTITION_UA,
                0,
                $message->payload,
                null,
                $headers
            );

            $this->producer->flush(1000);
            
            Log::info('Message moved to dead letter queue', [
                'original_topic' => $topic,
                'dead_letter_queue' => $this->config['dead_letter_queue'],
                'message_id' => $headers['id'] ?? 'unknown'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to move message to dead letter queue', [
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if the consumer is subscribed to a topic.
     */
    protected function isSubscribed(string $topic): bool
    {
        $subscription = $this->consumer->getSubscription();
        return in_array($topic, $subscription);
    }

    /**
     * Get the default queue name.
     */
    protected function getDefaultQueue(): string
    {
        return $this->config['default_topic'] ?? 'default';
    }

    /**
     * Create a payload string from the given job and data.
     */
    protected function createPayload($job, $queue, $data = ''): string
    {
        $payload = [
            'uuid' => Str::uuid()->toString(),
            'displayName' => $this->getDisplayName($job),
            'job' => $job,
            'data' => $data,
            'attempts' => 0,
            'pushedAt' => time(),
        ];

        return json_encode($payload);
    }

    /**
     * Get the display name for the given job.
     */
    protected function getDisplayName($job): string
    {
        return is_string($job) ? $job : get_class($job);
    }

    /**
     * Get the producer instance.
     */
    public function getProducer(): Producer
    {
        return $this->producer;
    }

    /**
     * Get the consumer instance.
     */
    public function getConsumer(): KafkaConsumer
    {
        return $this->consumer;
    }

    /**
     * Get the configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
