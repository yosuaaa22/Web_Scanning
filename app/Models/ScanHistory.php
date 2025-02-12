<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanHistory extends Model
{
    protected $fillable = [
        'website_id',       // Tambahkan ini untuk relasi ke Website
        'scan_result_id',   // Jika ada relasi ke ScanResult
        'url',              // Duplikasi data untuk histori
        'risk_level',       // Tingkat risiko
        'detected_threats', // Ancaman yang terdeteksi
        'scan_timestamp'    // Waktu scan
    ];

    protected $casts = [
        'detected_threats' => 'array',
        'scan_timestamp' => 'datetime'
    ];

    // Relasi ke Website
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    // Relasi ke ScanResult (jika diperlukan)
    public function scanResult(): BelongsTo
    {
        return $this->belongsTo(ScanResult::class);
    }
}