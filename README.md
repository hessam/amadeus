# Amadeus Flight Search Pro

A comprehensive WordPress plugin that integrates the Amadeus Self-Service Flight API for flight search and booking functionality via Gravity Forms.

## Features

### 🚀 Core Functionality
- **Flight Search**: Real-time flight search with Amadeus API
- **Gravity Forms Integration**: Seamless booking workflow
- **Multi-Currency Support**: TRY and IRR currency display
- **Responsive Design**: Mobile-first approach with KimLand UI design system
- **Ajax-Powered**: Fast, dynamic search without page reloads

### 🛡️ Production Ready
- **Load Tested**: Validated with k6 for up to 50 concurrent users
- **API Validation**: Automated testing for Amadeus credentials
- **Structured Logging**: Comprehensive error tracking and monitoring
- **Environment Management**: Secure credential handling via .env files
- **Feature Flags**: Configurable hotel search functionality

### 🎨 User Experience
- **Zero States**: Helpful messaging when no results found
- **Loading States**: Professional loading indicators
- **Error Handling**: Graceful error recovery and user feedback
- **RTL Support**: Full Persian/Farsi language support
- **Accessibility**: WCAG compliant design

## Requirements

- **WordPress**: 5.2 or higher
- **PHP**: 7.2 or higher
- **Gravity Forms**: Latest version recommended
- **Amadeus API**: Valid Self-Service API credentials

## Installation

1. Download the plugin zip file
2. Upload to WordPress via **Plugins > Add New > Upload Plugin**
3. Activate the plugin
4. Configure your Amadeus API credentials in the settings
5. Create a Gravity Form with the flight search fields

## Configuration

### API Credentials
Navigate to **Settings > Amadeus Flight Search** and enter:
- **API Key**: Your Amadeus API key
- **API Secret**: Your Amadeus API secret
- **Environment**: Live or Test environment

### Environment Variables (Recommended)
For enhanced security, create a `.env` file in the plugin directory:

```env
AMADEUS_API_KEY=your_api_key_here
AMADEUS_API_SECRET=your_api_secret_here
AMADEUS_ENVIRONMENT=live
```

### Feature Flags
- **Hotel Search**: Enable/disable hotel search functionality
- **Debug Mode**: Enable detailed logging for troubleshooting

## Development

### Local Development Setup
```bash
# Start development server
./dev-server.sh start

# View logs
./dev-server.sh logs

# Stop development server
./dev-server.sh stop
```

### Testing
```bash
# Test API credentials
./test-api.sh

# Run load tests
k6 run k6-load-test.js
```

### Project Structure
```
├── admin/                 # Admin interface classes
├── includes/              # Core plugin classes
│   ├── class-amadeus-api.php      # API communication
│   ├── class-ajax.php             # AJAX handlers
│   ├── class-settings.php         # Settings management
│   └── helpers.php                # Utility functions
├── public/                # Frontend assets
│   ├── css/               # Stylesheets
│   └── js/                # JavaScript files
├── languages/             # Translation files
├── data/                  # Airport/city data
└── tests/                 # Testing infrastructure
```

## API Endpoints

The plugin integrates with the following Amadeus APIs:
- **Flight Offers Search**: Real-time flight pricing and availability
- **Flight Offers Price**: Detailed pricing information
- **Airport & City Search**: Location autocomplete functionality
- **Hotel Search**: Optional hotel booking (feature flag controlled)

## Hooks & Filters

### Actions
- `afs_before_flight_search`: Fires before flight search API call
- `afs_after_flight_search`: Fires after flight search API call
- `afs_flight_search_error`: Fires on API error

### Filters
- `afs_api_request_timeout`: Modify API request timeout (default: 30s)
- `afs_search_results_limit`: Limit number of search results
- `afs_currency_display`: Customize currency display format

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## License

This project is licensed under the GPL v3 or later - see the [LICENSE](LICENSE) file for details.

## Support

For support and questions:
- Create an issue on GitHub
- Check the [documentation](https://yourwebsite.com/docs)
- Contact: support@yourwebsite.com

## Credits

- **Amadeus**: Flight and hotel API services
- **Gravity Forms**: Form processing framework
- **WordPress**: CMS platform
- **KimLand**: UI design system inspiration

---

**Version**: 3.2.4
**Last Updated**: November 22, 2025