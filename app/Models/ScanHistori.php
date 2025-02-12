<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanHistori extends Model
{
    protected $table = 'scan_histori';
    
    protected $fillable = [
        'website_id',
        'status',       // Tambahkan kolom status
        'scan_results',
        'scanned_at',
        'response_time' // Jika ada di migration
    ];

    protected $casts = [
        'scan_results' => 'array',
        'scanned_at' => 'datetime',
        'response_time' => 'integer' // Jika ada
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(
            related: Website::class, 
            foreignKey: 'website_id',
            ownerKey: 'id'
        );
    }

    // Accessor untuk status
    public function getStatusAttribute($value): string
    {
        return strtolower($value);
    }

    // Scope untuk status up
    public function scopeUp($query)
    {
        return $query->where('status', 'up');
    }

    // Scope untuk status down
    public function scopeDown($query)
    {
        return $query->where('status', 'down');
    }
}