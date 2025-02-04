<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Website extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'status',
        'response_time',
        'last_checked_at',
        'ssl_expires_at',
        'domain_expires_at',
        'uptime_percentage',
        'check_interval',
        'expected_status_code',
        'expected_response_pattern',
        'alert_threshold_response_time',
        'notification_channels'
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
        'ssl_expires_at' => 'datetime',
        'domain_expires_at' => 'datetime',
        'vulnerabilities' => 'array',
        'notification_channels' => 'array',
        'uptime_percentage' => 'float',
        'alert_threshold_response_time' => 'integer',
        'expected_status_code' => 'integer',
    ];

    protected $attributes = [
        'vulnerabilities' => '[]',
        'notification_channels' => '["email"]',
        'check_interval' => 300, // 5 minutes default
        'expected_status_code' => 200,
        'alert_threshold_response_time' => 1000 // 1 second default
    ];

    // Relationships
    public function monitoringLogs(): HasMany
    {
        return $this->hasMany(MonitoringLog::class);
    }

    public function uptimeReports(): HasMany
    {
        return $this->hasMany(UptimeReport::class);
    }

    public function securityScans(): HasMany
    {
        return $this->hasMany(SecurityScan::class);
    }

    // Scopes
    public function scopeNeedsCheck($query)
    {
        return $query->where('last_checked_at', '<=', now()->subSeconds('check_interval'));
    }

    // Helper Methods
    public function isDown(): bool
    {
        return $this->status === 'down';
    }

    public function hasHighLatency(): bool
    {
        return $this->response_time > $this->alert_threshold_response_time;
    }

    public function sslExpiresWithin(int $days): bool
    {
        return $this->ssl_expires_at && 
               $this->ssl_expires_at->lte(now()->addDays($days));
    }

    public function calculateUptimePercentage(): float
    {
        $total = $this->monitoringLogs()
            ->where('created_at', '>=', now()->subMonth())
            ->count();
            
        $uptime = $this->monitoringLogs()
            ->where('created_at', '>=', now()->subMonth())
            ->where('status', 'up')
            ->count();

        return $total > 0 ? ($uptime / $total) * 100 : 100;
    }
}