<?php
namespace Thirtybittech\SafeCheck\Services;

use Illuminate\Support\Carbon;
use Thirtybittech\SafeCheck\Repositories\ScanRepository;

class DependencyScanner
{
    public function __construct(
        private OsvClient $osv,
        private ScanRepository $repo,
    ) {}

public function run(): array
{
    $lockPath = base_path('composer.lock');

    if (!is_readable($lockPath)) {
        report("SafeCheck: composer.lock not readable at {$lockPath}");
        return $this->persistError('composer.lock not found or not readable');
    }

    $raw = @file_get_contents($lockPath);
    if ($raw === false) {
        report("SafeCheck: failed to read composer.lock");
        return $this->persistError('Unable to read composer.lock');
    }

    try {
        $lock = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        report($e);
        return $this->persistError('Invalid composer.lock JSON');
    }

    $packages = array_merge(
        $lock['packages'] ?? [],
        $lock['packages-dev'] ?? []
    );

    $queries = [];
    $packageMap = []; 

    foreach ($packages as $pkg) {
        if (empty($pkg['name']) || empty($pkg['version'])) {
            continue;
        }

        $queries[] = [
            'name' => $pkg['name'],
            'version' => $pkg['version'],
        ];

        $packageMap[] = $pkg;
    }

    if (empty($queries)) {
        return $this->persistError('No valid packages found in composer.lock');
    }

    // 🔹 Query OSV
    try {
        $batchResults = $this->osv->queryBatch($queries);
    } catch (\Throwable $e) {
        report($e);
        return $this->persistError('Failed to query vulnerability database');
    }

    // 🔹 Normalize results SAFELY
    $results = [];

    foreach ($packageMap as $i => $pkg) {
        $result = $batchResults[$i] ?? null;

        if (!$result || empty($result['vulns'])) {
            continue;
        }

        foreach ($result['vulns'] as $vuln) {
            $normalized = $this->osv->normalize(
                $pkg['name'],
                $pkg['version'],
                $vuln
            );

            if ($normalized) {
                $results[] = $normalized;
            }
        }
    }

    // 🔹 Summary
    $packagesScanned = count($packageMap);
    $vulnCount = count($results);
    $highCritical = collect($results)->filter(fn ($i) =>
        in_array(strtolower($i['severity'] ?? ''), ['high', 'critical'], true)
    )->count();

    $payload = [
        'scanned_at' => Carbon::now()->toIso8601String(),
        'packages_scanned' => $packagesScanned,
        'vulnerabilities_found' => $vulnCount,
        'high_critical' => $highCritical,
        'items' => $results,
        'disclaimer' =>
            'This tool provides visibility into known dependency vulnerabilities. ' .
            'It does not guarantee application security.',
    ];

    $this->repo->save($payload);

    return $payload;
}


    private function persistError(string $message): array
    {
        $payload = [
            'scanned_at' => Carbon::now()->toIso8601String(),
            'error' => $message,
            'items' => [],
        ];
        $this->repo->save($payload);
        return $payload;
    }
}
