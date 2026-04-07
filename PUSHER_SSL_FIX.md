# Pusher SSL Certificate Fix

## Problem
When sending location updates to Pusher from Windows local development environment, the backend was returning HTTP 500 with error:

```
cURL error 60: SSL certificate problem: unable to get local issuer certificate
```

## Root Cause
Pusher uses HTTPS (api-ap1.pusher.com) which requires SSL certificate verification. On Windows local development machines, the SSL certificate chain might not be complete, causing cURL to fail verification.

## Solution
Disable SSL verification in Pusher client options for local development environment.

### Changes Made

**File: `config/broadcasting.php`**

Added to `pusher` connection's `client_options`:

```php
'client_options' => [
    // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
    // Disable SSL verification for local development on Windows
    'verify' => env('APP_ENV') === 'production' ? true : false,
],
```

**Why this works:**
- `verify` option tells Guzzle HTTP client to skip SSL certificate verification
- Only disabled when `APP_ENV` is NOT production
- Production deployments will have `verify: true` to ensure security
- Allows LocationUpdated events to broadcast successfully

### Verification

Test location endpoint:
```bash
curl -X POST http://localhost:8000/api/gps/track \
  -H "X-Tracking-Token: 5a29b4f206d17e021b57c51cc9c7d8f58b9504c8d36999ed992a40a3939a7b18" \
  -H "Content-Type: application/json" \
  -d '{
    "latitude": -6.2,
    "longitude": 106.8,
    "speed": 50.5,
    "accuracy": 10.2
  }'
```

Expected response:
```json
{
  "success": true,
  "message": "Location updated successfully",
  "data": {
    "vehicle_id": 4,
    "vehicle_number": "B 1234 XYV",
    "latitude": "-6.20000000",
    "longitude": "106.80000000",
    "updated_at": "2026-04-07T10:26:47.000000Z"
  }
}
```

### Next Steps

1. ✅ Backend can now broadcast to Pusher
2. Check Pusher Dashboard → Events should show messages being received
3. Svelte frontend should update location map in real-time without refresh
4. Test with Python GPS simulator or live tracking

### For Production

Ensure `.env` has `APP_ENV=production` so SSL verification is enabled for security.
