#!/bin/bash

# Amadeus API Test Script
# Tests the API credentials and flight search functionality

# Load environment variables
if [ -f .env ]; then
    export $(grep -v '^#' .env | xargs)
fi

# Set defaults if not set
API_KEY=${AMADEUS_API_KEY:-"demo_key"}
API_SECRET=${AMADEUS_API_SECRET:-"demo_secret"}
ENVIRONMENT=${AMADEUS_ENVIRONMENT:-"test"}

# Set base URL based on environment
if [ "$ENVIRONMENT" = "live" ]; then
    BASE_URL="https://api.amadeus.com"
else
    BASE_URL="https://test.api.amadeus.com"
fi

echo "🔍 Testing Amadeus API with curl"
echo "=================================="
echo "Environment: $ENVIRONMENT"
echo "Base URL: $BASE_URL"
echo ""

# Step 1: Get Access Token
echo "📝 Step 1: Getting access token..."
TOKEN_RESPONSE=$(curl -s -X POST "$BASE_URL/v1/security/oauth2/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=client_credentials&client_id=$API_KEY&client_secret=$API_SECRET")

# Check if token request was successful
if echo "$TOKEN_RESPONSE" | grep -q "access_token"; then
    echo "Full token response: $TOKEN_RESPONSE"
    # Extract token by removing whitespace first, then parsing
    CLEAN_RESPONSE=$(echo "$TOKEN_RESPONSE" | tr -d '\n\r\t ')
    ACCESS_TOKEN=$(echo "$CLEAN_RESPONSE" | sed 's/.*"access_token":"\([^"]*\)".*/\1/')
    echo "Clean response: $CLEAN_RESPONSE"
    echo "Extracted token: '$ACCESS_TOKEN'"
    echo "✅ Token obtained successfully"
    echo "Token: ${ACCESS_TOKEN:0:20}..."
    echo "Token length: ${#ACCESS_TOKEN}"
    
    if [ -z "$ACCESS_TOKEN" ] || [ "$ACCESS_TOKEN" = "null" ] || [ ${#ACCESS_TOKEN} -lt 10 ]; then
        echo "❌ Token extraction failed!"
        echo "Clean response: $CLEAN_RESPONSE"
        exit 1
    fi
    echo ""
else
    echo "❌ Failed to get access token"
    echo "Response: $TOKEN_RESPONSE"
    echo ""
    echo "💡 Make sure your API credentials are valid!"
    exit 1
fi

# Step 2: Test Flight Search
echo "✈️  Step 2: Testing flight search..."

FLIGHT_SEARCH_DATA='{
  "currencyCode": "USD",
  "originDestinations": [
    {
      "id": "1",
      "originLocationCode": "JFK",
      "destinationLocationCode": "LAX",
      "departureDateTimeRange": {
        "date": "2025-12-01"
      }
    }
  ],
  "travelers": [
    {
      "id": "1",
      "travelerType": "ADULT"
    }
  ],
  "sources": ["GDS"],
  "searchCriteria": {
    "maxFlightOffers": 5
  }
}'

SEARCH_RESPONSE=$(curl -s -X POST "$BASE_URL/v2/shopping/flight-offers" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/vnd.amadeus+json" \
  -d "$FLIGHT_SEARCH_DATA")

# Debug: Show what we're sending
echo "Debug - Authorization header: Bearer ${ACCESS_TOKEN:0:20}..."
echo "Debug - Content-Type: application/vnd.amadeus+json"
echo "Debug - Request data length: ${#FLIGHT_SEARCH_DATA} characters"
echo ""

# Check if search was successful
if echo "$SEARCH_RESPONSE" | grep -q '"data":\['; then
    FLIGHT_COUNT=$(echo "$SEARCH_RESPONSE" | grep -o '"data":\[[^]]*\]' | grep -o '"id"' | wc -l)
    echo "✅ Flight search successful!"
    echo "Found $FLIGHT_COUNT flight offers"
    echo ""
    echo "🎉 API is working correctly!"
    echo "You can now run the k6 load test with confidence."
else
    echo "❌ Flight search failed"
    echo "Response: $SEARCH_RESPONSE"
    echo ""
    echo "💡 Possible issues:"
    echo "   - API plan doesn't include flight search"
    echo "   - Token format issue"
    echo "   - Request format issue"
    echo ""
    echo "Let's try a simpler API endpoint to test token validity..."
fi

# Step 3: Test a simpler endpoint (airport search)
echo ""
echo "🔍 Step 3: Testing airport search (simpler endpoint)..."

AIRPORT_RESPONSE=$(curl -s -X GET "$BASE_URL/v1/reference-data/locations?subType=AIRPORT&keyword=JFK" \
  -H "Authorization: Bearer $ACCESS_TOKEN")

# Check if response contains data array (more robust check)
if echo "$AIRPORT_RESPONSE" | grep -q '"data"' && echo "$AIRPORT_RESPONSE" | grep -q '"iataCode"'; then
    AIRPORT_COUNT=$(echo "$AIRPORT_RESPONSE" | grep -o '"iataCode"' | wc -l)
    echo "✅ Airport search successful!"
    echo "Found $AIRPORT_COUNT airports"
    echo ""
    echo "🎉 All API endpoints are working correctly!"
    echo "Your Amadeus API credentials are valid and functional."
else
    echo "❌ Even airport search failed"
    echo "Response: $AIRPORT_RESPONSE"
    echo ""
    echo "❌ Token appears to be invalid or API credentials are wrong"
fi