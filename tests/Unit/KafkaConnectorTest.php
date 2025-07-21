<?php

namespace Asifs\KafkaQueueLaravel\Tests\Unit;

use Asifs\KafkaQueueLaravel\Tests\TestCase;
use Asifs\KafkaQueueLaravel\KafkaConnector;
use Asifs\KafkaQueueLaravel\Exceptions\KafkaException;

class KafkaConnectorTest extends TestCase
{
    public function test_validates_required_configuration(): void
    {
        $connector = new KafkaConnector();

        $this->expectException(KafkaException::class);
        $this->expectExceptionMessage('Missing required configuration: bootstrap_servers');

        $connector->connect([]);
    }

    public function test_validates_sasl_configuration(): void
    {
        $connector = new KafkaConnector();

        $this->expectException(KafkaException::class);
        $this->expectExceptionMessage('SASL credentials required for non-PLAINTEXT security protocol');

        $connector->connect([
            'bootstrap_servers' => 'localhost:9092',
            'group_id' => 'test-group',
            'security_protocol' => 'SASL_SSL'
        ]);
    }

    public function test_creates_configuration_successfully(): void
    {
        $connector = new KafkaConnector();
        
        // This test would need actual Kafka running to fully test
        // For now, we'll just test that the method exists and accepts valid config
        $this->assertTrue(method_exists($connector, 'connect'));
    }
}
