# JourneyLog Manual Testing Guide

## Prerequisites
- Laravel test project running on port 8000
- JourneyLog package source available locally

## Installation Methods

### Method 1: Local Development (Recommended for testing)
```bash
# In your Laravel test project directory
composer config repositories.journey-log path "../JourneyLog"
composer require mhrshuvo/journey-log:dev-main
```

### Method 2: Direct Installation (After publishing)
```bash
composer require mhrshuvo/journey-log
```

## Configuration Setup

### 1. Publish Config (Optional)
```bash
php artisan vendor:publish --tag=journeylog-config
```

### 2. Environment Variables
Add to your `.env` file:
```env
JOURNEY_LOG_STORAGE_PATH=journeys
JOURNEY_LOG_HEADER=X-Journey-ID
JOURNEY_LOG_SESSION_KEY=journey_id
JOURNEY_LOG_SUMMARY_ENABLED=true
JOURNEY_LOG_SUMMARY_ROUTE_PREFIX=journey-log
JOURNEY_LOG_AUTO_CLEANUP_ENABLED=true
JOURNEY_LOG_CLEANUP_RETENTION_HOURS=24
```

### 3. Middleware Registration
Add to your routes or middleware groups:

**Option A: Global Middleware**
In `bootstrap/app.php` (Laravel 11) or `app/Http/Kernel.php` (Laravel 10):
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: ['journey-log']);
    $middleware->api(append: ['journey-log']);
})

// Or for Laravel 10:
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        'journey-log',
    ],
    'api' => [
        // ... other middleware
        'journey-log',
    ],
];
```

**Option B: Route-specific**
```php
Route::middleware(['journey-log'])->group(function () {
    Route::get('/', [HomeController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/search', [SearchController::class, 'search']);
});
```

## Manual Testing Scenarios

### Test 1: Basic Journey Logging
1. Visit `http://localhost:8000` 
2. Navigate through various pages
3. Check `storage/logs/journeys/` for generated log files
4. Verify JSON structure and timestamps

### Test 2: Custom Journey Logging
Create a test controller:
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function search(Request $request)
    {
        journey_log('search', 'User performed search', [
            'query' => $request->get('q', ''),
            'filters' => $request->get('filters', []),
            'results_count' => rand(1, 100)
        ]);
        
        return response()->json(['message' => 'Search completed']);
    }
    
    public function purchase(Request $request)
    {
        journey_log('purchase', 'User completed purchase', [
            'product_id' => $request->get('product_id'),
            'amount' => $request->get('amount'),
            'payment_method' => 'credit_card'
        ]);
        
        return response()->json(['message' => 'Purchase completed']);
    }
}
```

### Test 3: Journey ID Persistence
1. Open browser dev tools
2. Make a request with custom header: `X-Journey-ID: test-journey-123`
3. Navigate to other pages
4. Verify the same journey ID is used across requests
5. Check that session maintains the journey ID

### Test 4: API Testing
Test with curl:
```bash
# Test with custom journey ID
curl -H "X-Journey-ID: api-test-456" http://localhost:8000/api/test-endpoint

# Test without journey ID (should generate one)
curl http://localhost:8000/api/test-endpoint

# Test session persistence in web routes
curl -b cookies.txt -c cookies.txt http://localhost:8000/test-page
curl -b cookies.txt -c cookies.txt http://localhost:8000/another-page
```

### Test 5: Summary Dashboard (if enabled)
1. Set `JOURNEY_LOG_SUMMARY_ENABLED=true`
2. Generate some journey logs
3. Visit `http://localhost:8000/journey-log`
4. Verify the timeline display shows your journeys

### Test 6: Cleanup Functionality
1. Set `JOURNEY_LOG_CLEANUP_RETENTION_HOURS=0`
2. Run: `php artisan schedule:run`
3. Or manually: `php artisan journey:cleanup`
4. Verify old log files are removed

## Expected Results

### Log File Structure
```json
{
    "timestamp": "2026-03-01T10:30:45Z",
    "action": "page_visit",
    "description": "User visited homepage",
    "url": "http://localhost:8000",
    "method": "GET",
    "ip": "127.0.0.1",
    "user_agent": "Mozilla/5.0...",
    "session_id": "abc123...",
    "data": {
        "custom": "data"
    }
}
```

### File Organization
```
storage/logs/journeys/
├── 2026/03/01/
│   ├── test-journey-123_20260301_103045.json
│   ├── test-journey-123_20260301_103046.json
│   └── api-test-456_20260301_103050.json
```

## Troubleshooting

### Issue 1: Permission Errors
```bash
chmod -R 775 storage/logs/
```

### Issue 2: Directory Not Created
- Check web server permissions
- Verify storage directory is writable

### Issue 3: Journey ID Not Persisting
- Check session configuration
- Verify middleware is applied correctly
- Test with browser session vs API calls