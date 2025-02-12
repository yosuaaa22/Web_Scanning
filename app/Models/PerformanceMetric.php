<?php
// app/Models/PerformanceMetric.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceMetric extends Model
{
    protected $fillable = [
        'memory_usage',
        'timestamp',
        'php_memory_limit',
        'php_memory_usage',
        'cpu_usage',
        'disk_free_space'
    ];

    protected $casts = [
        'disk_free_space' => 'array',
        'timestamp' => 'datetime',
        'cpu_usage' => 'float',
        'memory_usage' => 'integer',
        'php_memory_usage' => 'integer'
    ];

    // Optional: Tambahkan scope untuk query umum
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeHighMemoryUsage($query)
    {
        return $query->where('memory_usage', '>', 1024 * 1024 * 100); // > 100MB
    }
}