#!/bin/bash

# JourneyLog Package Integration Script
# This script helps integrate the JourneyLog package into your Laravel test project

echo "🚀 JourneyLog Package Integration Script"
echo "======================================="

# Get the Laravel project path
read -p "Enter the path to your Laravel test project (or press Enter for ~/Desktop/work/journeylog-test): " LARAVEL_PATH
LARAVEL_PATH=${LARAVEL_PATH:-~/Desktop/work/journeylog-test}

# Expand the tilde
LARAVEL_PATH=$(eval echo $LARAVEL_PATH)

echo "Laravel project path: $LARAVEL_PATH"

# Check if the Laravel project directory exists
if [ ! -d "$LARAVEL_PATH" ]; then
    echo "❌ Laravel project directory not found: $LARAVEL_PATH"
    echo "Please create your Laravel project first:"
    echo "laravel new journeylog-test"
    echo "cd journeylog-test && php artisan serve"
    exit 1
fi

echo "✅ Laravel project directory found"

# Check if it's actually a Laravel project
if [ ! -f "$LARAVEL_PATH/artisan" ]; then
    echo "❌ This doesn't appear to be a Laravel project (no artisan file found)"
    exit 1
fi

echo "✅ Laravel project validated"

# Get current JourneyLog package path
PACKAGE_PATH=$(pwd)
echo "JourneyLog package path: $PACKAGE_PATH"

# Navigate to Laravel project
cd "$LARAVEL_PATH"

echo "📦 Setting up local composer repository..."

# Add local repository to composer.json
composer config repositories.journey-log path "$PACKAGE_PATH"

echo "📥 Installing JourneyLog package..."

# Install the package
composer require mhrshuvo/journey-log:dev-main

echo "🔧 Publishing configuration..."

# Publish config
php artisan vendor:publish --tag=journeylog-config

echo "🔧 Adding environment variables..."

# Add environment variables if they don't exist
ENV_FILE="$LARAVEL_PATH/.env"

if ! grep -q "JOURNEY_LOG_STORAGE_PATH" "$ENV_FILE"; then
    echo "" >> "$ENV_FILE"
    echo "# JourneyLog Configuration" >> "$ENV_FILE"
    echo "JOURNEY_LOG_STORAGE_PATH=journeys" >> "$ENV_FILE"
    echo "JOURNEY_LOG_HEADER=X-Journey-ID" >> "$ENV_FILE"
    echo "JOURNEY_LOG_SESSION_KEY=journey_id" >> "$ENV_FILE"
    echo "JOURNEY_LOG_SUMMARY_ENABLED=true" >> "$ENV_FILE"
    echo "JOURNEY_LOG_SUMMARY_ROUTE_PREFIX=journey-log" >> "$ENV_FILE"
    echo "JOURNEY_LOG_AUTO_CLEANUP_ENABLED=true" >> "$ENV_FILE"
    echo "JOURNEY_LOG_CLEANUP_RETENTION_HOURS=24" >> "$ENV_FILE"
    echo "✅ Environment variables added"
else
    echo "✅ Environment variables already exist"
fi

echo "🛣️ Setting up test routes..."

# Create test routes file
cat > routes/journey-test.php << 'EOF'
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'journey-log'])->group(function () {
    Route::get('/journey-test', function () {
        journey_log('test_visit', 'User visited test page', ['test' => true]);
        return response()->json([
            'message' => 'JourneyLog test successful!',
            'journey_id' => session('journey_id'),
            'timestamp' => now()
        ]);
    });

    Route::post('/journey-test/action', function () {
        journey_log('test_action', 'User performed test action', [
            'action_type' => 'button_click',
            'data' => request()->all()
        ]);
        return response()->json(['status' => 'action logged']);
    });

    Route::get('/journey-test/search', function () {
        journey_log('test_search', 'User performed test search', [
            'query' => request('q', ''),
            'results' => rand(1, 50)
        ]);
        return response()->json(['search_results' => 'test results']);
    });
});
EOF

# Include the test routes
if ! grep -q "journey-test.php" routes/web.php; then
    echo "require __DIR__.'/journey-test.php';" >> routes/web.php
    echo "✅ Test routes added"
else
    echo "✅ Test routes already included"
fi

echo "🆔 Creating test controller..."

# Create a proper test controller
mkdir -p app/Http/Controllers
cat > app/Http/Controllers/JourneyTestController.php << 'EOF'
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class JourneyTestController extends Controller
{
    public function index(Request $request)
    {
        journey_log('homepage_visit', 'User visited homepage', [
            'source' => $request->get('source', 'direct'),
            'timestamp' => now()
        ]);

        return response()->json([
            'message' => 'Welcome to JourneyLog test!',
            'journey_id' => session('journey_id'),
            'next_steps' => [
                'Visit /journey-test/search?q=laptop to test search logging',
                'Visit /journey-log to see the summary dashboard',
                'Check storage/logs/journeys/ for log files'
            ]
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        journey_log('search_performed', 'User performed search', [
            'query' => $query,
            'filters' => $request->get('filters', []),
            'result_count' => rand(1, 100),
            'search_type' => 'product_search'
        ]);

        return response()->json([
            'query' => $query,
            'results' => ['Mock Product 1', 'Mock Product 2'],
            'journey_id' => session('journey_id')
        ]);
    }

    public function purchase(Request $request)
    {
        journey_log('purchase_completed', 'User completed purchase', [
            'product_id' => $request->get('product_id', 1),
            'amount' => $request->get('amount', 99.99),
            'payment_method' => $request->get('payment_method', 'credit_card'),
            'success' => true
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase completed successfully',
            'journey_id' => session('journey_id')
        ]);
    }
}
EOF

# Update web routes to use the controller
cat > routes/web.php << 'EOF'
<?php

use App\Http\Controllers\JourneyTestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['journey-log'])->group(function () {
    Route::get('/', [JourneyTestController::class, 'index']);
    Route::get('/search', [JourneyTestController::class, 'search']);
    Route::post('/purchase', [JourneyTestController::class, 'purchase']);
});

require __DIR__.'/journey-test.php';
EOF

echo "🎉 Integration completed successfully!"
echo ""
echo "🧪 Testing Instructions:"
echo "========================"
echo "1. Make sure your Laravel server is running:"
echo "   cd $LARAVEL_PATH"
echo "   php artisan serve"
echo ""
echo "2. Test the endpoints:"
echo "   📍 http://localhost:8000/ - Homepage with journey logging"
echo "   📍 http://localhost:8000/search?q=laptop - Search functionality" 
echo "   📍 http://localhost:8000/journey-log - Summary dashboard"
echo "   📍 http://localhost:8000/journey-test - Simple test page"
echo ""
echo "3. Test with curl:"
echo "   curl http://localhost:8000/"
echo "   curl http://localhost:8000/search?q=laptop"
echo "   curl -X POST http://localhost:8000/purchase -d 'product_id=1&amount=99.99'"
echo ""
echo "4. Check the logs:"
echo "   ls -la $LARAVEL_PATH/storage/logs/journeys/"
echo ""
echo "5. Test with custom journey ID:"
echo "   curl -H 'X-Journey-ID: test-123' http://localhost:8000/"
echo ""
echo "✅ Ready for testing! Visit any endpoint and check storage/logs/journeys/ for log files."