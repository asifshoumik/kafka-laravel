<?php

namespace Asifshoumik\KafkaLaravel;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Facades\Log;
use RdKafka\Message;

class KafkaJobContainer extends Job implements JobContract
{
    protected KafkaQueue $kafka;
    protected Message $message;
    protected string $topic;
    protected array $decoded;

    public function __construct(KafkaQueue $kafka, Message $message, string $topic, array $decoded)
    {
        $this->kafka = $kafka;
        $this->message = $message;
        $this->topic = $topic;
        $this->decoded = $decoded;
        $this->container = Container::getInstance();
    }

    /**
     * Release the job back to the queue.
     */
    public function release($delay = 0): void
    {
        parent::release($delay);

        $headers = $this->message->headers ?? [];
        $headers['attempts'] = ((int) ($headers['attempts'] ?? 0)) + 1;
        $headers['released_at'] = time();

        if ($delay > 0) {
            $headers['delay_until'] = time() + $delay;
        }

        try {
            $topic = $this->kafka->getProducer()->newTopic($this->topic);
            $topic->producev(
                RD_KAFKA_PARTITION_UA,
                0,
                $this->message->payload,
                null,
                $headers
            );

            $this->kafka->getProducer()->flush(1000);

            Log::info('Job released back to queue', [
                'topic' => $this->topic,
                'attempts' => $headers['attempts'],
                'delay' => $delay
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to release job back to queue', [
                'topic' => $this->topic,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the number of times the job has been attempted.
     */
    public function attempts(): int
    {
        return (int) ($this->message->headers['attempts'] ?? 0);
    }

    /**
     * Get the raw body string for the job.
     */
    public function getRawBody(): string
    {
        return $this->message->payload;
    }

    /**
     * Delete the job from the queue.
     */
    public function delete(): void
    {
        parent::delete();

        Log::debug('Job deleted from queue', [
            'topic' => $this->topic,
            'partition' => $this->message->partition,
            'offset' => $this->message->offset
        ]);
    }

    /**
     * Get the job identifier.
     */
    public function getJobId(): string
    {
        return $this->decoded['uuid'] ?? $this->message->headers['id'] ?? 'unknown';
    }

    /**
     * Get the name of the queued job class.
     */
    public function getName(): string
    {
        return $this->decoded['displayName'] ?? 'Unknown';
    }

    /**
     * Get the name of the connection the job belongs to.
     */
    public function getConnectionName(): string
    {
        return 'kafka';
    }

    /**
     * Get the name of the queue the job belongs to.
     */
    public function getQueue(): string
    {
        return $this->topic;
    }

    /**
     * Get the decoded body of the job.
     */
    public function payload(): array
    {
        return $this->decoded;
    }

    /**
     * Get the Kafka message.
     */
    public function getMessage(): Message
    {
        return $this->message;
    }

    /**
     * Get the topic name.
     */
    public function getTopic(): string
    {
        return $this->topic;
    }

    /**
     * Fire the job.
     */
    public function fire(): void
    {
        try {
            $payload = $this->payload();
            
            if (isset($payload['job']) && is_string($payload['job'])) {
                // Handle serialized job class
                $job = unserialize($payload['job']);
                if (method_exists($job, 'handle')) {
                    $job->handle();
                }
            } elseif (isset($payload['displayName'])) {
                // Handle job class name
                $jobClass = $payload['displayName'];
                if (class_exists($jobClass)) {
                    $job = $this->container->make($jobClass);
                    if (method_exists($job, 'handle')) {
                        $job->handle($payload['data'] ?? []);
                    }
                }
            }

            $this->delete();

            Log::info('Job processed successfully', [
                'topic' => $this->topic,
                'job_id' => $this->getJobId(),
                'job_name' => $this->getName()
            ]);

        } catch (\Exception $e) {
            Log::error('Job failed to process', [
                'topic' => $this->topic,
                'job_id' => $this->getJobId(),
                'job_name' => $this->getName(),
                'error' => $e->getMessage(),
                'attempts' => $this->attempts()
            ]);

            $this->fail($e);
        }
    }
}
