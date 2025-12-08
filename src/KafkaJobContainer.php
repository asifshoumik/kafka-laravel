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
            
            // Handle nested job structure from Laravel's job serialization
            if (isset($payload['job']['job']) && is_string($payload['job']['job'])) {
                // Fully serialized job object
                $job = unserialize($payload['job']['job']);
                if (method_exists($job, 'handle')) {
                    $this->container->call([$job, 'handle']);
                }
            } elseif (isset($payload['job']) && is_string($payload['job'])) {
                // Direct serialized job class
                $job = unserialize($payload['job']);
                if (method_exists($job, 'handle')) {
                    $this->container->call([$job, 'handle']);
                }
            } elseif (isset($payload['displayName'])) {
                // Handle job class name with constructor parameters
                $jobClass = $payload['displayName'];
                
                if (class_exists($jobClass)) {
                    // Extract constructor parameters from job.data
                    $jobData = $payload['job']['data'] ?? $payload['data'] ?? [];
                    
                    // Separate numeric keys (constructor args) from named properties
                    $constructorArgs = [];
                    $namedData = [];
                    
                    foreach ($jobData as $key => $value) {
                        if (is_numeric($key)) {
                            $constructorArgs[(int)$key] = $value;
                        } else {
                            $namedData[$key] = $value;
                        }
                    }
                    
                    // Sort constructor args by key to maintain order
                    ksort($constructorArgs);
                    
                    // Instantiate job with constructor parameters
                    if (!empty($constructorArgs)) {
                        $job = new $jobClass(...array_values($constructorArgs));
                    } elseif (!empty($namedData)) {
                        // Fallback: pass named data as single array parameter
                        $job = new $jobClass($namedData);
                    } else {
                        // No parameters
                        $job = new $jobClass();
                    }
                    
                    // Set job properties from payload if available
                    if (isset($payload['job']['middleware'])) {
                        $job->middleware = $payload['job']['middleware'];
                    }
                    if (isset($payload['job']['chained'])) {
                        $job->chained = $payload['job']['chained'];
                    }
                    
                    // Execute the job
                    if (method_exists($job, 'handle')) {
                        $this->container->call([$job, 'handle']);
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
                'trace' => $e->getTraceAsString(),
                'attempts' => $this->attempts()
            ]);

            $this->fail($e);
        }
    }
}
