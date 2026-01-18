<?php 
namespace Thirtybittech\SafeCheck\Services;

use Illuminate\Support\Facades\Http;

class OsvClient
{
    public function queryComposerPackage(string $package, string $version): array
    {
        $response = Http::timeout(10)->post('https://api.osv.dev/v1/query', [
            'package' => [
                'name' => $package,
                'ecosystem' => 'Packagist',
            ],
            'version' => ltrim($version, 'v'),
        ]);
        $response->throw();

        if (!$response->ok()) {
            return [];
        }

        $data = $response->json();
        $vulns = $data['vulns'] ?? [];

        return array_values(array_filter(array_map(
            fn($v) => $this->normalize($package, $version, $v),
            $vulns
        )));
    }

    public function queryBatch(array $packages): array
{
    $queries = collect($packages)->map(fn ($pkg) => [
        'package' => [
            'name' => $pkg['name'],
            'ecosystem' => 'Packagist',
        ],
        'version' => ltrim($pkg['version'], 'v'),
    ])->values()->all();

    $response = Http::timeout(10)
        ->post('https://api.osv.dev/v1/querybatch', [
            'queries' => $queries,
        ]);

    $response->throw();

    if (!$response->ok()) {
        return [];
    }

    $results = $response->json()['results'] ?? [];

    return array_values($results);
}

public function fetchVulnerabilityById(string $id): ?array
{
    $response = Http::timeout(10)
        ->get("https://api.osv.dev/v1/vulns/{$id}");

    $response->throw();


    return $response->json();
}



    public function normalize(string $package, string $installedVersion, array $v): ?array
    {
        // Best-effort severity
        $severity = $this->pickSeverity($v);

        // Affected range (best-effort)
        $affectedRange = $this->pickAffectedRange($v);

        // Plain-English (your edge): keep it templated for MVP
        $title = $v['summary'] ?? ($v['details'] ?? 'Vulnerability found');
        $summary = $v['summary'] ?? null;

        $why = $this->whyItMattersTemplate($severity);
        $next = $this->nextStepTemplate($v);

        return [
            'package' => $package,
            'installed_version' => $installedVersion,
            'affected_range' => $affectedRange,
            'severity' => $severity,
            'title' => $title,
            'summary' => $summary,
            'why_it_matters' => $why,
            'next_step' => $next,
            'references' => collect($v['references'] ?? [])->pluck('url')->filter()->values()->all(),
            'id' => $v['id'] ?? null,
        ];
    }
    

   protected function pickSeverity(array $v): string
{
    // 1. ecosystem-specific (best signal)
    foreach ($v['affected'] ?? [] as $affected) {
        if (!empty($affected['ecosystem_specific']['severity'])) {
            return strtoupper($affected['ecosystem_specific']['severity']);
        }
    }

    // 2. database-specific (GitHub / NVD)
    if (!empty($v['database_specific']['severity'])) {
        return strtoupper($v['database_specific']['severity']);
    }

    // 3. CVSS
    $cvss = $this->extractCvssScore($v);
    if ($cvss !== null) {
        return match (true) {
            $cvss >= 9.0 => 'CRITICAL',
            $cvss >= 7.0 => 'HIGH',
            $cvss >= 4.0 => 'MEDIUM',
            default => 'LOW',
        };
    }

    // 4. Keyword fallback
    $text = strtolower(($v['summary'] ?? '') . ' ' . ($v['details'] ?? ''));
    if (str_contains($text, 'remote code execution')) return 'CRITICAL';
    if (str_contains($text, 'use-after-free')) return 'HIGH';

    return 'UNKNOWN';
}

public function normalizeVulnerability(
    string $package,
    string $installedVersion,
    array $v
): array {
    $severity = $this->pickSeverity($v);

    return [
        'id' => $v['id'] ?? null,
        'package' => $package,
        'installed_version' => $installedVersion,
        'severity' => $severity,
        'affected_range' => $this->pickAffectedRange($v),
        'summary' => $v['summary'] ?? null,
        'details' => $v['details'] ?? null,
        'published' =>  $this->formatPublishedDate($v['published'] ?? null),
        'why_it_matters' => $this->whyItMattersTemplate($severity),
        'next_step' => $this->nextStepTemplate($v),
        'references' => collect($v['references'] ?? [])
            ->map(fn ($r) => [
                'url' => $r['url'] ?? null,
                'type' => $r['type'] ?? null,
            ])
            ->filter(fn ($r) => !empty($r['url']))
            ->values()
            ->all(),
    ];
}





protected function extractCvssScore(array $v): ?float
{
    foreach ($v['severity'] ?? [] as $s) {
        if (($s['type'] ?? null) === 'CVSS_V3') {
            return (float) ($s['score'] ?? null);
        }
    }
    return null;
}


private function formatPublishedDate(?string $published): ?string
{
    if (empty($published)) {
        return null;
    }

    try {
        return \Carbon\Carbon::parse($published)
            ->utc()
            ->format('Y-m-d H:i');
    } catch (\Throwable $e) {
        return null;
    }
}

    protected function pickAffectedRange(array $v): string
{
    $ranges = [];

    foreach ($v['affected'] ?? [] as $affected) {

        // Prefer ecosystem ranges (best signal)
        foreach ($affected['ranges'] ?? [] as $range) {
            if ($range['type'] === 'ECOSYSTEM') {
                $introduced = null;
                $fixed = null;

                foreach ($range['events'] as $event) {
                    $introduced ??= $event['introduced'] ?? null;
                    $fixed ??= $event['fixed'] ?? null;
                }

                if ($introduced && $fixed) {
                    $ranges[] = "{$introduced} – < {$fixed}";
                    continue 2;
                }
            }
        }

        // Fallback: explicit versions list
        if (!empty($affected['versions'])) {
            $ranges[] = implode(', ', $affected['versions']);
        }
    }

    if (empty($ranges)) {
        return 'Unknown affected range';
    }

    return  implode(' | ', $ranges);
}




    private function whyItMattersTemplate(string $severity): string
    {
        
        return match (trim(strtolower((string) $severity))) {
            'critical' => 'This is a serious issue that could allow major compromise if the vulnerable code path is reachable.',
            'high' => 'This can cause real security impact in common setups, especially on public-facing routes.',
            'medium' => 'This may be exploitable in specific conditions. It’s worth addressing when you can.',
            default => 'This is likely low impact, but still worth tracking.',
        };
    }

    protected function nextStepTemplate(array $v): string
{
    foreach ($v['affected'] ?? [] as $affected) {
        foreach ($affected['ranges'] ?? [] as $range) {
            foreach ($range['events'] ?? [] as $event) {
                if (!empty($event['fixed'])) {
                    return 'Update to a version that includes the fix.';
                }
            }
        }
    }

    return 'No fix available yet. Monitor and reduce exposure.';
}
}
