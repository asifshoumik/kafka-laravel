<?php

namespace Asifshoumik\KafkaLaravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class KafkaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payload;
    protected $topic;
    protected $partition;
    protected $offset;
    protected $timestamp;

    public function __construct(array $payload, string $topic, int $partition, int $offset, ?int $timestamp = null)
    {
        $this->payload = $payload;
        $this->topic = $topic;
        $this->partition = $partition;
        $this->offset = $offset;
        $this->timestamp = $timestamp ?? time();
    }

    public function handle(): void
    {
        // Default implementation - should be overridden by specific job classes
        $this->processPayload($this->payload);
    }

    protected function processPayload(array $payload): void
    {
        // Override this method in your specific job implementations
        Log::info('Processing Kafka message', [
            'topic' => $this->topic,
            'partition' => $this->partition,
            'offset' => $this->offset,
            'payload' => $payload,
        ]);
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getPartition(): int
    {
        return $this->partition;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getTimestamp(): Carbon
    {
        return Carbon::createFromTimestamp($this->timestamp);
    }
}
