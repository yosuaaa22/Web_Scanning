<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
// Di bagian atas file
use App\Models\SslDetail;

class Website extends Model
{
    protected $table = 'websitess';
    
    protected $guarded = [];
    
    protected $casts = [
        'monitoring_settings' => 'array',
        'notification_settings' => 'array',
        'analysis_data' => 'array',
        'last_checked' => 'datetime',
        'next_scheduled_scan' => 'datetime',
        'scan_results' => 'array',
    ];

    // Relasi ke SslDetails
    public function sslDetails(): HasOne
    {
        return $this->hasOne(SslDetail::class, 'website_id');
    }

    // Relasi ke ScanHistori (diubah dari ScanHistory)
    
    public function scanHistori(): HasMany
    {
        return $this->hasMany(ScanHistori::class, 'website_id');
    }

    // Relasi ke scan terakhir
    public function latestScan(): HasOne
    {
        return $this->hasOne(ScanHistori::class, 'website_id')
            ->latestOfMany('scanned_at');
    }

    // Mutator untuk URL
    protected function url(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $this->normalizeUrl($value),
        )->shouldCache();
    }

    // Normalisasi URL
    private function normalizeUrl(string $url): string
    {
        $url = Str::lower(trim($url));
        
        if (!Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://' . $url;
        }
        
        $parsed = parse_url($url);
        $host = str_replace('www.', '', $parsed['host'] ?? '');
        
        return rtrim(
            sprintf(
                '%s://%s%s',
                $parsed['scheme'] ?? 'https',
                $host,
                $parsed['path'] ?? ''
            ),
            '/'
        );
    }

    // Cek status website
    public function isOnline(): bool
    {
        return $this->status === 'up';
    }

    // Hitung persentase uptime yang disesuaikan
    public function uptimePercentage(int $days = 7): float
    {
        $total = $this->scanHistori()
            ->where('scanned_at', '>=', now()->subDays($days))
            ->count();

        if ($total === 0) {
            return 100.0;
        }

        $successful = $this->scanHistori()
            ->where('status', 'up')
            ->where('scanned_at', '>=', now()->subDays($days))
            ->count();

        return round(($successful / $total) * 100, 2);
    }

    // Scope untuk website aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope untuk status dengan penanganan case
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', Str::lower($status));
    }
}