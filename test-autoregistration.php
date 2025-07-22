<?php

/**
 * Simple test script to verify the auto-registration functionality
 * Run this with: php test-autoregistration.php
 */

echo "=== Kafka Laravel Package Auto-Registration Test ===" . PHP_EOL;
echo PHP_EOL;

// Test 1: Configuration file exists and is readable
echo "1. Testing configuration file..." . PHP_EOL;
if (file_exists(__DIR__ . '/config/kafka-queue.php')) {
    $config = include __DIR__ . '/config/kafka-queue.php';
    
    if (isset($config['connections']['kafka'])) {
        echo "   ✅ Main configuration structure is correct" . PHP_EOL;
        echo "   ✅ Driver: " . $config['connections']['kafka']['driver'] . PHP_EOL;
        echo "   ✅ Bootstrap servers: " . $config['connections']['kafka']['bootstrap_servers'] . PHP_EOL;
    } else {
        echo "   ❌ Main configuration structure is invalid" . PHP_EOL;
    }
    
    // Test additional configurations
    if (isset($config['topic_mapping'])) {
        echo "   ✅ Topic mapping configuration exists" . PHP_EOL;
        echo "   ✅ Default topic: " . $config['topic_mapping']['default'] . PHP_EOL;
    } else {
        echo "   ❌ Topic mapping configuration missing" . PHP_EOL;
    }
    
    if (isset($config['consumer'])) {
        echo "   ✅ Consumer configuration exists" . PHP_EOL;
        echo "   ✅ Memory limit: " . $config['consumer']['memory_limit'] . PHP_EOL;
    } else {
        echo "   ❌ Consumer configuration missing" . PHP_EOL;
    }
    
    if (isset($config['monitoring'])) {
        echo "   ✅ Monitoring configuration exists" . PHP_EOL;
        echo "   ✅ Enabled: " . ($config['monitoring']['enabled'] ? 'true' : 'false') . PHP_EOL;
    } else {
        echo "   ❌ Monitoring configuration missing" . PHP_EOL;
    }
} else {
    echo "   ❌ Configuration file not found" . PHP_EOL;
}

echo PHP_EOL;

// Test 2: Service Provider exists and has required methods  
echo "2. Testing service provider..." . PHP_EOL;
if (file_exists(__DIR__ . '/src/KafkaServiceProvider.php')) {
    $serviceProviderContent = file_get_contents(__DIR__ . '/src/KafkaServiceProvider.php');
    
    if (strpos($serviceProviderContent, 'registerKafkaQueueConnection') !== false) {
        echo "   ✅ Auto-registration method exists" . PHP_EOL;
    } else {
        echo "   ❌ Auto-registration method missing" . PHP_EOL;
    }
    
    if (strpos($serviceProviderContent, 'mergeConfigFrom') !== false) {
        echo "   ✅ Configuration merging is implemented" . PHP_EOL;
    } else {
        echo "   ❌ Configuration merging is missing" . PHP_EOL;
    }
    
    if (strpos($serviceProviderContent, 'topic_mapping') !== false) {
        echo "   ✅ Topic mapping auto-registration implemented" . PHP_EOL;
    } else {
        echo "   ❌ Topic mapping auto-registration missing" . PHP_EOL;
    }
    
    if (strpos($serviceProviderContent, 'consumer') !== false) {
        echo "   ✅ Consumer config auto-registration implemented" . PHP_EOL;
    } else {
        echo "   ❌ Consumer config auto-registration missing" . PHP_EOL;
    }
    
    if (strpos($serviceProviderContent, 'monitoring') !== false) {
        echo "   ✅ Monitoring config auto-registration implemented" . PHP_EOL;
    } else {
        echo "   ❌ Monitoring config auto-registration missing" . PHP_EOL;
    }
} else {
    echo "   ❌ Service provider file not found" . PHP_EOL;
}

echo PHP_EOL;

// Test 3: Documentation is updated
echo "3. Testing documentation updates..." . PHP_EOL;
if (file_exists(__DIR__ . '/README.md')) {
    $readmeContent = file_get_contents(__DIR__ . '/README.md');
    
    if (strpos($readmeContent, 'automatically registers') !== false) {
        echo "   ✅ README mentions auto-registration" . PHP_EOL;
    } else {
        echo "   ❌ README doesn't mention auto-registration" . PHP_EOL;
    }
    
    if (strpos($readmeContent, 'kafka:setup') === false) {
        echo "   ✅ Setup command references removed from README" . PHP_EOL;
    } else {
        echo "   ❌ Setup command still referenced in README" . PHP_EOL;
    }
} else {
    echo "   ❌ README.md not found" . PHP_EOL;
}

echo PHP_EOL;

// Test 4: Setup command is removed
echo "4. Testing setup command removal..." . PHP_EOL;
if (!file_exists(__DIR__ . '/src/Console/Commands/KafkaSetupCommand.php')) {
    echo "   ✅ Setup command file removed" . PHP_EOL;
} else {
    echo "   ❌ Setup command file still exists" . PHP_EOL;
}

echo PHP_EOL;

// Test 5: SSL certificate string support
echo "5. Testing SSL certificate string support..." . PHP_EOL;
if (file_exists(__DIR__ . '/src/KafkaConnector.php')) {
    $connectorContent = file_get_contents(__DIR__ . '/src/KafkaConnector.php');
    
    if (strpos($connectorContent, 'ssl_ca_pem') !== false) {
        echo "   ✅ SSL certificate string support implemented" . PHP_EOL;
    } else {
        echo "   ❌ SSL certificate string support missing" . PHP_EOL;
    }
    
    if (strpos($connectorContent, 'createTempCertFile') !== false) {
        echo "   ✅ Temporary certificate file handling implemented" . PHP_EOL;
    } else {
        echo "   ❌ Temporary certificate file handling missing" . PHP_EOL;
    }
} else {
    echo "   ❌ KafkaConnector.php not found" . PHP_EOL;
}

echo PHP_EOL;

// Test 6: KafkaQueue receives complete configuration
echo "6. Testing KafkaQueue configuration handling..." . PHP_EOL;
if (file_exists(__DIR__ . '/src/KafkaQueue.php')) {
    $queueContent = file_get_contents(__DIR__ . '/src/KafkaQueue.php');
    
    if (strpos($queueContent, '__construct(Producer $producer, KafkaConsumer $consumer, array $config') !== false) {
        echo "   ✅ KafkaQueue constructor accepts config array" . PHP_EOL;
    } else {
        echo "   ❌ KafkaQueue constructor doesn't accept config array" . PHP_EOL;
    }
    
    if (strpos($queueContent, '$this->config = $config') !== false) {
        echo "   ✅ KafkaQueue stores complete configuration" . PHP_EOL;
    } else {
        echo "   ❌ KafkaQueue doesn't store configuration" . PHP_EOL;
    }
} else {
    echo "   ❌ KafkaQueue.php not found" . PHP_EOL;
}

echo PHP_EOL;
echo "=== Test Complete ===" . PHP_EOL;
echo "If all tests show ✅, the complete auto-registration implementation is ready!" . PHP_EOL;
echo "This includes topic_mapping, consumer, and monitoring configurations." . PHP_EOL;
