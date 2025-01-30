<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanHistory extends Model
{
    protected $fillable = [
        'scan_result_id',
        'url',
        'risk_level',
        'detected_threats',
        'scan_timestamp'
    ];

    protected $casts = [
        'detected_threats' => 'array',
        'scan_timestamp' => 'datetime'
    ];

    public function scanResult()
    {
        return $this->belongsTo(ScanResult::class);
    }
}
