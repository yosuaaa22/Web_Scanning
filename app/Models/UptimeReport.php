<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UptimeReport extends Model
{
    use HasFactory;

    protected $table = 'uptime_reports';

    protected $fillable = [
        'website_id',
        'uptime_percentage',
        'total_downtime_minutes',
        'incidents_count',
        'period_start',
        'period_end',
        'created_at',
        'updated_at',
    ];

    public $timestamps = true;

    public function website()
    {
        return $this->belongsTo(Website::class);
    }
}
