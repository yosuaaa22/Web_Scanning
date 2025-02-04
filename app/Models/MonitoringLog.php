<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonitoringLog extends Model
{
    use HasFactory;

    protected $table = 'monitoring_logs';

    protected $fillable = [
        'status',
        'response_time',
        'status_code',
        'memory_usage',
        'cpu_usage',
        'performance_metrics',
        'created_at',
        'updated_at',
    ];

    public $timestamps = true;
}
