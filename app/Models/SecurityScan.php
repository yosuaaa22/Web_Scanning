<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityScan extends Model
{
    use HasFactory;

    protected $table = 'security_scans';

    protected $fillable = [
        'website_id',
        'vulnerabilities',
        'headers_analysis',
        'ssl_details',
        'content_security_analysis',
        'created_at',
        'updated_at',
    ];

    public $timestamps = true;

    public function website()
    {
        return $this->belongsTo(Website::class);
    }
}