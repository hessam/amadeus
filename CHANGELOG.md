## [3.2.5] - 2025-11-22

### Fixed
- **JavaScript Error**: `clearErrorMessage` function not defined - moved function to flight search script
- **jQuery UI Images**: 404 errors for missing UI images - switched to WordPress built-in jQuery UI styles
- **Script Dependencies**: Resolved undefined function errors in production environment

### Technical Improvements
- **Code Reliability**: Fixed JavaScript function dependencies and asset loading issues
- **Asset Management**: Improved CSS loading to prevent broken image references

## [3.2.4] - 2025-11-22

### Added
- **API Error Alert System**: Automated email notifications to administrators when API errors exceed threshold (10 errors/hour)
- **Response Caching**: 1-hour caching for GET requests to improve performance and reduce API calls
- **Rate Limiting**: IP-based rate limiting (20/min for locations, 5/min for flights) to prevent abuse
- **Skeleton Loading Screens**: Improved UX with animated skeleton placeholders during API calls
- **Accessibility Enhancements**: ARIA labels, keyboard navigation, and screen reader support
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
- **Critical Bug**: PHP Fatal Error due to undefined constants - reordered constant definitions
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