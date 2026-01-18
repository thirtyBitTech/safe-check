<?php

namespace Thirtybittech\SafeCheck\Tests;

use Illuminate\Support\Facades\Http;
use Thirtybittech\SafeCheck\Services\DependencyScanner;

class DependencyScannerTest extends TestCase
{
    /** @test */
   public function test_it_returns_expected_scan_structure()
{
    Http::fake([
        'https://api.osv.dev/*' => Http::response([
            'results' => [],
        ], 200),
    ]);

    $scanner = app(\Thirtybittech\SafeCheck\Services\DependencyScanner::class);

    $result = $scanner->run();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('scanned_at', $result);
    $this->assertArrayHasKey('items', $result);

    // When composer.lock is missing, an error is expected
    $this->assertTrue(
        isset($result['error']) || isset($result['packages_scanned'])
    );
}

}
