# Upgrade Guide

## From v0.x to v1.0.0

### Overview

Version 1.0.0 introduces significant improvements to the package's ease of use and SSL configuration capabilities.

### What's Changed

#### 🎉 **Auto-Registration (No Breaking Changes)**

The package now automatically registers the Kafka queue connection. This means:

- **No more manual `config/queue.php` configuration required**
- **No more `php artisan kafka:setup` command needed**
- Your existing configuration will continue to work

#### 🔒 **Enhanced SSL Support**

New SSL certificate string support for Kubernetes environments:

```env
# New: Use certificate strings directly
KAFKA_SSL_CA_PEM="-----BEGIN CERTIFICATE-----..."
KAFKA_SSL_CERT_PEM="-----BEGIN CERTIFICATE-----..."
KAFKA_SSL_KEY_PEM="-----BEGIN PRIVATE KEY-----..."

# Still supported: File paths
KAFKA_SSL_CA_LOCATION="/path/to/ca.crt"
```

### Upgrade Steps

1. **Update the package:**
   ```bash
   composer update asifshoumik/kafka-laravel
   ```

   Or for new installations:
   ```bash
   composer require asifshoumik/kafka-laravel
   ```

2. **Optional - Simplify your setup:**
   If you manually configured Kafka in `config/queue.php`, you can now remove it:
   
   ```php
   // You can remove this from config/queue.php
   'connections' => [
       'kafka' => [
           // ... this entire block can be removed
       ],
   ];
   ```

3. **For SSL users:**
   Consider using the new certificate string options if you're in a containerized environment.

### What Still Works

- All existing job classes
- All existing configuration in `config/kafka-queue.php`
- All environment variables
- Manual `config/queue.php` configuration (if you prefer to keep it)

### What's Deprecated

- `php artisan kafka:setup` command (no longer available)

### Benefits

- **Simpler installation** - Just `composer require` and configure `.env`
- **Better Kubernetes support** - SSL certificates from secrets
- **Cleaner codebase** - Reduced configuration complexity
- **More reliable** - Auto-registration follows Laravel best practices
- **Production ready** - Comprehensive feature set for enterprise use

### Need Help?

Check the updated documentation:
- [README.md](README.md) - Updated installation guide
- [INSTALLATION.md](INSTALLATION.md) - Detailed installation steps
- [SSL-CONFIGURATION.md](SSL-CONFIGURATION.md) - Enhanced SSL guide
- [CONFIGURATION.md](CONFIGURATION.md) - Complete configuration architecture
