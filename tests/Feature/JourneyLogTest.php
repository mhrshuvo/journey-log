<?php

namespace mhrshuvo\JourneyLog\Tests\Feature;

use Illuminate\Support\Facades\File;
use mhrshuvo\JourneyLog\Tests\TestCase;

class JourneyLogTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up generated log files after each test
        $logDir = storage_path('logs/journeys');
        if (File::isDirectory($logDir)) {
            File::deleteDirectory($logDir);
        }
        parent::tearDown();
    }

    public function test_config_is_loaded(): void
    {
        $this->assertEquals('journeys', config('journeylog.storage_path'));
    }

    public function test_journey_log_helper_creates_log_file(): void
    {
        // Simulate a session with journey_id
        session(['journey_id' => 'test-session-123']);

        journey_log('search', 'User searched for product', ['query' => 'laptop']);

        $filePath = storage_path('logs/journeys/search/journey-test-session-123.json');

        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('User searched for product', $content);
        $this->assertStringContainsString('laptop', $content);
    }

    public function test_journey_log_creates_subfolder(): void
    {
        session(['journey_id' => 'folder-test']);

        journey_log('add-to-cart', 'Item added to cart', ['item_id' => 42]);

        $dir = storage_path('logs/journeys/add-to-cart');
        $this->assertDirectoryExists($dir);
        $this->assertFileExists($dir.'/journey-folder-test.json');
    }

    public function test_journey_log_without_folder(): void
    {
        // When no folder is specified, log goes to base directory
        session(['journey_id' => 'no-folder-test']);

        journey_log(null, 'Generic log entry', ['action' => 'pageview']);

        $filePath = storage_path('logs/journeys/journey-no-folder-test.json');
        $this->assertFileExists($filePath);
    }

    public function test_journey_log_guest_fallback(): void
    {
        // No session journey_id set — should fall back to 'guest'
        journey_log('checkout', 'Guest checkout attempt', ['total' => 99.99]);

        $filePath = storage_path('logs/journeys/checkout/journey-guest.json');
        $this->assertFileExists($filePath);
    }

    public function test_middleware_sets_journey_id_in_session(): void
    {
        $this->app['router']->middleware(['web', 'journey-log'])->get('/test-route', function () {
            return response()->json(['journey_id' => session('journey_id')]);
        });

        $response = $this->get('/test-route');

        $response->assertStatus(200);

        $journeyId = $response->json('journey_id');
        $this->assertNotNull($journeyId);
        $this->assertNotEmpty($journeyId);
    }

    public function test_middleware_reuses_existing_journey_id(): void
    {
        $this->app['router']->middleware(['web', 'journey-log'])->get('/test-route', function () {
            return response()->json(['journey_id' => session('journey_id')]);
        });

        // First request — sets journey_id
        $response1 = $this->get('/test-route');
        $id1 = $response1->json('journey_id');

        // Second request with same session — should reuse
        $response2 = $this->withSession(['journey_id' => $id1])->get('/test-route');
        $id2 = $response2->json('journey_id');

        $this->assertEquals($id1, $id2);
    }

    public function test_middleware_accepts_header_journey_id(): void
    {
        $this->app['router']->middleware(['web', 'journey-log'])->get('/test-route', function () {
            return response()->json(['journey_id' => session('journey_id')]);
        });

        $response = $this->withHeaders(['X-Journey-ID' => 'header-id-abc'])
            ->get('/test-route');

        $response->assertStatus(200);
        // The middleware should pick up the header value when no session value exists
        $this->assertEquals('header-id-abc', $response->json('journey_id'));
    }

    public function test_middleware_prioritizes_header_over_session(): void
    {
        $this->app['router']->middleware(['web', 'journey-log'])->get('/test-route', function () {
            return response()->json(['journey_id' => session('journey_id')]);
        });

        // Set different values in session and header - header should take priority
        $response = $this->withSession(['journey_id' => 'session-id-123'])
            ->withHeaders(['X-Journey-ID' => 'header-id-456'])
            ->get('/test-route');

        $response->assertStatus(200);
        // Header should take priority over session
        $this->assertEquals('header-id-456', $response->json('journey_id'));
    }

    public function test_mask_sensitive_data_function(): void
    {
        $data = [
            'username' => 'testuser',
            'password' => 'secret123',
            'api_key' => 'abc123xyz',
            'safe_field' => 'public_info',
            'nested' => [
                'card_number' => '1234-5678-9012-3456',
                'public_data' => 'visible'
            ]
        ];

        $maskedData = mask_sensitive_data($data, ['password', 'api_key', 'card_number']);

        $this->assertEquals('testuser', $maskedData['username']);
        $this->assertEquals('********', $maskedData['password']);
        $this->assertEquals('********', $maskedData['api_key']);
        $this->assertEquals('public_info', $maskedData['safe_field']);
        $this->assertEquals('********', $maskedData['nested']['card_number']);
        $this->assertEquals('visible', $maskedData['nested']['public_data']);
    }

    public function test_mask_sensitive_data_case_insensitive(): void
    {
        $data = [
            'PASSWORD' => 'secret123',
            'Api_Key' => 'abc123xyz',
            'card_NUMBER' => '1234567890'
        ];

        $maskedData = mask_sensitive_data($data, ['password', 'api_key', 'card_number']);

        $this->assertEquals('********', $maskedData['PASSWORD']);
        $this->assertEquals('********', $maskedData['Api_Key']);
        $this->assertEquals('********', $maskedData['card_NUMBER']);
    }

    public function test_journey_log_masks_sensitive_data(): void
    {
        // Override config for this test
        config(['journeylog.mask_fields' => ['password', 'cvv']]);

        session(['journey_id' => 'masking-test']);

        journey_log('payment', 'Payment attempt', [
            'user_id' => 123,
            'password' => 'secretpass',
            'cvv' => '123',
            'amount' => 99.99
        ]);

        $filePath = storage_path('logs/journeys/payment/journey-masking-test.json');
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $decoded = json_decode($content, true);
        $entry = $decoded[0];

        // Sensitive data should be masked
        $this->assertEquals('********', $entry['context']['password']);
        $this->assertEquals('********', $entry['context']['cvv']);
        
        // Non-sensitive data should remain
        $this->assertEquals(123, $entry['context']['user_id']);
        $this->assertEquals(99.99, $entry['context']['amount']);
    }

    public function test_journey_log_masks_nested_sensitive_data(): void
    {
        config(['journeylog.mask_fields' => ['api_key', 'secret']]);

        session(['journey_id' => 'nested-masking-test']);

        journey_log('api', 'API call', [
            'endpoint' => '/users',
            'auth' => [
                'api_key' => 'super-secret-key',
                'user_id' => 456
            ],
            'config' => [
                'settings' => [
                    'secret' => 'deep-nested-secret'
                ]
            ]
        ]);

        $filePath = storage_path('logs/journeys/api/journey-nested-masking-test.json');
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $decoded = json_decode($content, true);
        $entry = $decoded[0];

        // Check nested masking
        $this->assertEquals('********', $entry['context']['auth']['api_key']);
        $this->assertEquals('********', $entry['context']['config']['settings']['secret']);
        
        // Non-sensitive nested data should remain
        $this->assertEquals(456, $entry['context']['auth']['user_id']);
        $this->assertEquals('/users', $entry['context']['endpoint']);
    }

    public function test_journey_log_with_empty_mask_fields(): void
    {
        config(['journeylog.mask_fields' => []]);

        session(['journey_id' => 'no-masking-test']);

        journey_log('test', 'No masking test', [
            'password' => 'should-not-be-masked',
            'api_key' => 'should-not-be-masked'
        ]);

        $filePath = storage_path('logs/journeys/test/journey-no-masking-test.json');
        $content = file_get_contents($filePath);
        $decoded = json_decode($content, true);
        $entry = $decoded[0];

        // No masking should occur when mask_fields is empty
        $this->assertEquals('should-not-be-masked', $entry['context']['password']);
        $this->assertEquals('should-not-be-masked', $entry['context']['api_key']);
    }

    public function test_log_output_is_valid_json(): void
    {
        session(['journey_id' => 'json-test']);

        journey_log('search', 'Valid JSON test', ['key' => 'value']);

        $filePath = storage_path('logs/journeys/search/journey-json-test.json');
        $content = file_get_contents($filePath);

        // The helper writes entries as a JSON array
        $decoded = json_decode($content, true);
        $this->assertNotNull($decoded, 'Content should be valid JSON');
        $this->assertIsArray($decoded, 'Content should be a JSON array');

        // Check the structure of the first entry
        $this->assertCount(1, $decoded);
        $entry = $decoded[0];
        $this->assertEquals('Valid JSON test', $entry['message']);
        $this->assertEquals('journey', $entry['channel']);
        $this->assertEquals('INFO', $entry['level_name']);
        $this->assertEquals(['key' => 'value'], $entry['context']);
    }
}
