<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebsiteMonitoring extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'url', 
        'status', 
        'last_checked', 
        'vulnerabilities'
    ];

    protected $casts = [
        'last_checked'    => 'datetime',
        'vulnerabilities' => 'array',
    ];
}
