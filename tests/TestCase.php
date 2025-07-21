<?php

namespace Asifs\KafkaQueueLaravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Asifs\KafkaQueueLaravel\KafkaServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            KafkaServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('queue.default', 'kafka');
        config()->set('queue.connections.kafka', [
            'driver' => 'kafka',
            'bootstrap_servers' => 'localhost:9092',
            'group_id' => 'test-group',
            'default_topic' => 'test-topic',
            'security_protocol' => 'PLAINTEXT',
        ]);
    }
}
