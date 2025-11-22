#!/bin/bash

# Amadeus Flight Search Development Server Script
# This script manages the development environment for the Amadeus Flight Search WordPress plugin

set -e

# Load environment variables
if [ -f .env ]; then
    export $(grep -v '^#' .env | xargs)
fi

# Default values if not set
DEV_PORT=${DEV_PORT:-8000}
DEV_HOST=${DEV_HOST:-localhost}
WP_DB_NAME=${WP_DB_NAME:-amadeus_flight_search}
WP_DB_USER=${WP_DB_USER:-root}
WP_DB_PASSWORD=${WP_DB_PASSWORD:-password}
WP_DB_HOST=${WP_DB_HOST:-localhost}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to start development server
start_server() {
    print_status "Starting Amadeus Flight Search development server..."

    # Check if Docker is available
    if command_exists docker && command_exists docker-compose; then
        print_status "Using Docker Compose for development environment"

        # Check if docker-compose.yml exists
        if [ ! -f docker-compose.yml ]; then
            print_error "docker-compose.yml not found. Creating basic development setup..."
            create_docker_compose
        fi

        # Start containers
        docker-compose up -d
        print_success "Development server started at http://$DEV_HOST:$DEV_PORT"

    elif command_exists php; then
        print_status "Using built-in PHP server"

        # Check if WordPress is installed
        if [ ! -d "wordpress" ]; then
            print_warning "WordPress not found. Installing WordPress..."
            install_wordpress
        fi

        # Start PHP built-in server
        cd wordpress
        php -S $DEV_HOST:$DEV_PORT &
        SERVER_PID=$!
        echo $SERVER_PID > ../server.pid
        cd ..
        print_success "Development server started at http://$DEV_HOST:$DEV_PORT (PID: $SERVER_PID)"

    else
        print_error "Neither Docker nor PHP found. Please install one of them."
        exit 1
    fi
}

# Function to stop development server
stop_server() {
    print_status "Stopping development server..."

    if [ -f server.pid ]; then
        SERVER_PID=$(cat server.pid)
        if kill -0 $SERVER_PID 2>/dev/null; then
            kill $SERVER_PID
            print_success "Server stopped (PID: $SERVER_PID)"
        else
            print_warning "Server process not found"
        fi
        rm server.pid
    elif command_exists docker-compose && [ -f docker-compose.yml ]; then
        docker-compose down
        print_success "Docker containers stopped"
    else
        print_warning "No running server found"
    fi
}

# Function to show server logs
show_logs() {
    if [ -f server.pid ]; then
        print_error "Logs not available for built-in PHP server"
    elif command_exists docker-compose && [ -f docker-compose.yml ]; then
        docker-compose logs -f
    else
        print_error "No log source available"
    fi
}

# Function to create basic docker-compose.yml
create_docker_compose() {
    cat > docker-compose.yml << EOF
version: '3.8'

services:
  wordpress:
    image: wordpress:latest
    ports:
      - "${DEV_PORT}:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_NAME: ${WP_DB_NAME}
      WORDPRESS_DB_USER: ${WP_DB_USER}
      WORDPRESS_DB_PASSWORD: ${WP_DB_PASSWORD}
      WORDPRESS_DEBUG: 1
    volumes:
      - ./wordpress:/var/www/html
      - ./:/var/www/html/wp-content/plugins/amadeus-flight-search
    depends_on:
      - db

  db:
    image: mysql:5.7
    environment:
      MYSQL_DATABASE: ${WP_DB_NAME}
      MYSQL_USER: ${WP_DB_USER}
      MYSQL_PASSWORD: ${WP_DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: rootpassword
    volumes:
      - db_data:/var/lib/mysql

volumes:
  db_data:
EOF
    print_success "Created docker-compose.yml"
}

# Function to install WordPress
install_wordpress() {
    print_status "Installing WordPress..."

    # Create wordpress directory
    mkdir -p wordpress

    # Download WordPress
    if command_exists wget; then
        wget -q https://wordpress.org/latest.tar.gz -O wordpress.tar.gz
    elif command_exists curl; then
        curl -s -o wordpress.tar.gz https://wordpress.org/latest.tar.gz
    else
        print_error "Neither wget nor curl found. Please install one of them."
        exit 1
    fi

    # Extract WordPress
    tar -xzf wordpress.tar.gz --strip-components=1 -C wordpress
    rm wordpress.tar.gz

    # Create wp-config.php
    cat > wordpress/wp-config.php << EOF
<?php
define( 'DB_NAME', '${WP_DB_NAME}' );
define( 'DB_USER', '${WP_DB_USER}' );
define( 'DB_PASSWORD', '${WP_DB_PASSWORD}' );
define( 'DB_HOST', '${WP_DB_HOST}' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY',         'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here' );
define( 'NONCE_KEY',        'put your unique phrase here' );
define( 'AUTH_SALT',        'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here' );
define( 'NONCE_SALT',       'put your unique phrase here' );

\$table_prefix = 'wp_';
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
EOF

    print_success "WordPress installed"
}

# Function to setup plugin
setup_plugin() {
    print_status "Setting up Amadeus Flight Search plugin..."

    # Create necessary directories
    mkdir -p src/backend
    mkdir -p src/mobile

    # Create log files
    touch src/backend/backend.log
    touch src/mobile/mobile.log

    print_success "Plugin setup complete"
}

# Function to show help
show_help() {
    echo "Amadeus Flight Search Development Server"
    echo ""
    echo "Usage: $0 [command]"
    echo ""
    echo "Commands:"
    echo "  start     Start the development server"
    echo "  stop      Stop the development server"
    echo "  logs      Show server logs"
    echo "  setup     Setup the plugin environment"
    echo "  help      Show this help message"
    echo ""
    echo "Environment variables (.env file):"
    echo "  DEV_PORT          Development server port (default: 8000)"
    echo "  DEV_HOST          Development server host (default: localhost)"
    echo "  WP_DB_*           WordPress database configuration"
    echo "  AMADEUS_*         Amadeus API configuration"
}

# Main script logic
case "${1:-start}" in
    start)
        setup_plugin
        start_server
        ;;
    stop)
        stop_server
        ;;
    logs)
        show_logs
        ;;
    setup)
        setup_plugin
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        print_error "Unknown command: $1"
        echo ""
        show_help
        exit 1
        ;;
esac