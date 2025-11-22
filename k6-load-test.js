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

// Base URL for the Amadeus API (use test environment for load testing)
const BASE_URL = 'https://test.api.amadeus.com';

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

export default function () {
    // In a real scenario, you'd obtain a valid access token
    // For this example, we'll simulate the request structure
    // Note: This is a mock - actual token retrieval would be needed

    const token = getAccessToken(); // Implement token retrieval

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
            const data = JSON.parse(r.body);
            return data && data.data && data.data.length > 0;
        },
    });

    sleep(1); // Wait 1 second between iterations
}

// Mock function to get access token
// In production, implement proper OAuth2 flow
function getAccessToken() {
    // This should be replaced with actual token retrieval logic
    // For load testing, you might need to cache tokens or use a service account
    return 'mock_access_token';
}