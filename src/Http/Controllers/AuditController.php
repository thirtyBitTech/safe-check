<?php 

namespace Thirtybittech\SafeCheck\Http\Controllers;

use Illuminate\Http\Request;
use Thirtybittech\SafeCheck\Repositories\ScanRepository;
use Thirtybittech\SafeCheck\Services\DependencyScanner;
use Symfony\Component\HttpFoundation\StreamedResponse;

use Thirtybittech\SafeCheck\Services\OsvClient;
use Illuminate\Support\Facades\Cache;


class AuditController
{
    public function __construct(
        private DependencyScanner $scanner,
        private ScanRepository $repo,
        private OsvClient $osv, // 👈 add this

    ) {}

    public function index()
    {
        return view('safe-check::audit');
    }

    public function scan(Request $request)
    {
        abort_unless($request->user()->can('safe-check run'), 403);

        return response()->json($this->scanner->run());
    }

    public function latest()
    {
        return response()->json($this->repo->latest());
    }

    public function exportJson(): StreamedResponse
{
    abort_unless(auth()->user()->can('safe-check export'), 403);

    $latest = $this->repo->latest();
    if (!$latest) {
        abort(404);
    }

    $export = [
        'scanned_at' => $latest['scanned_at'] ?? null,
        'packages_scanned' => $latest['packages_scanned'] ?? 0,
        'vulnerabilities_found' => $latest['vulnerabilities_found'] ?? 0,
        'items' => collect($latest['items'] ?? [])->map(fn ($item) => [
            'package' => $item['package'] ?? null,
            'installed_version' => $item['installed_version'] ?? null,
            'id' => $item['id'] ?? null,
        ])->values()->all(),
    ];

    $filename = 'safe-check-report-' . now()->format('Y-m-d_His') . '.json';

    return response()->streamDownload(
        fn () => print json_encode($export, JSON_PRETTY_PRINT),
        $filename,
        ['Content-Type' => 'application/json']
    );
}


public function vulnerability(Request $request, string $id)
        {

            abort_unless($request->user()->can('safe-check view'), 403);


            $request->validate([
                'package' => 'nullable|string|max:255',
                'installed_version' => 'nullable|string|max:50',
            ]);


            // Basic sanity check (OSV IDs are predictable)
            if (!preg_match('/^[A-Za-z0-9\-]+$/', $id)) {
                abort(400, 'Invalid vulnerability ID');
            }

            // Cache aggressively – vuln details rarely change
            $cacheKey = "safe-check:vuln:{$id}";

            try {
                $vuln = Cache::remember($cacheKey, now()->addDays(7), function () use ($id) {
                    return $this->osv->fetchVulnerabilityById($id);
                });
            } catch (\Throwable $e) {
                report($e);
                abort(502, 'Unable to fetch vulnerability details');
            }

            if (!$vuln) {
                abort(404, 'Vulnerability not found');
            }

            // Now normalize WITH context
            return response()->json(
                $this->osv->normalizeVulnerability(
                    $request->string('package'),
                    $request->string('installed_version'),
                    $vuln
                )
            );
        }

}
