# Changelog

All notable changes to `kafka_queue-laravel` will be documented in this file.

## [2.0.0] - 2025-07-21

### Added
- Complete rewrite with modern Laravel standards
- Comprehensive error handling and retry mechanisms
- Dead letter queue support for failed jobs
- Delayed job execution support
- Multiple security protocol support (PLAINTEXT, SSL/TLS, SASL)
- Configurable timeouts for different scenarios
- Batch processing optimization
- Topic mapping for different job types
- Console commands for queue management
- Comprehensive logging and monitoring
- Full test suite with PHPUnit
- Detailed documentation and examples

### Changed
- Minimum PHP version requirement to 8.1
- Minimum Laravel version requirement to 10.0
- Improved job serialization and deserialization
- Better exception handling throughout the package
- Enhanced configuration options

### Fixed
- Memory leaks in long-running consumers
- Connection stability issues
- Job retry logic improvements
- Producer acknowledgment handling

### Removed
- Support for older PHP versions (< 8.1)
- Support for older Laravel versions (< 10.0)
- Deprecated serialization methods

## [1.0.0] - Initial Release

### Added
- Basic Kafka queue driver for Laravel
- Simple producer and consumer functionality
- Basic job processing capabilities
