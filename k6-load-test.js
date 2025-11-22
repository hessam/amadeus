import http from 'k6/http';
import { check, sleep } from 'k6';

// Test configuration
export let options = {
    stages: [
        { duration: '30s', target: 10 },  // Ramp up to 10 users over 30 seconds
        { duration: '1m', target: 10 },   // Stay at 10 users for 1 minute
        { duration: '30s', target: 50 }, // Ramp up to 50 users over 30 seconds
        { duration: '1m', target: 50 },  // Stay at 50 users for 1 minute
        { duration: '30s', target: 0 },  // Ramp down to 0 users
    ],
    thresholds: {
        http_req_duration: ['p(95)<500'], // 95% of requests should be below 500ms
        http_req_failed: ['rate<0.1'],    // Error rate should be less than 10%
    },
};

// Environment variables
const API_KEY = __ENV.AMADEUS_API_KEY || 'demo_key';
const API_SECRET = __ENV.AMADEUS_API_SECRET || 'demo_secret';
const ENVIRONMENT = __ENV.AMADEUS_ENVIRONMENT || 'test';

// Base URL for the Amadeus API
const BASE_URL = ENVIRONMENT === 'live' ? 'https://api.amadeus.com' : 'https://test.api.amadeus.com';

// Global variable to cache token
let cachedToken = null;
let tokenExpiry = 0;

// Sample search criteria for flight offers
const searchCriteria = {
    currencyCode: 'USD',
    originDestinations: [
        {
            id: '1',
            originLocationCode: 'JFK',
            destinationLocationCode: 'LAX',
            departureDateTimeRange: {
                date: '2025-12-01'
            }
        }
    ],
    travelers: [
        {
            id: '1',
            travelerType: 'ADULT'
        }
    ],
    sources: ['GDS'],
    searchCriteria: {
        maxFlightOffers: 10
    }
};

// Function to get access token
function getAccessToken() {
    const now = new Date().getTime() / 1000; // Current time in seconds

    // Return cached token if still valid (with 60 second buffer)
    if (cachedToken && tokenExpiry > now + 60) {
        return cachedToken;
    }

    const tokenUrl = `${BASE_URL}/v1/security/oauth2/token`;
    const payload = {
        grant_type: 'client_credentials',
        client_id: API_KEY,
        client_secret: API_SECRET,
    };

    const response = http.post(tokenUrl, payload, {
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
    });

    check(response, {
        'token request successful': (r) => r.status === 200,
    });

    if (response.status === 200) {
        const data = JSON.parse(response.body);
        cachedToken = data.access_token;
        tokenExpiry = now + (data.expires_in || 1799); // Default 30 minutes
        return cachedToken;
    } else {
        console.error('Failed to get access token:', response.body);
        return null;
    }
}

export default function () {
    const token = getAccessToken();

    if (!token) {
        console.error('No access token available');
        return;
    }

    const params = {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/vnd.amadeus+json',
        },
    };

    const response = http.post(
        `${BASE_URL}/v2/shopping/flight-offers`,
        JSON.stringify(searchCriteria),
        params
    );

    // Check response
    check(response, {
        'status is 200': (r) => r.status === 200,
        'response time < 1000ms': (r) => r.timings.duration < 1000,
        'has flight offers': (r) => {
            try {
                const data = JSON.parse(r.body);
                return data && data.data && data.data.length > 0;
            } catch (e) {
                return false;
            }
        },
    });

    sleep(1); // Wait 1 second between iterations
}