<?php

namespace App\Modules\Governance\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Modules\Governance\Models\AnalyticsEvent;

class TrackAnalyticsMiddleware
{
    protected array $excludedPrefixes = [
        'api/v1/analytics',
        'api/v1/audit-logs',
        'api/health',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $this->trackRequest($request, $response, $start);

        return $response;
    }

    protected function trackRequest(Request $request, Response $response, float $start): void
    {
        $path = $request->path();

        foreach ($this->excludedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $durationMs = round((microtime(true) - $start) * 1000, 2);

        AnalyticsEvent::create([
            'user_id'     => auth()->id(),
            'event_name'  => 'api_request',
            'event_props' => [
                'method'      => $request->method(),
                'path'        => $path,
                'status_code' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
            ],
        ]);
    }
}
