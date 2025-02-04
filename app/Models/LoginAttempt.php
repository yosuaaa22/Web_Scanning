<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $fillable = [
        'ip_address', 
        'user_agent', 
        'device_type', 
        'browser', 
        'status', 
        'location', 
        'user_id'
    ];

    protected $casts = [
        'is_blocked' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function recordAttempt($user = null, $status = 'attempt')
    {
        $location = self::getLocationFromIP(request()->ip());

        return self::create([
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'device_type' => app('agent')->deviceType(),
            'browser' => app('agent')->browser(),
            'status' => $status,
            'location' => $location,
            'user_id' => $user ? $user->id : null
        ]);
    }

    public static function getLocationFromIP($ip)
    {
        try {
            $response = \Illuminate\Support\Facades\Http::get("http://ip-api.com/json/{$ip}");
            $data = $response->json();
            return $data['status'] === 'success' 
                ? "{$data['city']}, {$data['country']}" 
                : 'Unknown Location';
        } catch (\Exception $e) {
            return 'Location Unavailable';
        }
    }
}