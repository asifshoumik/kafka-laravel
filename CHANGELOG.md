# Changelog

All notable changes to `kafka-laravel` will be documented in this file.

## [1.0.0] - 2025-01-27

### Added
- Auto-registration of Kafka queue connection - no manual queue.php configuration required
- SSL certificate string support for Kubernetes environments  
- Enhanced SSL configuration documentation
- Complete Kafka queue driver implementation with Laravel integration
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
- Simplified installation process - removed manual setup steps
- Improved documentation with streamlined Quick Start guide
- Better SSL certificate handling for containerized deployments
- Minimum PHP version requirement to 8.1
- Minimum Laravel version requirement to 10.0
- Improved job serialization and deserialization
- Better exception handling throughout the package
- Enhanced configuration options

### Removed
- `kafka:setup` command - no longer needed with auto-registration
- Manual queue.php configuration requirement

### Fixed
- Memory leaks in long-running consumers
- Connection stability issues
- Configuration architecture for published packages
- Job retry logic improvements
- Producer acknowledgment handling

### Removed
- Support for older PHP versions (< 8.1)
- Support for older Laravel versions (< 10.0)
- Deprecated serialization methods

## [1.0.1] - 2026-01-05

### Added
- Added compatibility with Laravel 12.x (composer constraints updated)

### Changed
- Updated `orchestra/testbench` dev dependency to include v10 for Laravel 12 testing

## [1.0.0] - Initial Release

### Added
- Basic Kafka queue driver for Laravel
- Simple producer and consumer functionality
- Basic job processing capabilities
