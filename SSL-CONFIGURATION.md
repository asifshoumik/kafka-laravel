# SSL/TLS Configuration Guide

This guide explains how to configure SSL/TLS connections for the Kafka Laravel package using certificates.

## Types of SSL Authentication

### 1. Server Authentication Only (One-way SSL)
- Client verifies the server's identity using a CA certificate
- Server does not verify client identity
- Only requires a CA certificate file

### 2. Mutual Authentication (Two-way SSL/mTLS)
- Both client and server verify each other's identity
- Requires CA certificate, client certificate, and client private key

## Configuration for Your Certificate

You can configure SSL certificates in two ways: using file paths or certificate strings (PEM content).

### Method 1: Certificate Files

```env
# Basic SSL configuration with CA certificate file
KAFKA_SECURITY_PROTOCOL=SSL
KAFKA_SSL_CA_LOCATION=/path/to/your/ca-certificate.crt

# Kafka broker configuration
KAFKA_BOOTSTRAP_SERVERS=your-kafka-broker:9093
KAFKA_GROUP_ID=laravel-consumer-group
KAFKA_DEFAULT_TOPIC=laravel-jobs
```

### Method 2: Certificate Strings (Kubernetes Secrets)

```env
# Basic SSL configuration with CA certificate as PEM string
KAFKA_SECURITY_PROTOCOL=SSL
KAFKA_SSL_CA_PEM="-----BEGIN CERTIFICATE-----
MIIErDCCApSgAwIBAgIRdmZqCilhcM2YHm66+aSAjD8wDQYJKoZIhvcNAQELBQAw
PDELMAkGA1UEBhMCQ04xDzANBgNVBAoTBkh1YXdlaTEcMBoGA1UEAxMTSHVhd2Vp
IEVxdWlwbWVudCBDQTAeFw0xNzA4MzExMjEzMTRaFw00MVVaFw00MVUeFw0xNzA4
...
-----END CERTIFICATE-----"

# SSL verification settings (optional)
KAFKA_SSL_VERIFY_HOSTNAME=true
KAFKA_SSL_CHECK_HOSTNAME=true

# Kafka broker configuration
KAFKA_BOOTSTRAP_SERVERS=your-kafka-broker:9093
KAFKA_GROUP_ID=laravel-consumer-group
KAFKA_DEFAULT_TOPIC=laravel-jobs
```

### If You Have Client Certificates (for mutual TLS)

**Using certificate files:**
```env
KAFKA_SECURITY_PROTOCOL=SSL
KAFKA_SSL_CA_LOCATION=/path/to/your/ca-certificate.crt
KAFKA_SSL_CERTIFICATE_LOCATION=/path/to/your/client.crt
KAFKA_SSL_KEY_LOCATION=/path/to/your/client.key
```

**Using certificate strings:**
```env
KAFKA_SECURITY_PROTOCOL=SSL
KAFKA_SSL_CA_PEM="-----BEGIN CERTIFICATE-----..."
KAFKA_SSL_CERTIFICATE_PEM="-----BEGIN CERTIFICATE-----..."
KAFKA_SSL_KEY_PEM="-----BEGIN PRIVATE KEY-----..."
```

## SSL Verification Options

The package supports additional SSL verification controls for enhanced security:

### Certificate Verification (`ssl_verify_hostname`)
```env
KAFKA_SSL_VERIFY_HOSTNAME=true  # Default: true
```
- **true**: Enables SSL certificate verification against the CA
- **false**: Disables certificate verification (not recommended for production)

**When to disable:**
- Testing with self-signed certificates
- Development environments
- When you have certificate trust issues that need debugging

### Hostname Verification (`ssl_check_hostname`)
```env
KAFKA_SSL_CHECK_HOSTNAME=true  # Default: true
```
- **true**: Verifies that the certificate hostname matches the broker hostname
- **false**: Skips hostname verification (not recommended for production)

**When to disable:**
- When broker hostnames don't match certificate CN/SAN
- Load balancers with different hostnames
- IP-based connections with hostname certificates

### Security Combinations

**Maximum Security (Production):**
```env
KAFKA_SSL_VERIFY_HOSTNAME=true
KAFKA_SSL_CHECK_HOSTNAME=true
```

**Development/Testing:**
```env
KAFKA_SSL_VERIFY_HOSTNAME=false
KAFKA_SSL_CHECK_HOSTNAME=false
```

**Partial Verification (when hostname doesn't match):**
```env
KAFKA_SSL_VERIFY_HOSTNAME=true
KAFKA_SSL_CHECK_HOSTNAME=false
```

## File Path Considerations

### Windows Paths
```env
# Option 1: Forward slashes (recommended)
KAFKA_SSL_CA_LOCATION=C:/path/to/certificates/ca-certificate.crt

# Option 2: Escaped backslashes
KAFKA_SSL_CA_LOCATION=C:\\path\\to\\certificates\\ca-certificate.crt

# Option 3: Raw string (in config file)
'ssl_ca_location' => 'C:\path\to\certificates\ca-certificate.crt'
```

### Linux/macOS Paths
```env
KAFKA_SSL_CA_LOCATION=/etc/kafka/ssl/ca-cert.crt
KAFKA_SSL_CERTIFICATE_LOCATION=/etc/kafka/ssl/client-cert.crt
KAFKA_SSL_KEY_LOCATION=/etc/kafka/ssl/client-key.key
```

## Kubernetes Configuration

### Using Kubernetes Secrets with Certificate Strings

**Step 1: Create a Kubernetes Secret**
```yaml
apiVersion: v1
kind: Secret
metadata:
  name: kafka-ssl-certs
type: Opaque
data:
  ca.crt: LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0t...  # base64 encoded CA cert
  client.crt: LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0t...  # base64 encoded client cert
  client.key: LS0tLS1CRUdJTiBQUklWQVRFIEtFWS0tLS0t...  # base64 encoded private key
```

**Step 2: Use in Deployment**
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  template:
    spec:
      containers:
      - name: laravel
        image: your-laravel-app:latest
        env:
        - name: KAFKA_SECURITY_PROTOCOL
          value: "SSL"
        - name: KAFKA_SSL_CA_PEM
          valueFrom:
            secretKeyRef:
              name: kafka-ssl-certs
              key: ca.crt
        - name: KAFKA_SSL_CERTIFICATE_PEM
          valueFrom:
            secretKeyRef:
              name: kafka-ssl-certs
              key: client.crt
        - name: KAFKA_SSL_KEY_PEM
          valueFrom:
            secretKeyRef:
              name: kafka-ssl-certs
              key: client.key
```

**Step 3: Laravel Environment**
Your Laravel application will automatically receive the certificate content as environment variables.

### Alternative: Using Kubernetes Volume Mounts

**Step 1: Create Secret with Certificate Files**
```bash
kubectl create secret generic kafka-ssl-files \
  --from-file=ca.crt=/path/to/ca.crt \
  --from-file=client.crt=/path/to/client.crt \
  --from-file=client.key=/path/to/client.key
```

**Step 2: Mount as Volume**
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  template:
    spec:
      containers:
      - name: laravel
        image: your-laravel-app:latest
        env:
        - name: KAFKA_SECURITY_PROTOCOL
          value: "SSL"
        - name: KAFKA_SSL_CA_LOCATION
          value: "/etc/ssl/kafka/ca.crt"
        - name: KAFKA_SSL_CERTIFICATE_LOCATION
          value: "/etc/ssl/kafka/client.crt"
        - name: KAFKA_SSL_KEY_LOCATION
          value: "/etc/ssl/kafka/client.key"
        volumeMounts:
        - name: kafka-ssl
          mountPath: /etc/ssl/kafka
          readOnly: true
      volumes:
      - name: kafka-ssl
        secret:
          secretName: kafka-ssl-files
```

## Understanding Your Certificate

Your CA certificate file may contain one or more certificate authorities that establish the chain of trust for your Kafka cluster. Common scenarios include:

1. **Enterprise CA** - Your organization's certificate authority
2. **Cloud Provider CA** - Certificate authority from your cloud provider
3. **Third-party CA** - Certificate from a commercial certificate authority

This certificate is used to verify the identity of your Kafka brokers during SSL/TLS connections.

## Testing Your SSL Configuration

### 1. Test Certificate File Access
```bash
# For file-based certificates
php -r "echo file_exists('/path/to/your/ca-certificate.crt') ? 'File exists' : 'File not found';"

# For string-based certificates
php -r "echo !empty(\$_ENV['KAFKA_SSL_CA_PEM']) ? 'Certificate string loaded' : 'Certificate string empty';"
```

### 2. Test SSL Connection to Kafka
```bash
# Test SSL handshake with file (if openssl is available)
openssl s_client -connect your-kafka-broker:9093 -CAfile "/path/to/your/ca-certificate.crt"

# Test with certificate string (create temp file first)
echo "$KAFKA_SSL_CA_PEM" > /tmp/ca.crt && openssl s_client -connect your-kafka-broker:9093 -CAfile /tmp/ca.crt
```

### 3. Test Laravel Configuration
Create a simple test script:

```php
<?php
// test-kafka-ssl.php

require_once 'vendor/autoload.php';

$config = [
    'bootstrap_servers' => env('KAFKA_BOOTSTRAP_SERVERS'),
    'security_protocol' => 'SSL',
];

// Add SSL configuration based on what's available
if (env('KAFKA_SSL_CA_PEM')) {
    $config['ssl_ca_pem'] = env('KAFKA_SSL_CA_PEM');
    echo "Using CA certificate from PEM string\n";
} elseif (env('KAFKA_SSL_CA_LOCATION')) {
    $config['ssl_ca_location'] = env('KAFKA_SSL_CA_LOCATION');
    echo "Using CA certificate from file: " . env('KAFKA_SSL_CA_LOCATION') . "\n";
}

if (env('KAFKA_SSL_CERTIFICATE_PEM')) {
    $config['ssl_certificate_pem'] = env('KAFKA_SSL_CERTIFICATE_PEM');
    echo "Using client certificate from PEM string\n";
} elseif (env('KAFKA_SSL_CERTIFICATE_LOCATION')) {
    $config['ssl_certificate_location'] = env('KAFKA_SSL_CERTIFICATE_LOCATION');
    echo "Using client certificate from file: " . env('KAFKA_SSL_CERTIFICATE_LOCATION') . "\n";
}

if (env('KAFKA_SSL_KEY_PEM')) {
    $config['ssl_key_pem'] = env('KAFKA_SSL_KEY_PEM');
    echo "Using private key from PEM string\n";
} elseif (env('KAFKA_SSL_KEY_LOCATION')) {
    $config['ssl_key_location'] = env('KAFKA_SSL_KEY_LOCATION');
    echo "Using private key from file: " . env('KAFKA_SSL_KEY_LOCATION') . "\n";
}

try {
    $conf = new RdKafka\Conf();
    
    foreach ($config as $key => $value) {
        if ($value) {
            if (str_contains($key, '_pem')) {
                // PEM content will be handled by the KafkaConnector
                echo "PEM content loaded for {$key}\n";
            } else {
                $conf->set($key, $value);
            }
        }
    }
    
    $producer = new RdKafka\Producer($conf);
    echo "SSL configuration successful!\n";
    
} catch (Exception $e) {
    echo "SSL configuration failed: " . $e->getMessage() . "\n";
}
```

## Common SSL Errors and Solutions

### Error: "No such file or directory"
```
Failed to set SSL CA location: No such file or directory
```
**Solution:** Check that the certificate file path is correct and accessible.

### Error: "SSL handshake failed"
```
SSL handshake failed: certificate verify failed
```
**Solutions:**
- Verify the CA certificate matches your Kafka broker's certificate
- Check if the certificate has expired
- Ensure the certificate chain is complete

### Error: "Permission denied"
```
Failed to read SSL CA file: Permission denied
```
**Solution:** Ensure the web server user has read access to the certificate file, or use certificate strings instead.

### Error: "Invalid PEM format"
```
SSL configuration failed: Invalid certificate format
```
**Solutions:**
- Ensure PEM content includes proper headers (`-----BEGIN CERTIFICATE-----`) and footers (`-----END CERTIFICATE-----`)
- Check for extra whitespace or line ending issues
- Verify the certificate is properly base64 encoded in Kubernetes secrets

### Error: "Temporary file creation failed"
```
Failed to create temporary certificate file
```
**Solutions:**
- Check that the system temp directory is writable
- Ensure sufficient disk space
- Verify proper permissions on temp directory

### Error: "Certificate verify failed"
```
SSL certificate verification failed
```
**Solutions:**
- Ensure the CA certificate is correct and matches the broker's certificate
- Check certificate expiration dates
- Verify the certificate chain is complete
- For testing: Set `KAFKA_SSL_VERIFY_HOSTNAME=false`

### Error: "Hostname verification failed"  
```
SSL handshake failed: Hostname verification failed
```
**Solutions:**
- Ensure broker hostname matches certificate CN or SAN
- Check DNS resolution for broker hostname
- For IP connections: Set `KAFKA_SSL_CHECK_HOSTNAME=false`
- Use proper hostname in `KAFKA_BOOTSTRAP_SERVERS`

### Error: "Self-signed certificate"
```
SSL certificate problem: self signed certificate
```
**Solutions:**
- Add the self-signed certificate to your CA bundle
- For testing: Set both verification options to `false`
- Use proper CA-signed certificates in production

## Security Best Practices

1. **File Permissions:** Set restrictive permissions on certificate files (600 or 640)
2. **Storage Location:** Store certificates outside the web root directory
3. **Environment Variables:** Never commit certificate content to version control
4. **Certificate Rotation:** Monitor certificate expiration dates
5. **Access Control:** Limit access to certificate files to necessary users only
6. **Kubernetes Secrets:** Use proper RBAC to control access to secrets
7. **Temporary Files:** The package automatically cleans up temporary certificate files
8. **Memory:** Certificate strings are kept in memory only as long as necessary

## Alternative: Using Laravel Configuration

Instead of environment variables, you can configure SSL directly in `config/kafka-queue.php`:

**Using certificate files:**
```php
return [
    'bootstrap_servers' => 'your-kafka-broker:9093',
    'security_protocol' => 'SSL',
    'ssl_ca_location' => storage_path('certificates/ca-certificate.crt'),
    'ssl_certificate_location' => storage_path('certificates/client.crt'),
    'ssl_key_location' => storage_path('certificates/client.key'),
        'group_id' => 'laravel-consumer-group',
        'default_topic' => 'laravel-jobs',
    // ... other config
];
```

**Using certificate strings:**
```php
return [
    'bootstrap_servers' => 'your-kafka-broker:9093',
    'security_protocol' => 'SSL',
    'ssl_ca_pem' => file_get_contents(storage_path('certificates/ca.crt')),
    'ssl_certificate_pem' => env('KAFKA_SSL_CERT_PEM'), // From Kubernetes secret
    'ssl_key_pem' => env('KAFKA_SSL_KEY_PEM'), // From Kubernetes secret
    'group_id' => 'laravel-consumer-group',
    'default_topic' => 'laravel-jobs',
    // ... other config
];
```

## Next Steps

1. Place your CA certificate file in a secure location
2. Update your `.env` file with the correct path
3. Test the connection using the test script above
4. Deploy and monitor for SSL-related errors
