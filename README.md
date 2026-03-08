# JourneyLog Laravel Package

JourneyLog is the "Black Box" for your Laravel application. Designed specifically for high-stakes industries like Flight, Bus, and Hotel Reservations or Dropshipping, it creates a secure, organized, and session-isolated audit trail of every user interaction and 3rd-party API response.

**Stop digging through massive, messy log files. Start seeing the full story of every booking.**

## 🌟 Why JourneyLog?

In reservation systems, 3rd-party APIs (GDS, Wholesalers, Suppliers) change prices and availability in milliseconds. When a customer claims a price mismatch or a booking fails, you need evidence.

- **Session Isolation**: Every visitor gets their own unique JSON log file. No mixed data.
- **Folder-Wise Logic**: Automatically group logs into categories like `/search`, `/api-responses`, or `/bookings`.
- **Hybrid Ready**: Works seamlessly with Web (Sessions) and Stateless APIs (Headers).
- **Security First**: Built-in Global Masking recursively hides sensitive PII (passwords, API keys, tokens).

## Installation

```bash
composer require mhrshuvo/journey-log
```

## Server Setup & Permissions

### Required Permissions

Ensure your web server user has proper permissions:

```bash
# Set storage directory permissions
chmod -R 775 storage/logs
chown -R www-data:www-data storage/logs  # or your web server user

# For specific journey logs directory (after first use)
chmod -R 775 storage/logs/journeys
chown -R www-data:www-data storage/logs/journeys
```

### Common Server Issues & Solutions

#### 1. **Permission Denied Errors**
If you see permission errors in logs:

```bash
# Fix storage permissions
sudo chmod -R 775 storage/
sudo chown -R www-data:www-data storage/

# Set proper umask for web server
# Add to your web server config: umask 002
```

#### 2. **SELinux Issues** (RHEL/CentOS)
```bash
# Allow web server to write to storage
sudo setsebool -P httpd_unified 1
sudo chcon -R -t httpd_exec_t storage/
```

#### 3. **Shared Hosting**
- Ensure your hosting provider allows directory creation in storage/
- Some shared hosts require 755 permissions instead of 775
- Contact support if you can't create directories

#### 4. **Docker Container Issues**
```dockerfile
# In your Dockerfile, ensure proper permissions
RUN chown -R www-data:www-data /var/www/storage
RUN chmod -R 775 /var/www/storage
```

### Automatic Cleanup Scheduler

The package includes automatic cleanup of old files. Ensure Laravel's scheduler is running:

```bash
# Add to crontab (crontab -e)
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

#### Manual Cleanup
```bash
# Test what would be cleaned (dry run)
php artisan journey-log:cleanup --dry-run

# Run cleanup manually
php artisan journey-log:cleanup

# Custom retention period (2 hours)
php artisan journey-log:cleanup --hours=2
```

## Configuration

Publish the config file:
```bash
php artisan vendor:publish --tag=journeylog-config
```

### Environment Variables

Add to your `.env` file:
```env
JOURNEY_LOG_STORAGE_PATH=journeys
JOURNEY_LOG_HEADER=X-Journey-ID  
JOURNEY_LOG_SESSION_KEY=journey_id
JOURNEY_LOG_AUTO_CLEANUP_ENABLED=true
JOURNEY_LOG_CLEANUP_RETENTION_HOURS=1
```

### Data Masking

Protect sensitive information by automatically masking specified field names in your logs. The package includes built-in protection for common sensitive fields and allows customization.

#### Default Protected Fields
The following fields are automatically masked by default:
- `password`
- `password_confirmation` 
- `cvv`
- `card_number`
- `api_key`
- `auth_token`
- `access_token`
- `secret`

#### Usage Example
```php
// Original data with sensitive information
journey_log('payment', 'Processing payment', [
    'user_id' => 123,
    'amount' => 99.99,
    'card_number' => '1234-5678-9012-3456',  // Will be masked
    'cvv' => '123',                          // Will be masked
    'api_key' => 'sk_live_abc123xyz',        // Will be masked
    'transaction_id' => 'txn_456789'         // Will remain visible
]);
```

#### Generated Log Output
```json
[
  {
    "message": "Processing payment",
    "context": {
      "user_id": 123,
      "amount": 99.99,
      "card_number": "********",
      "cvv": "********", 
      "api_key": "********",
      "transaction_id": "txn_456789"
    },
    "level": 200,
    "level_name": "INFO",
    "channel": "journey",
    "datetime": "2026-03-08T10:30:15+00:00",
    "extra": []
  }
]
```

#### Nested Data Masking
Masking works recursively through nested arrays:
```php
journey_log('auth', 'User login attempt', [
    'user_data' => [
        'email' => 'user@example.com',
        'credentials' => [
            'password' => 'supersecret123',     // Will be masked
            'remember_token' => 'abc123'
        ]
    ],
    'request_info' => [
        'ip' => '192.168.1.1',
        'headers' => [
            'authorization' => 'Bearer secret'  // Will remain (not in mask_fields)
        ]
    ]
]);
```

#### Customizing Masked Fields
You can customize which fields are masked by modifying the configuration:

**config/journeylog.php:**
```php
'mask_fields' => [
    'password',
    'password_confirmation',
    'cvv',
    'card_number',
    'api_key',
    'auth_token',
    'access_token', 
    'secret',
    'ssn',              // Add custom sensitive field
    'bank_account',     // Add custom sensitive field
]
```

#### Case-Insensitive Matching
Field matching is case-insensitive, so all of these would be masked:
- `password`, `PASSWORD`, `Password`, `PaSsWoRd`
- `api_key`, `API_KEY`, `Api_Key`

## Usage

### Basic Logging
```php
// Log with folder organization
journey_log('checkout', 'User started checkout', ['total' => 99.99]);

// Log to root directory  
journey_log(null, 'Page view', ['url' => '/products']);
```

### Example Use Cases

#### **Example 1: Web Application User Journey**

**Setup Route:**
```php
// routes/web.php
Route::middleware(['web', 'journey-log'])->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::get('/checkout', [CheckoutController::class, 'index']);
    Route::post('/checkout/complete', [CheckoutController::class, 'complete']);
});
```

**Controller Implementation:**
```php
class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Log the user's product browsing
        journey_log('search', 'User viewed products page', [
            'category' => $request->get('category'),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip()
        ]);
        
        return view('products.index');
    }
}

class CartController extends Controller  
{
    public function add(Request $request)
    {
        // Log add to cart action
        journey_log('cart', 'Product added to cart', [
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'price' => $request->price
        ]);
        
        return redirect()->back();
    }
}

class CheckoutController extends Controller
{
    public function complete(Request $request)
    {
        // Process order...
        
        // Log successful checkout
        journey_log('checkout', 'Order completed successfully', [
            'order_id' => $order->id,
            'total_amount' => $order->total,
            'payment_method' => $request->payment_method,
            'items_count' => $order->items->count()
        ]);
        
        return view('checkout.success');
    }
}
```

**Request Flow:**
```
1. User visits /products
   → Middleware generates: journey_id = "abc123def456"  
   → Stored in session: journey_id = "abc123def456"
   → Log created: /storage/logs/journeys/search/journey-abc123def456.json

2. User adds product to cart (/cart/add)
   → Middleware reads from session: journey_id = "abc123def456"
   → Log appended to: /storage/logs/journeys/cart/journey-abc123def456.json

3. User completes checkout (/checkout/complete)  
   → Same journey_id from session
   → Log appended to: /storage/logs/journeys/checkout/journey-abc123def456.json
```

#### **Example 2: API Application with Mobile App**

**Setup Route:**
```php
// routes/api.php
Route::middleware(['api', 'journey-log'])->group(function () {
    Route::get('/products', [Api\ProductController::class, 'index']);
    Route::post('/cart', [Api\CartController::class, 'store']);
    Route::post('/orders', [Api\OrderController::class, 'store']);
});
```

**Mobile App Implementation:**
```javascript
// Mobile app JavaScript
const journeyId = 'mobile-user-789xyz';

// Step 1: Browse products
const products = await fetch('/api/products', {
    headers: {
        'X-Journey-ID': journeyId,
        'Authorization': 'Bearer ' + authToken,
        'Content-Type': 'application/json'
    }
});

// Step 2: Add to cart
const cartResponse = await fetch('/api/cart', {
    method: 'POST',
    headers: {
        'X-Journey-ID': journeyId,
        'Authorization': 'Bearer ' + authToken,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        product_id: 123,
        quantity: 2
    })
});

// Step 3: Place order
const orderResponse = await fetch('/api/orders', {
    method: 'POST', 
    headers: {
        'X-Journey-ID': journeyId,
        'Authorization': 'Bearer ' + authToken,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        payment_method: 'credit_card'
    })
});
```

**API Controller Implementation:**
```php
class Api\ProductController extends Controller
{
    public function index(Request $request)
    {
        // Log API product request
        journey_log('api-search', 'Mobile app browsed products', [
            'platform' => 'mobile',
            'app_version' => $request->header('App-Version'),
            'user_id' => auth()->id(),
            'filters' => $request->all()
        ]);
        
        return ProductResource::collection(Product::paginate());
    }
}

class Api\CartController extends Controller
{
    public function store(Request $request)
    {
        // Log add to cart via API
        journey_log('api-cart', 'Mobile user added item to cart', [
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'user_id' => auth()->id(),
            'platform' => 'mobile'
        ]);
        
        return response()->json(['success' => true]);
    }
}
```

**Request Flow:**
```
1. Mobile app calls /api/products
   → Header: X-Journey-ID = "mobile-user-789xyz"
   → Middleware reads header (no session in API)
   → Log created: /storage/logs/journeys/api-search/journey-mobile-user-789xyz.json

2. Mobile app calls /api/cart  
   → Same X-Journey-ID header
   → Log appended to: /storage/logs/journeys/api-cart/journey-mobile-user-789xyz.json

3. Mobile app calls /api/orders
   → Same X-Journey-ID header  
   → Log appended to: /storage/logs/journeys/api-orders/journey-mobile-user-789xyz.json
```

### Generated Log Files

**Example: `/storage/logs/journeys/search/journey-abc123def456.json`**
```json
[
  {
    "message": "User viewed products page",
    "context": {
      "category": "electronics",
      "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
      "ip": "192.168.1.100"
    },
    "level": 200,
    "level_name": "INFO",
    "channel": "journey",
    "datetime": "2026-02-28T10:30:15+00:00",
    "extra": []
  }
]
```

**Example: `/storage/logs/journeys/cart/journey-abc123def456.json`**
```json
[
  {
    "message": "Product added to cart",
    "context": {
      "product_id": 456,
      "quantity": 2,
      "price": "29.99"
    },
    "level": 200,
    "level_name": "INFO", 
    "channel": "journey",
    "datetime": "2026-02-28T10:32:45+00:00",
    "extra": []
  },
  {
    "message": "Product added to cart",
    "context": {
      "product_id": 789,
      "quantity": 1,
      "price": "15.50"
    },
    "level": 200,
    "level_name": "INFO",
    "channel": "journey", 
    "datetime": "2026-02-28T10:35:12+00:00",
    "extra": []
  }
]
```

**Example: `/storage/logs/journeys/checkout/journey-abc123def456.json`**
```json
[
  {
    "message": "Order completed successfully",
    "context": {
      "order_id": 12345,
      "total_amount": "75.48",
      "payment_method": "credit_card",
      "items_count": 3
    },
    "level": 200,
    "level_name": "INFO",
    "channel": "journey",
    "datetime": "2026-02-28T10:38:30+00:00",
    "extra": []
  }
]
```

### Middleware Setup
```php
// For Web routes - uses sessions + headers
Route::middleware(['web', 'journey-log'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index']);
    // Other web routes
});

// For API routes - uses headers
Route::middleware(['api', 'journey-log'])->group(function () {
    Route::get('/api/products', [ProductController::class, 'index']);
    // Other API routes  
});
```

### Journey ID Resolution Priority
The middleware resolves journey IDs in this order for both web and API:
1. **Header**: `X-Journey-ID` (works for both web and API)
2. **Session**: `journey_id` (web routes only)
3. **Generated**: Random 12-character string (fallback)

### API Usage
For API clients, include the journey ID in headers:
```javascript
// JavaScript/Fetch
fetch('/api/products', {
    headers: {
        'X-Journey-ID': 'user-abc123',
        'Content-Type': 'application/json'
    }
});

// cURL
curl -H "X-Journey-ID: user-abc123" https://yourapp.com/api/products
```

### File Structure
Files are organized as:
```
storage/logs/journeys/
├── checkout/
│   ├── journey-abc123.json
│   └── journey-def456.json
├── search/
│   └── journey-abc123.json
└── journey-guest.json
```

## Troubleshooting

### Check Permissions
```bash
# Verify storage is writable
ls -la storage/logs/

# Test file creation
touch storage/logs/test.txt && rm storage/logs/test.txt
```

### Enable Fallback Logging
The package automatically falls back to Laravel's default logging if it can't write journey files. Check your application logs:

```bash
tail -f storage/logs/laravel.log | grep JourneyLog
```

### Debug Mode
Enable debug mode to see permission issues:
```bash
# Run cleanup with verbose output
php artisan journey-log:cleanup --dry-run -v
```

## Production Deployment Checklist

- [ ] Storage directory exists and is writable
- [ ] Web server user owns storage directory
- [ ] Laravel scheduler is configured in cron
- [ ] SELinux contexts set (if applicable)  
- [ ] Cleanup retention period configured appropriately
- [ ] Fallback logging is working (check Laravel logs)

## Support

If you encounter permission issues, the package will automatically log errors to Laravel's default log system for debugging.