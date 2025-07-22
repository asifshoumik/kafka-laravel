# Installation Guide

## Prerequisites

Before installing this package, you must have the rdkafka PHP extension installed on your system.

## Step-by-Step Installation

### 1. Install librdkafka (C library)

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install librdkafka-dev
```

**CentOS/RHEL/Fedora:**
```bash
# For CentOS/RHEL 7
sudo yum install librdkafka-devel

# For CentOS/RHEL 8+ or Fedora
sudo dnf install librdkafka-devel
```

**macOS:**
```bash
brew install librdkafka
```

**Windows:**
Download pre-compiled binaries from [librdkafka releases](https://github.com/edenhill/librdkafka/releases)

### 2. Install PHP rdkafka Extension

**Using PECL (Recommended):**
```bash
sudo pecl install rdkafka
```

**From Source (if PECL fails):**
```bash
git clone https://github.com/arnaud-lb/php-rdkafka.git
cd php-rdkafka
phpize
./configure
make
sudo make install
```

### 3. Configure PHP

Add the extension to your php.ini file:
```ini
extension=rdkafka
```

**Find your php.ini location:**
```bash
php --ini
```

### 4. Restart Web Server

After adding the extension to php.ini, restart your web server:

**Apache:**
```bash
sudo systemctl restart apache2
# or
sudo service apache2 restart
```

**Nginx with PHP-FPM:**
```bash
sudo systemctl restart php8.1-fpm  # Replace with your PHP version
sudo systemctl restart nginx
```

### 5. Verify Installation

```bash
php -m | grep rdkafka
```

You should see `rdkafka` in the output.

### 6. Install the Laravel Package

Now you can install the package:
```bash
composer require asifshoumik/kafka-laravel
```

The package will automatically register itself with Laravel's service container.

### 7. Configure Your Environment

Update your `.env` file with your Kafka settings:
```env
QUEUE_CONNECTION=kafka
KAFKA_BOOTSTRAP_SERVERS=your-kafka-server:9092
KAFKA_GROUP_ID=laravel-consumer-group
KAFKA_DEFAULT_TOPIC=laravel-jobs
```

### 8. Optional: Publish Configuration

If you need to customize advanced settings, publish the configuration file:
```bash
php artisan vendor:publish --tag=kafka-queue-config
```

This creates `config/kafka-queue.php` with all available options.

That's it! You can now start using Kafka as your queue driver.

## Docker Installation

If you're using Docker, add this to your Dockerfile:

```dockerfile
# Install librdkafka
RUN apt-get update && apt-get install -y \
    librdkafka-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP rdkafka extension
RUN pecl install rdkafka \
    && docker-php-ext-enable rdkafka
```

## Common Issues

### PECL Installation Fails

If `pecl install rdkafka` fails:

1. Make sure you have development tools installed:
   ```bash
   # Ubuntu/Debian
   sudo apt-get install build-essential php-dev
   
   # CentOS/RHEL
   sudo yum groupinstall "Development Tools"
   sudo yum install php-devel
   ```

2. Try installing from source (see step 2 above)

### Extension Not Loading

1. Check if the extension file exists:
   ```bash
   find /usr -name "rdkafka.so" 2>/dev/null
   ```

2. Verify the extension path in php.ini matches the actual location

3. Check PHP error logs for loading errors

### Windows Installation

For Windows, the easiest approach is to:

1. Download the appropriate DLL from [PECL rdkafka page](https://pecl.php.net/package/rdkafka)
2. Choose the version that matches your PHP version and architecture (x64/x86)
3. Place the `php_rdkafka.dll` file in your PHP extensions directory
4. Add `extension=rdkafka` to your php.ini file
5. Restart your web server

## Testing Your Installation

Create a simple test file:

```php
<?php
if (extension_loaded('rdkafka')) {
    echo "rdkafka extension is loaded successfully!\n";
    echo "librdkafka version: " . rd_kafka_version_str() . "\n";
} else {
    echo "rdkafka extension is NOT loaded\n";
}
```

Run it with: `php test.php`
