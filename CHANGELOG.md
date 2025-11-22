# Amadeus Flight Search Pro Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.2.4] - 2025-11-22

### Added
- **Zero States UI**: Enhanced user experience with helpful messaging when no flight results are found
- **Load Testing**: Comprehensive k6 load testing script for performance validation
- **API Testing**: curl-based test script for validating Amadeus API credentials and endpoints
- **Feature Flags**: Hotel search functionality can now be enabled/disabled via settings
- **Structured Logging**: Improved error handling and logging in API communications
- **Environment Management**: Secure credential handling via .env files with development server support
- **Development Tools**: Added dev-server.sh script for easy local development setup

### Enhanced
- **API Integration**: Robust error handling and token management for Amadeus API
- **Performance**: Load testing confirms plugin can handle up to 50 concurrent users
- **Security**: Environment variable support prevents credential exposure
- **User Experience**: Better feedback and error states throughout the application

### Technical Improvements
- **Code Quality**: Added comprehensive testing infrastructure
- **Monitoring**: Structured logging for better debugging and monitoring
- **Configuration**: Flexible environment-based configuration system
- **Testing**: Automated API validation and performance testing

### Fixed
- **Error Handling**: Improved error messages and user feedback
- **API Reliability**: Better handling of API failures and timeouts

## [3.2.3] - 2025-09-15

### Added
- Initial release of Amadeus Flight Search Pro
- Basic flight search functionality
- Gravity Forms integration
- WordPress admin settings
- AJAX-powered search interface

### Technical
- Amadeus Self-Service Flight API integration
- OAuth2 authentication
- Responsive frontend design
- Multilingual support (Persian/English)