<?php

namespace Asifs\KafkaQueueLaravel\Exceptions;

use Exception;

class KafkaException extends Exception
{
    public static function connectionFailed(string $message): self
    {
        return new self("Kafka connection failed: {$message}");
    }

    public static function configurationInvalid(string $message): self
    {
        return new self("Kafka configuration invalid: {$message}");
    }

    public static function producerError(string $message): self
    {
        return new self("Kafka producer error: {$message}");
    }

    public static function consumerError(string $message): self
    {
        return new self("Kafka consumer error: {$message}");
    }

    public static function serializationError(string $message): self
    {
        return new self("Kafka serialization error: {$message}");
    }
}
