<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class ScanResult extends Model
{
    use Notifiable;

    protected $fillable = [
        'url',
        'backdoor_risk',
        'gambling_risk',
        'scan_time',
        'detailed_report'
    ];

    protected $casts = [
        'scan_time' => 'datetime',
    ];

    /**
     * Tentukan route notifikasi untuk channel telegram.
     *
     * @return string|null
     */
    public function routeNotificationForTelegram()
    {
        // Pastikan variabel ini terisi di config/services.php (.env)
        return config('services.telegram.bot_chat_id');
    }
}
