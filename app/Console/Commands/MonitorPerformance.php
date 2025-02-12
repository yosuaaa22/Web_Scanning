<?php
// app/Console/Commands/MonitorPerformance.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorPerformance extends Command
{
    protected $signature = 'monitor:performance';
    protected $description = 'Monitor system performance metrics';

    public function handle()
    {
        try {
            $metrics = [
                'memory_usage' => memory_get_usage(true),
                'timestamp' => now()->timestamp,
                'php_memory_limit' => ini_get('memory_limit'),
                'php_memory_usage' => memory_get_usage(true),
                'cpu_usage' => $this->getSimpleCpuUsage(),
                'disk_free_space' => $this->getDiskSpace(),
            ];

            // Log metrics
            Log::info('Performance metrics', $metrics);

            // Store in database
            \App\Models\PerformanceMetric::create($metrics);

            $this->info('Performance metrics collected successfully:');
            $this->table(
                ['Metric', 'Value'],
                collect($metrics)->map(fn($value, $key) => [
                    $key, 
                    is_array($value) ? json_encode($value) : $value
                ])
            );

        } catch (\Exception $e) {
            Log::error('Performance monitoring failed: ' . $e->getMessage());
            $this->error('Failed to collect performance metrics: ' . $e->getMessage());
        }
    }

    private function getSimpleCpuUsage()
    {
        // Simplified CPU usage estimation based on process count
        try {
            $cmd = PHP_OS_FAMILY === 'Windows' ? 'tasklist' : 'ps aux';
            $processes = explode("\n", shell_exec($cmd));
            return count($processes);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getDiskSpace()
    {
        try {
            $drive = PHP_OS_FAMILY === 'Windows' ? 'C:' : '/';
            return [
                'free_space' => disk_free_space($drive),
                'total_space' => disk_total_space($drive),
                'usage_percentage' => round((1 - (disk_free_space($drive) / disk_total_space($drive))) * 100, 2)
            ];
        } catch (\Exception $e) {
            return [
                'free_space' => 0,
                'total_space' => 0,
                'usage_percentage' => 0
            ];
        }
    }
}