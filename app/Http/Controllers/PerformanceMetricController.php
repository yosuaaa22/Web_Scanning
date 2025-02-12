<?php
// app/Http/Controllers/PerformanceMetricController.php
namespace App\Http\Controllers;

use App\Models\PerformanceMetric;
use Illuminate\Http\Request;

class PerformanceMetricController extends Controller
{
    public function index()
    {
        $metrics = PerformanceMetric::latest()->take(50)->get();
        
        return view('performance.index', [
            'metrics' => $metrics
        ]);
    }

    public function dashboard()
    {
        $latestMetric = PerformanceMetric::latest()->first();
        $averageMemory = PerformanceMetric::avg('memory_usage');
        $highUsageCount = PerformanceMetric::highMemoryUsage()->count();

        return view('performance.dashboard', compact(
            'latestMetric',
            'averageMemory',
            'highUsageCount'
        ));
    }

    public function api()
    {
        return PerformanceMetric::recent()
            ->take(50)
            ->get();
    }
}