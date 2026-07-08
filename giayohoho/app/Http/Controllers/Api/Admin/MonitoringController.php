<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MonitoringController extends Controller
{
    public function summary()
    {
        $checkedAt = now()->toIso8601String();
        $enabled = (bool) config('services.monitoring.enabled', false);
        $warnings = [];

        if (! $enabled) {
            $warnings[] = 'Admin monitoring is disabled by MONITORING_ENABLED=false.';
        }

        $components = [
            $this->component('prometheus', 'Prometheus', 'giayohoho_prometheus', 'prom/prometheus', 'PromQL metrics store', config('services.monitoring.prometheus_url'), $enabled, '/-/ready', $warnings),
            $this->component('grafana', 'Grafana', 'giayohoho_grafana', 'grafana/grafana', 'Dashboard UI', config('services.monitoring.grafana_url'), $enabled, '/api/health', $warnings),
            $this->component('loki', 'Loki', 'giayohoho_loki', 'grafana/loki', 'Log store', config('services.monitoring.loki_url'), $enabled, '/ready', $warnings),
        ];

        return $this->ok([
            'checkedAt' => $checkedAt,
            'servicesStatus' => collect($components)->map(fn ($item) => [
                'name' => $item['name'],
                'status' => $item['status'],
                'uptime' => $item['status'] === 'operational' ? 100 : ($item['status'] === 'degraded' ? 75 : 0),
            ])->values(),
            'metricsData' => [
                [
                    'id' => 'api-health',
                    'label' => 'Laravel API',
                    'value' => 'ok',
                    'unit' => '',
                    'chartType' => 'flat',
                    'sparklineData' => [],
                    'trendDirection' => 'up',
                    'trendPercent' => 0,
                    'status' => 'operational',
                ],
            ],
            'healthServices' => collect($components)->map(fn ($item) => [
                'id' => $item['id'],
                'name' => $item['name'],
                'container' => $item['container'],
                'status' => $item['status'],
                'logsUrl' => null,
            ])->values(),
            'recentErrors' => [],
            'stackItems' => collect($components)->map(fn ($item) => [
                'name' => $item['name'],
                'container' => $item['container'],
                'source' => $item['source'],
                'ready' => $item['status'] === 'operational',
                'iconKey' => $item['id'] === 'grafana' ? 'frontend' : 'metrics',
            ])->values(),
            'componentDetails' => $components,
            'prometheusTargets' => [],
            'dockerContainers' => [],
            'grafanaDatasources' => [
                ['uid' => 'prometheus', 'name' => 'Prometheus', 'type' => 'prometheus', 'status' => $components[0]['status'], 'message' => 'Configured via MONITORING_PROMETHEUS_URL'],
                ['uid' => 'loki', 'name' => 'Loki', 'type' => 'loki', 'status' => $components[2]['status'], 'message' => 'Configured via MONITORING_LOKI_URL'],
            ],
            'grafanaDashboards' => [
                ['uid' => 'ohgiay-loki-logs', 'title' => 'OhGiay Loki Logs', 'folder' => 'OhGiay', 'url' => '/d/ohgiay-loki-logs/ohgiay-loki-logs'],
                ['uid' => 'ohgiay-system-overview', 'title' => 'OhGiay System Overview', 'folder' => 'OhGiay', 'url' => '/d/ohgiay-system-overview/ohgiay-system-overview'],
                ['uid' => 'ohgiay-k6-performance', 'title' => 'OhGiay k6 Performance', 'folder' => 'OhGiay', 'url' => '/d/ohgiay-k6-performance/ohgiay-k6-performance'],
            ],
            'warnings' => $warnings,
        ], 'Đã tải trạng thái monitoring');
    }

    public function logql(Request $request)
    {
        $data = $request->validate([
            'query' => 'required|string|max:2000',
        ]);

        $lokiUrl = rtrim((string) config('services.monitoring.loki_url'), '/');
        if (! config('services.monitoring.enabled') || $lokiUrl === '') {
            return $this->ok(['results' => []], 'Monitoring chưa được bật hoặc chưa cấu hình Loki.');
        }

        $end = now();
        $start = $end->copy()->subHours(6);
        $response = Http::timeout((int) config('services.monitoring.timeout_seconds', 3))
            ->get($lokiUrl.'/loki/api/v1/query_range', [
                'query' => $data['query'],
                'start' => (string) ($start->timestamp * 1000000000),
                'end' => (string) ($end->timestamp * 1000000000),
                'limit' => 100,
            ]);

        if (! $response->successful()) {
            return $this->ok(['results' => []], 'Không truy vấn được Loki.');
        }

        $results = collect($response->json('data.result', []))
            ->flatMap(fn ($stream) => collect($stream['values'] ?? [])->map(fn ($value) => [
                'timestamp' => isset($value[0]) ? date('H:i:s d/m', (int) floor(((int) $value[0]) / 1000000000)) : '',
                'service' => $stream['stream']['service'] ?? $stream['stream']['app'] ?? $stream['stream']['container'] ?? 'unknown',
                'message' => Str::limit((string) ($value[1] ?? ''), 1000, '...'),
            ]))
            ->take(100)
            ->values();

        return $this->ok(['results' => $results], 'Đã truy vấn LogQL');
    }

    private function component(
        string $id,
        string $name,
        string $container,
        string $image,
        string $source,
        ?string $endpoint,
        bool $enabled,
        string $probePath,
        array &$warnings
    ): array {
        $status = 'offline';
        $endpoint = rtrim((string) $endpoint, '/');

        if ($enabled && $endpoint !== '') {
            try {
                $response = Http::timeout((int) config('services.monitoring.timeout_seconds', 3))->get($endpoint.$probePath);
                $status = $response->successful() ? 'operational' : 'degraded';
            } catch (\Throwable $exception) {
                $warnings[] = "{$name}: ".$exception->getMessage();
                $status = 'offline';
            }
        }

        return [
            'id' => $id,
            'name' => $name,
            'container' => $container,
            'image' => $image,
            'source' => $source,
            'endpoint' => $endpoint,
            'status' => $status,
            'summary' => $enabled ? "{$name} health probe" : "{$name} monitoring probe disabled",
            'details' => [
                ['label' => 'Probe', 'value' => $probePath],
                ['label' => 'Endpoint', 'value' => $endpoint ?: 'not configured'],
            ],
        ];
    }
}
