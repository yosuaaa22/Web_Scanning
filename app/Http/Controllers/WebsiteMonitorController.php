<?php

namespace App\Http\Controllers;

use App\Jobs\WebsiteMonitorJob;
use App\Models\Website;
use App\Services\WebsiteMonitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WebsiteMonitorController extends Controller
{
    protected $monitorService;

    public function __construct(WebsiteMonitorService $monitorService)
    {
        $this->monitorService = $monitorService;
    }

    public function index()
    {
        $websites = Website::active() // Misalnya, scope untuk website yang aktif
            ->orderByDesc('last_checked_at')
            ->limit(10)
            ->get();

        return view('monitor.index', compact('websites'));
    }

    public function getRealTimeStatus()
    {
        $websites = Website::query()->limit(100)->get()->map(function ($website) {
            $cacheKey = 'website_status_' . $website->id;
            $cachedStatus = Cache::get($cacheKey, []);

            if (!$cachedStatus || $this->monitorService->isWebsiteStatusExpired($website)) {
                WebsiteMonitorJob::dispatch($website)->onQueue('high');
                $cachedStatus = $this->getCachedWebsiteStatus($website);
            }

            return [
                'id' => $website->id,
                'name' => $website->name,
                'url' => $website->url,
                'status' => $cachedStatus['status'] ?? 'pending',
                'response_time' => $cachedStatus['response_time'] ?? null,
                'last_checked_at' => $cachedStatus['last_checked_at'] ?? null,
                'vulnerabilities' => $cachedStatus['vulnerabilities'] ?? []
            ];
        });

        return response()->json($websites);
    }

    public function getCachedWebsiteStatus(Website $website)
    {
        $cacheKey = 'website_status_' . $website->id;

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($website) {
            try {
                $status = $this->monitorService->getWebsiteStatus($website);
                return array_merge($status, ['vulnerabilities' => $status['vulnerabilities'] ?? []]);
            } catch (\Exception $e) {
                Log::error('Failed to get website status', [
                    'website_id' => $website->id,
                    'error' => $e->getMessage()
                ]);
                return ['status' => 'error', 'error_message' => 'Failed to check status', 'last_checked_at' => now()];
            }
        });
    }
}
