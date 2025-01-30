<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanResult extends Model
{
    protected $fillable = [
        'url',
        'backdoor_risk',
        'gambling_risk',
        'scan_time',
        'detailed_report'
    ];

    protected $casts = [
        'scan_time' => 'datetime',
        'detailed_report' => 'array'
    ];

    public function history()
    {
        return $this->hasMany(ScanHistory::class);
    }
}
